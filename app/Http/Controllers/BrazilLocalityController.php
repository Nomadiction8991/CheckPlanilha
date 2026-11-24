<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class BrazilLocalityController extends Controller
{
    private const PARTICULAS = ['de', 'da', 'do', 'das', 'dos', 'e'];

    public function states(Request $request): JsonResponse
    {
        foreach ($this->stateProviders() as $source => $url) {
            try {
                $response = Http::timeout(8)
                    ->acceptJson()
                    ->get($url);

                if (! $response->ok()) {
                    continue;
                }

                $states = $this->extractStates((array) $response->json());

                if ($states === []) {
                    continue;
                }

                return response()->json([
                    'success' => true,
                    'data' => $states,
                    'source' => $source,
                ]);
            } catch (Throwable) {
                continue;
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Não foi possível consultar os estados.',
            'data' => [],
        ], 502);
    }

    public function cities(Request $request, string $state): JsonResponse
    {
        $state = strtoupper(trim($state));

        if (!array_key_exists($state, (array) config('brazil.states', []))) {
            return response()->json([
                'success' => false,
                'message' => 'UF inválida.',
                'data' => [],
            ], 422);
        }

        foreach ($this->cityProviders($state) as $source => $url) {
            try {
                $response = Http::timeout(8)
                    ->acceptJson()
                    ->get($url);

                if (! $response->ok()) {
                    continue;
                }

                $cities = $this->extractCities((array) $response->json());

                if ($cities === []) {
                    continue;
                }

                return response()->json([
                    'success' => true,
                    'data' => $cities,
                    'source' => $source,
                ]);
            } catch (Throwable) {
                continue;
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Não foi possível consultar as cidades.',
            'data' => [],
        ], 502);
    }

    /**
     * @return array<string, string>
     */
    private function stateProviders(): array
    {
        return [
            'IBGE' => 'https://servicodados.ibge.gov.br/api/v1/localidades/estados',
            'BrasilAPI' => 'https://brasilapi.com.br/api/ibge/uf/v1',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     * @return array<int, array{sigla: string, nome: string}>
     */
    private function extractStates(array $payload): array
    {
        $states = [];

        foreach ($payload as $item) {
            if (! is_array($item)
                || ! is_string($item['sigla'] ?? null)
                || ! is_string($item['nome'] ?? null)) {
                continue;
            }

            $sigla = strtoupper(trim($item['sigla']));
            $nome = $this->normalizeCityName($item['nome']);

            if ($sigla === '' || $nome === '') {
                continue;
            }

            $states[] = [
                'sigla' => $sigla,
                'nome' => $nome,
            ];
        }

        usort($states, static fn (array $left, array $right): int => $left['sigla'] <=> $right['sigla']);

        return $states;
    }

    private function cityProviders(string $state): array
    {
        return [
            'IBGE' => "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$state}/municipios",
            'BrasilAPI' => "https://brasilapi.com.br/api/ibge/municipios/v1/{$state}",
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     * @return array<int, string>
     */
    private function extractCities(array $payload): array
    {
        $cities = [];

        foreach ($payload as $item) {
            if (! is_array($item) || ! is_string($item['nome'] ?? null)) {
                continue;
            }

            $name = $this->normalizeCityName($item['nome']);

            if ($name !== '') {
                $cities[] = $name;
            }
        }

        return $cities;
    }

    private function normalizeCityName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '') {
            return '';
        }

        // Separadores (espaço ou hífen) ficam no array, em posições ímpares, para remontar o nome igual.
        $partes = preg_split('/(\s+|-)/u', mb_convert_case($name, MB_CASE_TITLE, 'UTF-8'), -1, PREG_SPLIT_DELIM_CAPTURE)
            ?: [$name];

        $indicePalavra = 0;

        foreach ($partes as $indice => $parte) {
            if ($indice % 2 === 1 || $parte === '') {
                continue;
            }

            $minuscula = mb_strtolower($parte, 'UTF-8');

            if ($indicePalavra > 0 && in_array($minuscula, self::PARTICULAS, true)) {
                $partes[$indice] = $minuscula;
            }

            $indicePalavra++;
        }

        // "d'" com apóstrofo segue a grafia oficial: "d'Oeste", "d'Água" (não "D'oeste"),
        // inclusive depois de hífen: "Olhos-d'Água".
        $nome = implode('', $partes);

        // Aceita o apóstrofo ASCII (') e o tipográfico (’), que vem de algumas bases de nomes.
        $nome = preg_replace_callback(
            '/(?<=[\s-])D([\'’])(\p{L})/u',
            static fn (array $m): string => 'd' . $m[1] . mb_strtoupper($m[2], 'UTF-8'),
            $nome,
        ) ?? $nome;

        // Apóstrofo dentro de palavra ("Sant'Ana", "D'Ávila") mantém a letra seguinte maiúscula.
        return preg_replace_callback(
            '/(\p{L})([\'’])(\p{L})/u',
            static fn (array $m): string => $m[1] . $m[2] . mb_strtoupper($m[3], 'UTF-8'),
            $nome,
        ) ?? $nome;
    }
}
