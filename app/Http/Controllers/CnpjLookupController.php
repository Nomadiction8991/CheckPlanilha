<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\CnpjLookupUnavailableException;
use App\Http\Requests\LookupCnpjRequest;
use App\Services\CnpjLookupService;
use Illuminate\Http\JsonResponse;

final class CnpjLookupController extends Controller
{
    public function __construct(
        private readonly CnpjLookupService $service,
    ) {
    }

    public function lookup(LookupCnpjRequest $request): JsonResponse
    {
        try {
            $result = $this->service->lookup($request->toDto()->cnpj);
        } catch (CnpjLookupUnavailableException) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Serviço de consulta indisponível. Tente novamente.',
            ], 502);
        }

        if ($result === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'CNPJ não encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'source' => 'cnpj.dev',
        ]);
    }
}
