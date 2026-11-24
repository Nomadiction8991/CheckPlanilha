<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\LegacyMailConfigurationServiceInterface;
use App\Services\LegacyPasswordRecoveryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class LegacyPasswordRecoveryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('usuarios');
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('email');
            $table->string('senha');
            $table->unsignedTinyInteger('ativo')->default(1);
            $table->string('cpf')->nullable();
            $table->string('telefone')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('usuarios');
        parent::tearDown();
    }

    public function testRecoveryRejectsNondigitPhoneWhenStoredPhoneIsEmpty(): void
    {
        $originalHash = Hash::make('senha-original');
        DB::table('usuarios')->insert([
            'nome' => 'Maria',
            'email' => 'maria@example.test',
            'senha' => $originalHash,
            'ativo' => 1,
            'cpf' => '52998224725',
            'telefone' => null,
        ]);
        Mail::fake();

        $service = new LegacyPasswordRecoveryService(
            $this->createStub(LegacyMailConfigurationServiceInterface::class),
        );

        try {
            $service->recover('529.982.247-25', 'abc', 'maria@example.test');
            self::fail('A recuperação não deve aceitar telefone sem dígitos.');
        } catch (RuntimeException $exception) {
            self::assertSame('Dados não conferem com o cadastro informado.', $exception->getMessage());
        }

        self::assertSame($originalHash, DB::table('usuarios')->value('senha'));
        Mail::assertNothingOutgoing();
    }

    public function testMailFailureDoesNotOverwritePasswordChangedConcurrently(): void
    {
        $originalHash = Hash::make('senha-original');
        $concurrentHash = Hash::make('senha-definida-em-outra-sessao');
        $userId = DB::table('usuarios')->insertGetId([
            'nome' => 'Maria',
            'email' => 'maria@example.test',
            'senha' => $originalHash,
            'ativo' => 1,
            'cpf' => '52998224725',
            'telefone' => '65999999999',
        ]);

        $pendingMail = Mockery::mock(PendingMail::class);
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('to')->once()->with('maria@example.test')->andReturn($pendingMail);
        $pendingMail->shouldReceive('send')->once()->andReturnUsing(
            static function () use ($userId, $concurrentHash): never {
                DB::table('usuarios')->where('id', $userId)->update(['senha' => $concurrentHash]);
                throw new RuntimeException('Falha simulada no envio.');
            },
        );
        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);

        $service = new LegacyPasswordRecoveryService(
            $this->createStub(LegacyMailConfigurationServiceInterface::class),
        );

        try {
            $service->recover('529.982.247-25', '(65) 99999-9999', 'maria@example.test');
            self::fail('A falha de envio deve ser reportada.');
        } catch (RuntimeException $exception) {
            self::assertSame('Não foi possível enviar a nova senha agora. Tente novamente mais tarde.', $exception->getMessage());
        }

        self::assertSame($concurrentHash, DB::table('usuarios')->where('id', $userId)->value('senha'));
    }
}
