<?php

declare(strict_types=1);

namespace App\Sync;

use InvalidArgumentException;
use RuntimeException;

/**
 * Pasta das planilhas enviadas (storage/importacao). Cada arquivo só entra se o SHA-256 conferir,
 * e um arquivo que já existe com o mesmo conteúdo nunca é gravado de novo.
 */
final class SpreadsheetStore
{
    private const NOME_VALIDO = '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,190}$/';

    public function __construct(
        private readonly string $pasta,
    ) {
    }

    /**
     * @return list<array{nome: string, sha256: string, tamanho: int}>
     */
    public function listar(): array
    {
        if (! is_dir($this->pasta)) {
            return [];
        }

        $arquivos = [];

        foreach (scandir($this->pasta) ?: [] as $nome) {
            if (preg_match(self::NOME_VALIDO, $nome) !== 1 || ! is_file($this->caminho($nome))) {
                continue;
            }

            $arquivos[] = [
                'nome' => $nome,
                'sha256' => (string) hash_file('sha256', $this->caminho($nome)),
                'tamanho' => (int) filesize($this->caminho($nome)),
            ];
        }

        usort($arquivos, fn (array $a, array $b): int => strcmp($a['nome'], $b['nome']));

        return $arquivos;
    }

    /**
     * @return array{conteudo: string, sha256: string}
     */
    public function ler(string $nome): array
    {
        $this->validarNome($nome);

        if (! is_file($this->caminho($nome))) {
            throw new RuntimeException("A planilha {$nome} não existe neste computador.");
        }

        $conteudo = (string) file_get_contents($this->caminho($nome));

        return ['conteudo' => $conteudo, 'sha256' => hash('sha256', $conteudo)];
    }

    /**
     * Grava o arquivo se o SHA-256 conferir. Devolve 'gravado' ou 'ja_existe'.
     */
    public function gravar(string $nome, string $conteudo, string $sha256): string
    {
        $this->validarNome($nome);

        if (hash('sha256', $conteudo) !== $sha256) {
            throw new ArquivoCorrompidoException(
                "A planilha {$nome} chegou com conteúdo diferente do esperado. Envie de novo.",
            );
        }

        $caminho = $this->caminho($nome);

        if (is_file($caminho)) {
            if (hash_file('sha256', $caminho) === $sha256) {
                return 'ja_existe';
            }

            throw new ConflitoDeArquivoException("Já existe uma planilha {$nome} com conteúdo diferente.");
        }

        if (! is_dir($this->pasta) && ! mkdir($this->pasta, 0775, true) && ! is_dir($this->pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta das planilhas.');
        }

        // Serializa as decisões para este nome: rename() sozinho pode substituir o destino
        // quando duas instâncias passam juntas pela verificação de existência.
        $bloqueio = @fopen($this->pasta . DIRECTORY_SEPARATOR . '.' . $nome . '.lock', 'c');

        if ($bloqueio === false || ! flock($bloqueio, LOCK_EX)) {
            if (is_resource($bloqueio)) {
                fclose($bloqueio);
            }

            throw new RuntimeException('Não foi possível bloquear a gravação da planilha.');
        }

        $temporario = null;

        try {
            if (is_file($caminho)) {
                if (hash_file('sha256', $caminho) === $sha256) {
                    return 'ja_existe';
                }

                throw new ConflitoDeArquivoException("Já existe uma planilha {$nome} com conteúdo diferente.");
            }

            // Nome único para que gravações simultâneas de arquivos diferentes não compartilhem o temporário.
            $temporario = tempnam($this->pasta, '.' . $nome . '.');

            if ($temporario === false || file_put_contents($temporario, $conteudo, LOCK_EX) === false || ! rename($temporario, $caminho)) {
                throw new RuntimeException("Não foi possível gravar a planilha {$nome}.");
            }

            return 'gravado';
        } finally {
            if ($temporario !== null && is_file($temporario)) {
                @unlink($temporario);
            }

            flock($bloqueio, LOCK_UN);
            fclose($bloqueio);
        }
    }

    private function caminho(string $nome): string
    {
        return $this->pasta . DIRECTORY_SEPARATOR . $nome;
    }

    private function validarNome(string $nome): void
    {
        if (preg_match(self::NOME_VALIDO, $nome) !== 1) {
            throw new InvalidArgumentException('Nome de planilha inválido.');
        }
    }
}
