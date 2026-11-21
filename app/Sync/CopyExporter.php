<?php

declare(strict_types=1);

namespace App\Sync;

use Generator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gera a cópia completa do servidor: uma linha JSON por registro (formato NDJSON).
 *
 * A primeira linha diz até onde a cópia vai (cursor do registro de alterações), e a última
 * marca o fim. Se a conexão cair no meio, o notebook não recebe a marca de fim e desfaz tudo.
 * Campos cifrados saem em texto puro, dentro do HTTPS, e o notebook os cifra com a própria chave.
 */
final class CopyExporter
{
    public function __construct(
        private readonly SensitiveFields $camposCifrados,
    ) {}

    /**
     * @return Generator<int, string>
     */
    public function linhas(): Generator
    {
        $arquivo = fopen('php://temp/maxmemory:2097152', 'w+b');

        if ($arquivo === false) {
            throw new RuntimeException('Não foi possível preparar a cópia do servidor.');
        }

        try {
            // A leitura consistente impede que o cursor e os registros representem instantes diferentes.
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            DB::transaction(function () use ($arquivo): void {
                $cursor = (int) DB::table((string) config('sincronizacao.tabela_registro'))->max('id');
                $this->escreverLinha($arquivo, ['tipo' => 'cabecalho', 'versao' => 1, 'cursor' => $cursor]);

                $total = 0;

                foreach ((array) config('sincronizacao.ordem_da_copia') as $tabela) {
                    $consulta = DB::table((string) $tabela);

                    foreach (TableKeys::primaryKey((string) $tabela) as $coluna) {
                        $consulta->orderBy($coluna);
                    }

                    foreach ($consulta->cursor() as $registro) {
                        $dados = $this->camposCifrados->decifrar((string) $tabela, (array) $registro);

                        $this->escreverLinha($arquivo, ['tipo' => 'linha', 'tabela' => $tabela, 'dados' => $dados]);
                        $total++;
                    }
                }

                $this->escreverLinha($arquivo, ['tipo' => 'fim', 'linhas' => $total]);
            });

            rewind($arquivo);

            while (($linha = fgets($arquivo)) !== false) {
                yield $linha;
            }
        } finally {
            fclose($arquivo);
        }
    }

    /**
     * @param  resource  $arquivo
     * @param  array<string, mixed>  $registro
     */
    private function escreverLinha($arquivo, array $registro): void
    {
        $linha = json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

        if (fwrite($arquivo, $linha) !== strlen($linha)) {
            throw new RuntimeException('Não foi possível preparar a cópia do servidor.');
        }
    }
}
