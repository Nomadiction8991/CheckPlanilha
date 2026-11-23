<?php

declare(strict_types=1);

namespace App\Sync;

use InvalidArgumentException;
use RuntimeException;

/**
 * Histórico de auditoria: um evento por linha JSON, só acrescentado.
 *
 * Cada linha é identificada pelo SHA-256 do próprio texto. A mescla acrescenta só o que falta,
 * então repetir a sincronização não duplica nada.
 */
final class AuditLog
{
    public function __construct(
        private readonly string $arquivo,
    ) {
    }

    /**
     * @return list<array{sha256: string, linha: string}>
     */
    public function linhas(): array
    {
        if (! is_file($this->arquivo)) {
            return [];
        }

        $linhas = file($this->arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_map(
            fn (string $linha): array => ['sha256' => hash('sha256', $linha), 'linha' => $linha],
            $linhas,
        );
    }

    /**
     * Acrescenta as linhas que ainda não existem. Se uma linha não confere, nenhuma entra.
     * Devolve quantas linhas foram acrescentadas.
     *
     * @param  list<array{sha256: string, linha: string}>  $entradas
     */
    public function acrescentar(array $entradas): int
    {
        foreach ($entradas as $entrada) {
            $linha = (string) $entrada['linha'];
            $sha256 = (string) $entrada['sha256'];

            if (hash('sha256', $linha) !== $sha256) {
                throw new InvalidArgumentException('Uma linha da auditoria chegou corrompida. O lote inteiro foi recusado.');
            }

            if (str_contains($linha, "\n") || str_contains($linha, "\r") || ! is_array(json_decode($linha, true))) {
                throw new InvalidArgumentException('Uma linha da auditoria não é um evento válido. O lote inteiro foi recusado.');
            }
        }

        if ($entradas === []) {
            return 0;
        }

        $pasta = dirname($this->arquivo);

        if (! is_dir($pasta) && ! mkdir($pasta, 0775, true) && ! is_dir($pasta)) {
            throw new InvalidArgumentException('Não foi possível criar a pasta da auditoria.');
        }

        $arquivo = @fopen($this->arquivo, 'c+');

        if ($arquivo === false) {
            throw new RuntimeException('Não foi possível abrir o histórico de auditoria para gravação.');
        }

        try {
            if (! flock($arquivo, LOCK_EX)) {
                throw new RuntimeException('Não foi possível bloquear o histórico de auditoria para gravação.');
            }

            rewind($arquivo);
            $conteudoExistente = stream_get_contents($arquivo);
            if ($conteudoExistente === false) {
                throw new RuntimeException('Não foi possível ler o histórico de auditoria.');
            }

            $existentes = array_fill_keys(array_map(
                static fn (string $linha): string => hash('sha256', $linha),
                preg_split('/\r\n|\n|\r/', $conteudoExistente, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            ), true);
            $novas = [];

            foreach ($entradas as $entrada) {
                $sha256 = (string) $entrada['sha256'];
                if (isset($existentes[$sha256])) {
                    continue;
                }

                $existentes[$sha256] = true;
                $novas[] = (string) $entrada['linha'];
            }

            if ($novas === []) {
                return 0;
            }

            $separadorInicial = $conteudoExistente !== ''
                && ! str_ends_with($conteudoExistente, "\n")
                && ! str_ends_with($conteudoExistente, "\r")
                ? PHP_EOL
                : '';
            $conteudo = $separadorInicial . implode(PHP_EOL, $novas) . PHP_EOL;

            if (fseek($arquivo, 0, SEEK_END) !== 0) {
                throw new RuntimeException('Não foi possível posicionar o histórico de auditoria para gravação.');
            }

            $estadoInicial = fstat($arquivo);
            if ($estadoInicial === false) {
                throw new RuntimeException('Não foi possível verificar o histórico de auditoria.');
            }

            $tamanho = strlen($conteudo);
            $gravado = 0;

            while ($gravado < $tamanho) {
                $bytes = @fwrite($arquivo, substr($conteudo, $gravado));

                if ($bytes === false || $bytes === 0) {
                    @ftruncate($arquivo, $estadoInicial['size']);
                    throw new RuntimeException('Não foi possível gravar o histórico de auditoria completo.');
                }

                $gravado += $bytes;
            }

            if (! fflush($arquivo)) {
                @ftruncate($arquivo, $estadoInicial['size']);
                throw new RuntimeException('Não foi possível confirmar a gravação do histórico de auditoria.');
            }
        } finally {
            flock($arquivo, LOCK_UN);
            fclose($arquivo);
        }

        return count($novas);
    }
}
