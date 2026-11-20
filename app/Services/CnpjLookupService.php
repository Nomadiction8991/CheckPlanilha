<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CnpjLookupService
{
    /**
     * @return array{nome: string, cidade: string}|null
     */
    public function lookup(string $cnpj): ?array
    {
        try {
            /** @var Response $response */
            $response = Http::timeout(5)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'CheckPlanilha/1.0',
                ])
                ->get(sprintf('https://www.cnpj.dev/api/v1/cnpj/%s', $cnpj));

            if ($response->status() < 200 || $response->status() >= 300) {
                return null;
            }

            $payload = json_decode($response->body(), true);

            if (! is_array($payload)) {
                return null;
            }

            $name = $this->textField($payload['razao_social'] ?? null);

            if ($name === '') {
                $name = $this->textField($payload['nome_fantasia'] ?? null);
            }

            if ($name === '') {
                return null;
            }

            return [
                'nome' => $name,
                'cidade' => $this->textField($payload['municipio'] ?? null),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function textField(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
