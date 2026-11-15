<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class BrazilLocalityControllerTest extends TestCase
{
    public function testStatesReturnsNormalizedPayload(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados' => Http::response([
                ['sigla' => 'MT', 'nome' => 'Mato Grosso'],
                ['sigla' => 'SP', 'nome' => 'São Paulo'],
            ]),
            'https://brasilapi.com.br/api/ibge/uf/v1' => Http::response([
                ['sigla' => 'RJ', 'nome' => 'Rio de Janeiro'],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.states'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                ['sigla' => 'MT', 'nome' => 'Mato Grosso'],
                ['sigla' => 'SP', 'nome' => 'São Paulo'],
            ],
            'source' => 'IBGE',
        ]);
    }

    public function testStatesFallsBackToBrasilApiWhenIbgeFails(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados' => Http::response([], 503),
            'https://brasilapi.com.br/api/ibge/uf/v1' => Http::response([
                ['sigla' => 'MT', 'nome' => 'Mato Grosso'],
                ['sigla' => 'SP', 'nome' => 'São Paulo'],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.states'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                ['sigla' => 'MT', 'nome' => 'Mato Grosso'],
                ['sigla' => 'SP', 'nome' => 'São Paulo'],
            ],
            'source' => 'BrasilAPI',
        ]);
    }

    public function testCitiesReturnsNormalizedPayload(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/MT/municipios' => Http::response([
                ['nome' => 'Cuiabá'],
                ['nome' => 'Várzea Grande'],
            ]),
            'https://brasilapi.com.br/api/ibge/municipios/v1/MT' => Http::response([
                ['nome' => 'CUIABÁ'],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.cities', ['state' => 'MT']));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => ['Cuiabá', 'Várzea Grande'],
            'source' => 'IBGE',
        ]);
    }

    public function testCitiesFallsBackToBrasilApiWhenIbgeFails(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/MT/municipios' => Http::response([], 503),
            'https://brasilapi.com.br/api/ibge/municipios/v1/MT' => Http::response([
                ['nome' => 'CUIABÁ'],
                ['nome' => 'VÁRZEA GRANDE'],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.cities', ['state' => 'MT']));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => ['Cuiabá', 'Várzea Grande'],
            'source' => 'BrasilAPI',
        ]);
    }

    public function testStatesKeepsPortugueseParticlesLowercase(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados' => Http::response([
                ['sigla' => 'MS', 'nome' => 'MATO GROSSO DO SUL'],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.states'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                ['sigla' => 'MS', 'nome' => 'Mato Grosso do Sul'],
            ],
        ]);
    }

    public function testCitiesKeepsPortugueseParticlesLowercase(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/MT/municipios' => Http::response([
                ['nome' => 'SÃO JOÃO DA BARRA'],
                ['nome' => 'CAMPOS DO JORDÃO'],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.cities', ['state' => 'MT']));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => ['São João da Barra', 'Campos do Jordão'],
        ]);
    }

    public function testCitiesKeepsApostropheParticleFormatting(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/MT/municipios' => Http::response([
                ['nome' => "OLHO D'ÁGUA"],
                ['nome' => 'SANTA BÁRBARA D\'OESTE'],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.cities', ['state' => 'MT']));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => ["Olho d'Água", "Santa Bárbara d'Oeste"],
        ]);
    }

    public function testCitiesKeepsApostropheParticleLowercaseAfterHyphen(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/MT/municipios' => Http::response([
                ['nome' => "OLHOS-D'ÁGUA"],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.cities', ['state' => 'MT']));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => ["Olhos-d'Água"],
        ]);
    }

    public function testCitiesKeepsConjunctionELowercase(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/RN/municipios' => Http::response([
                ['nome' => 'PASSA E FICA'],
            ]),
        ]);

        $response = $this->get(route('migration.api.localidades.cities', ['state' => 'RN']));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => ['Passa e Fica'],
        ]);
    }

    public function testCitiesRejectsInvalidState(): void
    {
        Http::fake();

        $response = $this->get(route('migration.api.localidades.cities', ['state' => 'XX']));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'UF inválida.',
            'data' => [],
        ]);

        Http::assertNothingSent();
    }
}
