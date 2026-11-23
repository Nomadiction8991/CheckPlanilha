<?php

declare(strict_types=1);

namespace Tests\Feature\Transfers;

use App\Contracts\LegacyAuthSessionServiceInterface;
use App\Contracts\LegacyPermissionServiceInterface;
use App\Services\LegacyReportService;
use App\Services\LegacyReportTemplateService;
use App\Services\LegacyTransferService;
use Illuminate\Support\Facades\DB;
use Mockery;

final class RelatorioAlteracoesTransferenciasTest extends TransferenciasTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->criarAdministracao(1, 'Administração Cuiabá');
        $this->criarIgreja(7, '12-3456', 'Central Cuiabá');
        $this->criarIgreja(8, '35-0001', 'Central São Paulo');
        $this->criarDependencia(70, 7, 'Salão');
        $this->criarDependencia(80, 8, 'Sala 2');
        $this->criarTipoBem(1, 'Cadeira');
        $this->criarBem(1001, 7, 'A-100', 'Cadeira de madeira', 70, 150.0);

        (new LegacyTransferService())->transferir(7, 8, 80, [1001], 9);
    }

    public function testRelatorioDeAlteracoesTrazAsTransferenciasDaIgreja(): void
    {
        $relatorio = $this->relatorioDaIgreja(8);

        $this->assertCount(1, $relatorio['transferencias']);
        $this->assertSame('entrada', $relatorio['transferencias'][0]['tipo']);
        $this->assertSame(1, $relatorio['resumo']['total_transferidos']);
    }

    public function testTelaDeAlteracoesExibeASecaoDeTransferencias(): void
    {
        $html = view('reports.position', [
            'churches' => collect(),
            'selectedChurchId' => 8,
            'report' => $this->relatorioDaIgreja(8),
        ])->render();

        $this->assertStringContainsString('Transferências', $html);
        $this->assertStringContainsString('12-3456 - Central Cuiabá', $html);
        $this->assertStringContainsString('A-100', $html);
        $this->assertStringContainsString('Pendente', $html);
    }

    public function testFiltroDeDependenciaMantemBemEditadoSemDependenciaEditada(): void
    {
        $this->criarBem(1002, 7, 'A-101', 'Mesa', 70, 150.0);
        DB::table('produtos')->where('id_produto', 1002)->update([
            'editado' => 1,
            'editado_bem' => 'Mesa atualizada',
            'editado_dependencia_id' => null,
        ]);

        $auth = Mockery::mock(LegacyAuthSessionServiceInterface::class)->shouldIgnoreMissing();
        $permissions = Mockery::mock(LegacyPermissionServiceInterface::class)->shouldIgnoreMissing();
        $service = new LegacyReportService(new LegacyReportTemplateService(), $auth, $permissions);

        $report = $service->buildChangeHistory(7, ['dependencia' => 70]);

        $this->assertSame(1, $report['resumo']['total_geral']);
        $this->assertSame('A-101', $report['secoes']['alteracoes']['itens'][0]['codigo']);
    }

    public function testFiltroDeDependenciaUsaLocalEditadoQuandoEleExiste(): void
    {
        $this->criarDependencia(71, 7, 'Sala nova');
        $this->criarBem(1002, 7, 'A-101', 'Mesa', 70, 150.0);
        DB::table('produtos')->where('id_produto', 1002)->update([
            'editado' => 1,
            'editado_bem' => 'Mesa atualizada',
            'editado_dependencia_id' => 71,
        ]);

        $auth = Mockery::mock(LegacyAuthSessionServiceInterface::class)->shouldIgnoreMissing();
        $permissions = Mockery::mock(LegacyPermissionServiceInterface::class)->shouldIgnoreMissing();
        $service = new LegacyReportService(new LegacyReportTemplateService(), $auth, $permissions);

        $this->assertSame(0, $service->buildChangeHistory(7, ['dependencia' => 70])['resumo']['total_geral']);
        $this->assertSame(1, $service->buildChangeHistory(7, ['dependencia' => 71])['resumo']['total_geral']);
    }

    public function testPosicaoDeEstoqueMostraDependenciaEditadaEUsaOriginalComoFallback(): void
    {
        $this->criarDependencia(71, 7, 'Sala nova');
        $this->criarBem(1002, 7, 'A-101', 'Mesa', 70, 150.0);
        DB::table('produtos')->where('id_produto', 1002)->update([
            'editado' => 1,
            'editado_bem' => 'Mesa atualizada',
            'editado_dependencia_id' => 71,
        ]);

        $relatorio = $this->relatorioDaIgreja(7);
        $itemEditado = collect($relatorio['itens'])->firstWhere('codigo', 'A-101');

        $this->assertSame('Sala nova', $itemEditado['dependencia']);
        $this->assertStringContainsString('Sala nova', $relatorio['backup']['content']);

        DB::table('produtos')->where('id_produto', 1002)->update(['editado' => 0]);

        $relatorioSemEdicao = $this->relatorioDaIgreja(7);
        $itemOriginal = collect($relatorioSemEdicao['itens'])->firstWhere('codigo', 'A-101');

        $this->assertSame('Salão', $itemOriginal['dependencia']);
    }

    /**
     * @return array<string, mixed>
     */
    private function relatorioDaIgreja(int $comumId): array
    {
        $auth = Mockery::mock(LegacyAuthSessionServiceInterface::class)->shouldIgnoreMissing();
        $permissions = Mockery::mock(LegacyPermissionServiceInterface::class)->shouldIgnoreMissing();
        $servico = new LegacyReportService(new LegacyReportTemplateService(), $auth, $permissions);

        return $servico->buildVerificationPositionReport($comumId);
    }
}
