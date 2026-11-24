<?php

declare(strict_types=1);

namespace App\Sync;

use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

/**
 * Importa a cópia do servidor no notebook. Substitui os dados de negócio do notebook.
 *
 * Esvazia as tabelas (filhos primeiro) e grava tudo sem registrar alterações, porque
 * o que chega do servidor não é uma alteração do notebook. Tudo acontece em uma única
 * transação: sem a marca de fim, a cópia foi cortada e nada é gravado. Ao final, o cursor
 * de recebimento começa no ponto em que a cópia foi tirada.
 */
final class CopyImporter
{
    public function __construct(
        private readonly SyncState $estado,
        private readonly SensitiveFields $camposCifrados,
    ) {}

    /**
     * @param  iterable<int, string>  $linhas  Linhas NDJSON geradas por CopyExporter.
     */
    public function importar(iterable $linhas): void
    {
        $cursor = 0;

        DB::transaction(function () use ($linhas, &$cursor): void {
            $this->suprimirRegistro(true);

            try {
                $this->esvaziarTabelas();
                $cabecalhoRecebido = false;
                $terminou = false;
                $linhasRecebidas = 0;

                foreach ($linhas as $linha) {
                    try {
                        $registro = json_decode(trim($linha), true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        throw new RuntimeException('A cópia contém uma linha NDJSON inválida.', previous: $exception);
                    }

                    if (! is_array($registro)) {
                        throw new RuntimeException('A cópia contém uma linha NDJSON inválida.');
                    }

                    if ($terminou) {
                        throw new RuntimeException('A cópia contém dados depois da marca de fim.');
                    }

                    switch ($registro['tipo'] ?? null) {
                        case 'cabecalho':
                            if (($registro['versao'] ?? null) !== 1) {
                                throw new RuntimeException('A versão do protocolo da cópia não é suportada.');
                            }

                            $cursorRecebido = $registro['cursor'] ?? null;

                            if ($cabecalhoRecebido || $linhasRecebidas > 0 || ! is_int($cursorRecebido) || $cursorRecebido < 0) {
                                throw new RuntimeException('O cabeçalho da cópia é inválido.');
                            }

                            $cursor = $cursorRecebido;
                            $cabecalhoRecebido = true;

                            break;

                        case 'linha':
                            if (! $cabecalhoRecebido || ! is_string($registro['tabela'] ?? null) || ! is_array($registro['dados'] ?? null)) {
                                throw new RuntimeException('A cópia contém um registro inválido.');
                            }

                            $this->gravar($registro['tabela'], $registro['dados']);
                            $linhasRecebidas++;

                            break;

                        case 'fim':
                            $quantidadeRecebida = $registro['linhas'] ?? null;

                            if (! $cabecalhoRecebido || ! is_int($quantidadeRecebida) || $quantidadeRecebida !== $linhasRecebidas) {
                                throw new RuntimeException('A cópia está incompleta: a quantidade de registros não confere.');
                            }

                            $terminou = true;

                            break;

                        default:
                            throw new RuntimeException('A cópia contém um tipo de linha desconhecido.');
                    }
                }

                if (! $cabecalhoRecebido || ! $terminou) {
                    throw new RuntimeException('A cópia chegou incompleta. Nada foi alterado neste computador.');
                }
            } finally {
                $this->suprimirRegistro(false);
            }

            $this->estado->definir(SyncState::ULTIMO_RECEBIDO, $cursor);
            $this->estado->definir(SyncState::ULTIMO_ENVIADO, 0);
        });
    }

    /**
     * Antes da cópia, cada tabela com número automático passa a contar a partir da faixa deste
     * notebook. Assim, o que este notebook criar nunca usa um número de outro notebook.
     *
     * No MySQL, alterar a estrutura de uma tabela confirma a transação aberta. Por isso esta função
     * roda fora da transação da importação.
     */
    public function prepararNumeracao(int $faixaInicial): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ((array) config('sincronizacao.ordem_da_copia') as $tabela) {
            // Confere a coluna pelo campo Extra em PHP, sem comparar texto no SQL (collations diferentes).
            foreach (DB::select("SHOW COLUMNS FROM `{$tabela}`") as $coluna) {
                if (str_contains((string) $coluna->Extra, 'auto_increment')) {
                    DB::statement("ALTER TABLE `{$tabela}` AUTO_INCREMENT = {$faixaInicial}");

                    break;
                }
            }
        }
    }

    private function esvaziarTabelas(): void
    {
        foreach (array_reverse((array) config('sincronizacao.ordem_da_copia')) as $tabela) {
            DB::table((string) $tabela)->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function gravar(string $tabela, array $dados): void
    {
        if (! in_array($tabela, (array) config('sincronizacao.tabelas'), true)) {
            throw new RuntimeException('A cópia contém uma tabela desconhecida.');
        }

        DB::table($tabela)->insert($this->camposCifrados->cifrar($tabela, $dados));
    }

    private function suprimirRegistro(bool $ligar): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET @sincronizacao_aplicando = '.($ligar ? '1' : 'NULL'));
        }
    }
}
