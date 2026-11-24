<?php

declare(strict_types=1);

namespace Tests\Unit\Sync;

use App\Sync\ArquivoCorrompidoException;
use App\Sync\ConflitoDeArquivoException;
use App\Sync\SpreadsheetStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pasta das planilhas enviadas: cada arquivo só entra se o SHA-256 conferir.
 */
class SpreadsheetStoreTest extends TestCase
{
    private string $pasta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pasta = sys_get_temp_dir() . '/cp-planilhas-' . uniqid();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->pasta));

        parent::tearDown();
    }

    public function test_grava_o_arquivo_e_lista_com_o_sha256(): void
    {
        $loja = new SpreadsheetStore($this->pasta);

        $this->assertSame('gravado', $loja->gravar('importacao_1_100.csv', 'codigo;bem', hash('sha256', 'codigo;bem')));

        $this->assertSame(
            [['nome' => 'importacao_1_100.csv', 'sha256' => hash('sha256', 'codigo;bem'), 'tamanho' => 10]],
            $loja->listar(),
        );
    }

    public function test_recusa_conteudo_que_nao_confere_com_o_sha256_e_nao_grava(): void
    {
        $loja = new SpreadsheetStore($this->pasta);

        try {
            $loja->gravar('importacao_1_200.csv', 'conteudo alterado', hash('sha256', 'conteudo original'));
            $this->fail('Arquivo corrompido precisava ser recusado.');
        } catch (ArquivoCorrompidoException) {
            $this->assertFileDoesNotExist($this->pasta . '/importacao_1_200.csv');
            $this->assertSame([], $loja->listar());
        }
    }

    public function test_arquivo_igual_nao_e_gravado_de_novo(): void
    {
        $loja = new SpreadsheetStore($this->pasta);
        $sha = hash('sha256', 'dados');

        $this->assertSame('gravado', $loja->gravar('a.csv', 'dados', $sha));
        $this->assertSame('ja_existe', $loja->gravar('a.csv', 'dados', $sha));
    }

    public function test_mesmo_nome_com_conteudo_diferente_vira_conflito_sem_sobrescrever(): void
    {
        $loja = new SpreadsheetStore($this->pasta);
        $loja->gravar('a.csv', 'versao um', hash('sha256', 'versao um'));

        try {
            $loja->gravar('a.csv', 'versao dois', hash('sha256', 'versao dois'));
            $this->fail('Conteúdo diferente com o mesmo nome precisa ser recusado.');
        } catch (ConflitoDeArquivoException) {
            $this->assertSame('versao um', file_get_contents($this->pasta . '/a.csv'));
        }
    }

    public function test_gravacoes_concorrentes_com_mesmo_nome_nao_substituem_o_arquivo(): void
    {
        $loja = new SpreadsheetStore($this->pasta);
        $processos = [];
        $sinalizacao = $this->pasta . '/.inicio';
        $resultados = [];
        $conteudos = [str_repeat('a', 2_000_000), str_repeat('b', 2_000_000)];

        if (! mkdir($this->pasta, 0775, true) && ! is_dir($this->pasta)) {
            $this->fail('Não foi possível criar a pasta temporária do teste.');
        }

        foreach ($conteudos as $indice => $conteudo) {
            $resultado = $this->pasta . '/.resultado-' . $indice;
            $payload = $this->pasta . '/.payload-' . $indice;
            file_put_contents($payload, $conteudo);
            $codigo = <<<'PHP'
                require $argv[1] . '/vendor/autoload.php';
                while (! is_file($argv[2])) { usleep(1000); }
                $conteudo = file_get_contents($argv[4]);
                try {
                    $status = (new \App\Sync\SpreadsheetStore($argv[3]))->gravar('concorrente.csv', $conteudo, hash('sha256', $conteudo));
                } catch (\App\Sync\ConflitoDeArquivoException) {
                    $status = 'conflito';
                } catch (\Throwable $erro) {
                    file_put_contents($argv[5], 'erro:' . $erro->getMessage());
                    exit(1);
                }
                file_put_contents($argv[5], $status);
                PHP;
            $comando = [PHP_BINARY, '-r', $codigo, dirname(__DIR__, 3), $sinalizacao, $this->pasta, $payload, $resultado];
            $processos[$indice] = proc_open($comando, [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ], $pipes);
            $resultados[$indice] = $resultado;
            $this->assertIsResource($processos[$indice]);
        }

        touch($sinalizacao);

        foreach ($processos as $indice => $processo) {
            proc_close($processo);
            $this->assertFileExists($resultados[$indice]);
        }

        $status = array_map(static fn (string $path): string => (string) file_get_contents($path), $resultados);
        sort($status);

        $this->assertSame(['conflito', 'gravado'], $status);
        $this->assertContains(file_get_contents($this->pasta . '/concorrente.csv'), $conteudos);
    }

    public function test_nome_com_caminho_ou_caractere_estranho_e_recusado(): void
    {
        $loja = new SpreadsheetStore($this->pasta);

        $this->expectException(InvalidArgumentException::class);

        $loja->gravar('../fora.csv', 'x', hash('sha256', 'x'));
    }

    public function test_nao_lista_nem_le_arquivo_que_seja_link_simbolico(): void
    {
        mkdir($this->pasta, 0775, true);
        $arquivoExterno = $this->pasta . '-privado.txt';
        file_put_contents($arquivoExterno, 'conteudo privado');
        symlink($arquivoExterno, $this->pasta . '/alias.csv');

        $loja = new SpreadsheetStore($this->pasta);

        $this->assertSame([], $loja->listar());
        $this->expectException(\RuntimeException::class);
        $loja->ler('alias.csv');
    }

    public function test_recusa_listar_e_ler_planilha_sem_permissao_de_leitura(): void
    {
        mkdir($this->pasta, 0775, true);
        $caminho = $this->pasta . '/privada.csv';
        file_put_contents($caminho, 'codigo;bem');
        chmod($caminho, 0000);

        try {
            $loja = new SpreadsheetStore($this->pasta);

            $this->expectException(\RuntimeException::class);
            $loja->listar();
        } finally {
            chmod($caminho, 0644);
        }
    }

    public function test_nao_entrega_conteudo_vazio_quando_a_planilha_nao_pode_ser_lida(): void
    {
        mkdir($this->pasta, 0775, true);
        $caminho = $this->pasta . '/privada.csv';
        file_put_contents($caminho, 'codigo;bem');
        chmod($caminho, 0000);

        try {
            $this->expectException(\RuntimeException::class);
            (new SpreadsheetStore($this->pasta))->ler('privada.csv');
        } finally {
            chmod($caminho, 0644);
        }
    }

    public function test_nao_lista_planilhas_quando_a_pasta_configurada_e_link_simbolico(): void
    {
        $pastaExterna = $this->pasta . '-externa';
        mkdir($pastaExterna, 0775, true);
        file_put_contents($pastaExterna . '/planilha.csv', 'conteudo externo');
        symlink($pastaExterna, $this->pasta);

        $this->assertSame([], (new SpreadsheetStore($this->pasta))->listar());
    }

    public function test_nao_le_planilhas_quando_um_diretorio_ancestral_e_link_simbolico(): void
    {
        $pastaReal = $this->pasta . '/real/importacao';
        mkdir($pastaReal, 0775, true);
        file_put_contents($pastaReal . '/privada.csv', 'conteudo privado');
        symlink($this->pasta . '/real', $this->pasta . '/atalho');
        $loja = new SpreadsheetStore($this->pasta . '/atalho/importacao');

        $this->assertSame([], $loja->listar());
        $this->expectException(\RuntimeException::class);
        $loja->ler('privada.csv');
    }

    public function test_nao_grava_planilhas_quando_um_diretorio_ancestral_e_link_simbolico(): void
    {
        $pastaReal = $this->pasta . '/real';
        mkdir($pastaReal, 0775, true);
        symlink($pastaReal, $this->pasta . '/atalho');
        $loja = new SpreadsheetStore($this->pasta . '/atalho/importacao');

        $this->expectException(\RuntimeException::class);
        $loja->gravar('arquivo.csv', 'conteudo', hash('sha256', 'conteudo'));
    }

    public function test_nao_grava_sobre_arquivo_que_seja_link_simbolico(): void
    {
        mkdir($this->pasta, 0775, true);
        $arquivoExterno = $this->pasta . '-privado.txt';
        file_put_contents($arquivoExterno, 'conteudo privado');
        symlink($arquivoExterno, $this->pasta . '/alias.csv');

        try {
            (new SpreadsheetStore($this->pasta))->gravar(
                'alias.csv',
                'novo conteudo',
                hash('sha256', 'novo conteudo'),
            );
            $this->fail('O destino simbólico deveria ser recusado.');
        } catch (\RuntimeException $erro) {
            $this->assertSame('conteudo privado', file_get_contents($arquivoExterno));
        }
    }
}
