<?php

declare(strict_types=1);

namespace Tests\Unit\Sync;

use App\Sync\AuditLog;
use InvalidArgumentException;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Histórico de auditoria (uma linha JSON por evento): a mescla só acrescenta o que falta.
 */
class AuditLogTest extends TestCase
{
    private string $arquivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arquivo = sys_get_temp_dir() . '/cp-auditoria-' . uniqid() . '/audit-log.jsonl';
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg(dirname($this->arquivo)));

        parent::tearDown();
    }

    public function test_acrescenta_so_as_linhas_que_ainda_nao_existem(): void
    {
        $log = new AuditLog($this->arquivo);
        $a = '{"acao":"login","usuario":1}';
        $b = '{"acao":"importou","usuario":2}';
        $log->acrescentar([$this->entrada($a)]);

        $recebidas = $log->acrescentar([$this->entrada($a), $this->entrada($b)]);

        $this->assertSame(1, $recebidas);
        $this->assertSame([$a, $b], array_map(fn (array $e): string => $e['linha'], $log->linhas()));
    }

    public function test_acrescenta_em_arquivo_existente_sem_quebra_de_linha_final(): void
    {
        mkdir(dirname($this->arquivo), 0777, true);
        $a = '{"acao":"legado"}';
        $b = '{"acao":"novo"}';
        file_put_contents($this->arquivo, $a);

        $log = new AuditLog($this->arquivo);
        $this->assertSame(1, $log->acrescentar([$this->entrada($b)]));

        $this->assertSame([$a, $b], array_map(fn (array $e): string => $e['linha'], $log->linhas()));
    }

    public function test_recusa_o_lote_inteiro_se_uma_linha_nao_confere_com_o_sha256(): void
    {
        $log = new AuditLog($this->arquivo);
        $log->acrescentar([$this->entrada('{"acao":"login"}')]);

        try {
            $log->acrescentar([
                $this->entrada('{"acao":"novo"}'),
                ['sha256' => str_repeat('0', 64), 'linha' => '{"acao":"adulterado"}'],
            ]);
            $this->fail('Linha corrompida precisava derrubar o lote.');
        } catch (InvalidArgumentException) {
            $this->assertCount(1, $log->linhas(), 'Nenhuma linha do lote pode entrar.');
        }
    }

    public function test_recusa_linha_que_nao_e_json(): void
    {
        $log = new AuditLog($this->arquivo);

        $this->expectException(InvalidArgumentException::class);

        $log->acrescentar([$this->entrada('isto nao e json')]);
    }

    public function test_duas_gravacoes_concorrentes_nao_duplicam_eventos(): void
    {
        $diretorio = dirname($this->arquivo);
        mkdir($diretorio, 0777, true);

        $sinalizacao = $diretorio . '/.inicio';
        $conteudo = [];
        for ($indice = 0; $indice < 10_000; $indice++) {
            $linha = json_encode(['acao' => 'concorrente', 'evento' => $indice], JSON_THROW_ON_ERROR);
            $conteudo[] = $this->entrada($linha);
        }

        $codigo = <<<'PHP'
            require $argv[1] . '/vendor/autoload.php';
            $entradas = json_decode(file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
            while (! is_file($argv[2])) { usleep(1000); }
            $recebidas = (new \App\Sync\AuditLog($argv[3]))->acrescentar($entradas);
            file_put_contents($argv[5], (string) $recebidas);
            PHP;

        $arquivoEntradas = $diretorio . '/entradas.json';
        file_put_contents($arquivoEntradas, json_encode($conteudo, JSON_THROW_ON_ERROR));
        $processos = [];
        $resultados = [];

        for ($indice = 0; $indice < 2; $indice++) {
            $resultado = $diretorio . '/resultado-' . $indice;
            $processo = proc_open([
                PHP_BINARY,
                '-r',
                $codigo,
                dirname(__DIR__, 3),
                $sinalizacao,
                $this->arquivo,
                $arquivoEntradas,
                $resultado,
            ], [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ], $pipes);

            $this->assertIsResource($processo);
            $processos[] = $processo;
            $resultados[] = $resultado;
        }

        touch($sinalizacao);

        foreach ($processos as $processo) {
            $this->assertSame(0, proc_close($processo));
        }

        $this->assertSame(10_000, count((new AuditLog($this->arquivo))->linhas()));
        $this->assertSame(10_000, array_sum(array_map(
            static fn (string $resultado): int => (int) file_get_contents($resultado),
            $resultados,
        )));
    }

    public function test_nao_confirma_linhas_quando_o_arquivo_de_auditoria_nao_pode_ser_escrito(): void
    {
        mkdir($this->arquivo, 0777, true);
        $log = new AuditLog($this->arquivo);

        set_error_handler(static fn (): bool => true);
        try {
            $this->expectException(RuntimeException::class);
            $log->acrescentar([$this->entrada('{"acao":"login"}')]);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return array{sha256: string, linha: string}
     */
    private function entrada(string $linha): array
    {
        return ['sha256' => hash('sha256', $linha), 'linha' => $linha];
    }
}
