<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Sync\DeviceRegistry;
use App\Sync\NotebookAgent;
use App\Sync\NotebookChangeApplier;
use App\Sync\SyncTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Recebimento: o notebook pede as alterações feitas em outros lugares,
 * e nunca recebe de volta as próprias.
 */
class SyncPullTest extends SincronizacaoTestCase
{
    private string $token;

    private int $aparelhoId;

    protected function setUp(): void
    {
        parent::setUp();

        $registro = app(DeviceRegistry::class)->registrar('Notebook da igreja', 7);
        $this->token = $registro['token'];
        $this->aparelhoId = (int) $registro['aparelho']->id;
    }

    public function test_recebimento_devolve_alteracoes_de_outras_origens_e_nao_as_proprias(): void
    {
        $this->registrar('local', 'produtos', '1');
        $this->registrar('aparelho:' . $this->aparelhoId, 'produtos', '2');
        $this->registrar('aparelho:99', 'produtos', '3');

        $resposta = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/sincronizacao/receber?depois=0');

        $resposta->assertOk();
        $origens = array_column($resposta->json('alteracoes'), 'origem');

        $this->assertSame(['local', 'aparelho:99'], $origens);
    }

    public function test_recebimento_informa_o_cursor_para_a_proxima_chamada(): void
    {
        $primeira = $this->registrar('local', 'produtos', '1');
        $segunda = $this->registrar('local', 'produtos', '2');

        $resposta = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/sincronizacao/receber?depois=0');

        $this->assertSame($segunda, $resposta->json('cursor'));
        $this->assertFalse($resposta->json('mais'));
        $this->assertLessThan($segunda, $primeira);
    }

    public function test_recebimento_pede_um_cursor_valido(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/sincronizacao/receber?depois=abc')
            ->assertUnprocessable();
    }

    public function test_aplicacao_no_notebook_recusa_chave_primaria_inconsistente(): void
    {
        Schema::create('produtos', function (Blueprint $table): void {
            $table->increments('id_produto');
            $table->string('codigo')->unique();
            $table->integer('comum_id')->nullable();
            $table->string('bem')->nullable();
            $table->boolean('checado')->default(false);
        });

        try {
            app(NotebookChangeApplier::class)->aplicarLote([[
                'uuid' => 'abababab-1111-4111-8111-111111111111',
                'tabela' => 'produtos',
                'chave' => '5',
                'operacao' => 'insert',
                'antes' => null,
                'depois' => [
                    'id_produto' => 6,
                    'codigo' => 'P-6',
                    'comum_id' => 1,
                    'bem' => 'Mesa',
                    'checado' => false,
                ],
            ]]);
            $this->fail('Era esperado RuntimeException para a chave inconsistente.');
        } catch (RuntimeException $erro) {
            $this->assertSame('A chave do evento não corresponde ao registro recebido.', $erro->getMessage());
        }

        $this->assertFalse(DB::table('produtos')->where('id_produto', 5)->exists());
        $this->assertFalse(DB::table('produtos')->where('id_produto', 6)->exists());
    }

    public function test_aplicacao_no_notebook_recusa_operacao_desconhecida_sem_alterar_o_registro(): void
    {
        Schema::create('produtos', function (Blueprint $table): void {
            $table->increments('id_produto');
            $table->string('codigo')->unique();
            $table->string('bem')->nullable();
        });
        DB::table('produtos')->insert(['id_produto' => 5, 'codigo' => 'P-5', 'bem' => 'Cadeira']);

        try {
            app(NotebookChangeApplier::class)->aplicarLote([[
                'uuid' => 'abababab-4444-4444-8444-444444444444',
                'tabela' => 'produtos',
                'chave' => '5',
                'operacao' => 'truncate',
                'antes' => ['id_produto' => 5, 'codigo' => 'P-5', 'bem' => 'Cadeira'],
                'depois' => ['id_produto' => 5, 'codigo' => 'P-5', 'bem' => 'Mesa'],
            ]]);
            $this->fail('Era esperado RuntimeException para uma operação desconhecida.');
        } catch (RuntimeException $erro) {
            $this->assertSame('Operação desconhecida: truncate.', $erro->getMessage());
        }

        $this->assertSame('Cadeira', DB::table('produtos')->where('id_produto', 5)->value('bem'));
    }

    public function test_aplicacao_no_notebook_preserva_mudanca_de_nulo_para_texto_vazio(): void
    {
        Schema::create('produtos', function (Blueprint $table): void {
            $table->increments('id_produto');
            $table->string('codigo')->unique();
            $table->string('bem')->nullable();
        });
        DB::table('produtos')->insert(['id_produto' => 5, 'codigo' => 'P-5', 'bem' => null]);

        app(NotebookChangeApplier::class)->aplicarLote([[
            'uuid' => 'abababab-2222-4222-8222-222222222222',
            'tabela' => 'produtos',
            'chave' => '5',
            'operacao' => 'update',
            'antes' => ['id_produto' => 5, 'codigo' => 'P-5', 'bem' => null],
            'depois' => ['id_produto' => 5, 'codigo' => 'P-5', 'bem' => ''],
        ]]);

        $this->assertSame('', DB::table('produtos')->where('id_produto', 5)->value('bem'));
    }

    public function test_aplicacao_no_notebook_recusa_exclusao_de_configuracao_sem_snapshot(): void
    {
        Schema::create('configuracoes', function (Blueprint $table): void {
            $table->string('mail_host')->nullable();
            $table->text('mail_password')->nullable();
        });
        DB::table('configuracoes')->insert(['mail_host' => 'smtp.teste.local']);

        try {
            app(NotebookChangeApplier::class)->aplicarLote([[
                'uuid' => 'abababab-3333-4333-8333-333333333333',
                'tabela' => 'configuracoes',
                'chave' => '*',
                'operacao' => 'delete',
                'antes' => null,
                'depois' => null,
            ]]);
            $this->fail('Era esperado RuntimeException para exclusão sem snapshot.');
        } catch (RuntimeException $erro) {
            $this->assertSame('A alteração de uma tabela sem chave exige o registro anterior.', $erro->getMessage());
        }

        $this->assertSame('smtp.teste.local', DB::table('configuracoes')->value('mail_host'));
    }

    public function test_falha_ao_gravar_cursor_reverte_alteracoes_incrementais_recebidas(): void
    {
        Schema::create('produtos', function (Blueprint $table): void {
            $table->unsignedInteger('id_produto')->primary();
            $table->string('codigo')->nullable();
            $table->string('bem')->nullable();
        });
        DB::table('sincronizacao_estado')->insert([
            'chave' => 'ultimo_recebido',
            'valor' => '0',
        ]);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER falhar_ao_atualizar_cursor_incremental
            BEFORE UPDATE ON sincronizacao_estado
            WHEN NEW.chave = 'ultimo_recebido'
            BEGIN
                SELECT RAISE(ABORT, 'falha simulada ao gravar cursor');
            END
            SQL);

        $transporte = new class implements SyncTransport
        {
            public function enviar(array $alteracoes): array
            {
                return ['erros' => []];
            }

            public function receber(int $depois): array
            {
                return [
                    'alteracoes' => [[
                        'uuid' => 'abababab-5555-4555-8555-555555555555',
                        'tabela' => 'produtos',
                        'chave' => '5',
                        'operacao' => 'insert',
                        'antes' => null,
                        'depois' => ['id_produto' => 5, 'codigo' => 'P-5', 'bem' => 'Mesa'],
                    ]],
                    'cursor' => 1,
                    'mais' => false,
                ];
            }
        };

        try {
            app(NotebookAgent::class)->executar($transporte);
            $this->fail('A falha de gravação do cursor precisava interromper o recebimento.');
        } catch (\Throwable $erro) {
            $this->assertStringContainsString('cursor', mb_strtolower($erro->getMessage(), 'UTF-8'));
        }

        $this->assertFalse(DB::table('produtos')->where('id_produto', 5)->exists());
        $this->assertSame('0', DB::table('sincronizacao_estado')->where('chave', 'ultimo_recebido')->value('valor'));
    }

    private function registrar(string $origem, string $tabela, string $chave): int
    {
        return (int) DB::table('sincronizacao_alteracoes')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tabela' => $tabela,
            'chave' => $chave,
            'operacao' => 'update',
            'antes' => json_encode(['bem' => 'Cadeira']),
            'depois' => json_encode(['bem' => 'Mesa']),
            'origem' => $origem,
            'criado_em' => now(),
        ]);
    }
}
