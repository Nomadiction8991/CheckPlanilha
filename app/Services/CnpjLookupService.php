<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CnpjLookupUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

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
        } catch (ConnectionException $exception) {
            throw new CnpjLookupUnavailableException(previous: $exception);
        }

        if ($response->notFound()) {
            return null;
        }

        if (! $response->successful()) {
            throw new CnpjLookupUnavailableException();
        }

        try {
            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CnpjLookupUnavailableException(previous: $exception);
        }

        if (! is_array($payload)) {
            throw new CnpjLookupUnavailableException();
        }

        $name = $this->textField($payload['razao_social'] ?? null);

        if ($name === '') {
            $name = $this->textField($payload['nome_fantasia'] ?? null);
        }

        if ($name === '') {
            throw new CnpjLookupUnavailableException();
        }

        return [
            'nome' => $name,
            'cidade' => $this->textField($payload['municipio'] ?? null),
        ];
    }

    private function textField(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
