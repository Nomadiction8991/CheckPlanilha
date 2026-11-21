<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transferencia_itens', function (Blueprint $table): void {
            $table->text('descricao')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('transferencia_itens')->whereRaw('LENGTH(descricao) > 255')->exists()) {
            throw new RuntimeException('Não é possível reduzir a descrição das transferências: existem registros com mais de 255 bytes.');
        }

        Schema::table('transferencia_itens', function (Blueprint $table): void {
            $table->string('descricao', 255)->nullable()->change();
        });
    }
};
