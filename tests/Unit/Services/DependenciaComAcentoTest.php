<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CsvParserService;
use App\Services\ImportacaoService;
use PDO;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Dependências com acento precisam ser reconhecidas nas duas pontas:
 * a tela de cadastro grava "SALA DE REUNIÃO" (mb_strtoupper), e a planilha
 * traz "Sala de Reunião". strtoupper() do PHP não converte "ã", então a chave
 * de comparação ficava diferente e a dependência era duplicada ou ignorada.
 */
#[AllowMockObjectsWithoutExpectations]
final class DependenciaComAcentoTest extends TestCase
{
    public function testImportacaoReutilizaDependenciaComAcentoJaCadastrada(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE dependencias (id INTEGER PRIMARY KEY, comum_id INTEGER, descricao TEXT)');
        $pdo->exec("INSERT INTO dependencias (id, comum_id, descricao) VALUES (7, 10, 'SALA DE REUNIÃO')");

        $service = new ImportacaoService($pdo);
        $method = new ReflectionMethod($service, 'buscarOuCriarDependenciaCached');

        self::assertSame(7, $method->invoke($service, 'Sala de Reunião', 10));

        $total = (int) $pdo->query('SELECT COUNT(*) FROM dependencias')->fetchColumn();
        self::assertSame(1, $total, 'Não deve criar duplicata de uma dependência já cadastrada');
    }

    public function testExclusaoReconheceDependenciaComAcentoPresenteNaPlanilha(): void
    {
        $pdo = $this->createMock(PDO::class);
        $service = new CsvParserService($pdo);

        $linhaDaPlanilha = [
            'codigo' => '09-0565 / 0001',
            'tipo_bem_codigo' => '4',
            'bem' => 'CADEIRA',
            'complemento' => 'METALICA',
            'dependencia' => 'Sala de Reunião',
            'localidade' => 'BR 09-0565',
            'codigo_comum' => '09-0565',
            'nome_original' => '4 - CADEIRA METALICA',
            '_linha_original' => 2,
        ];

        $analise = (new ReflectionMethod($service, 'analisarTodasAsLinhas'))->invoke(
            $service,
            [$linhaDaPlanilha],
            ['09-0565' => 10],
            0,
            [],
            [],
            [],
        );

        // Produto que existe no banco, não está na planilha e está numa dependência
        // que a planilha também traz: deve ser sugerido para exclusão.
        $produtosDb = [
            'OUTRO-1' => [
                'id_produto' => 99,
                'codigo' => 'OUTRO-1',
                'bem' => 'MESA',
                'complemento' => '',
                'tipo_bem_codigo' => '7',
                'tipo_bem_descricao' => 'Mesa',
                'dependencia_descricao' => 'SALA DE REUNIÃO',
                'ativo' => 1,
            ],
        ];

        $exclusoes = (new ReflectionMethod($service, 'detectarExclusoes'))->invoke(
            $service,
            ['09-0565' => 10],
            [10 => $produtosDb],
            $analise['codigosPorComum'],
            $analise['depsPorComum'],
        );

        self::assertSame(1, $exclusoes['total'], 'Produto da dependência com acento deve ser sugerido para exclusão');
        self::assertSame('ex99', $exclusoes['registros'][0]['linha_csv']);
    }
}
