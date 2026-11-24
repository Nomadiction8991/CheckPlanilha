<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\LegacyAdministrationManagementServiceInterface;
use App\DTO\AdministrationMutationData;
use App\Models\Legacy\Administracao;
use App\Models\Legacy\Usuario;
use App\Support\LegacyCnpjValidator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyAdministrationManagementService implements LegacyAdministrationManagementServiceInterface
{
    public function create(AdministrationMutationData $data): Administracao
    {
        try {
            $cnpj = LegacyCnpjValidator::validate($data->cnpj);
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException('CNPJ inválido: '.$exception->getMessage());
        }

        return DB::transaction(function () use ($data, $cnpj): Administracao {
            $administration = Administracao::query()->create([
                'descricao' => $this->normalizeDescription($data->description),
                'cnpj' => $cnpj,
                'estado' => strtoupper(trim($data->state)),
                'cidade' => mb_strtoupper(trim($data->city), 'UTF-8'),
            ]);
            $this->syncAdministratorAccess();

            return $administration;
        });
    }

    public function update(Administracao $administration, AdministrationMutationData $data): Administracao
    {
        try {
            $cnpj = LegacyCnpjValidator::validate($data->cnpj);
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException('CNPJ inválido: '.$exception->getMessage());
        }

        return DB::transaction(function () use ($administration, $data, $cnpj): Administracao {
            $administration->fill([
                'descricao' => $this->normalizeDescription($data->description),
                'cnpj' => $cnpj,
                'estado' => strtoupper(trim($data->state)),
                'cidade' => mb_strtoupper(trim($data->city), 'UTF-8'),
            ]);
            $administration->save();
            $this->syncAdministratorAccess();

            return $administration->refresh();
        });
    }

    public function delete(Administracao $administration): void
    {
        try {
            DB::transaction(function () use ($administration): void {
                $lockedAdministration = Administracao::query()
                    ->whereKey($administration->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $administrationId = (int) $lockedAdministration->getKey();

                if (DB::table('comums')->where('administracao_id', $administrationId)->exists()) {
                    throw new RuntimeException('Esta administração não pode ser excluída porque já está vinculada a igrejas.');
                }

                if (DB::table('usuarios')->where('administracao_id', $administrationId)->exists()) {
                    throw new RuntimeException('Esta administração não pode ser excluída porque já está vinculada a usuários.');
                }

                if (DB::table('importacoes')->where('administracao_id', $administrationId)->exists()) {
                    throw new RuntimeException('Esta administração não pode ser excluída porque já está vinculada a importações.');
                }

                if (DB::table('tipos_bens')->where('administracao_id', $administrationId)->exists()) {
                    throw new RuntimeException('Esta administração não pode ser excluída porque já está vinculada a tipos de bens.');
                }

                $lockedAdministration->delete();
            });
        } catch (QueryException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'foreign key constraint')) {
                throw $exception;
            }

            throw new RuntimeException(
                'Esta administração não pode ser excluída porque há registros vinculados.',
                previous: $exception,
            );
        }
    }

    private function normalizeDescription(string $description): string
    {
        return trim($description);
    }

    private function syncAdministratorAccess(): void
    {
        $administrationIds = Administracao::query()
            ->orderBy('descricao')
            ->pluck('id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        if ($administrationIds === []) {
            return;
        }

        Usuario::query()
            ->where(function ($query): void {
                $query
                    ->whereKey(1)
                    ->orWhere('tipo', 'administrador')
                    ->orWhereRaw('UPPER(email) = ?', ['ADMIN@LOCALHOST']);
            })
            ->get()
            ->each(function (Usuario $user) use ($administrationIds): void {
                $user->forceFill([
                    'administracoes_permitidas' => $administrationIds,
                ])->save();
            });
    }
}
