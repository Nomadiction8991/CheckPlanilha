<?php

declare(strict_types=1);

use App\Sync\TriggerGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Os gatilhos gerais foram instalados antes da migração das tabelas de transferência.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            app(TriggerGenerator::class)->generateFor(['transferencias', 'transferencia_itens']);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            app(TriggerGenerator::class)->dropFor(['transferencias', 'transferencia_itens']);
        }
    }
};
