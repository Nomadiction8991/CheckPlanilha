<?php

declare(strict_types=1);

namespace App\DTO;

use App\Support\QueryParameter;
use Illuminate\Http\Request;

final readonly class ProductFilters
{
    public function __construct(
        public ?int $administrationId,
        public ?int $comumId,
        public string $search,
        public ?int $dependencyId,
        public ?int $assetTypeId,
        public ?string $state,
        public string $status,
        public bool $onlyNew,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $administrationId = QueryParameter::integer($request, 'administracao_id');
        $comumId = QueryParameter::integer($request, 'comum_id');
        $dependencyId = QueryParameter::integer($request, 'dependencia_id');
        $assetTypeId = QueryParameter::integer($request, 'tipo_bem_id');
        $search = trim(QueryParameter::string($request, 'busca'));
        $rawState = trim(QueryParameter::string($request, 'estado'));
        $state = $rawState !== '' ? mb_strtoupper(mb_substr($rawState, 0, 2, 'UTF-8'), 'UTF-8') : null;

        if ($search === '') {
            $search = trim(QueryParameter::string($request, 'nome'));
        }

        if ($search === '') {
            $search = trim(QueryParameter::string($request, 'codigo'));
        }

        return new self(
            administrationId: $administrationId > 0 ? $administrationId : null,
            comumId: $comumId > 0 ? $comumId : null,
            search: $search,
            dependencyId: $dependencyId > 0 ? $dependencyId : null,
            assetTypeId: $assetTypeId > 0 ? $assetTypeId : null,
            state: $state,
            status: trim(QueryParameter::string($request, 'status')),
            onlyNew: QueryParameter::boolean($request, 'somente_novos') || QueryParameter::string($request, 'status') === 'novos',
            page: QueryParameter::page($request, 'pagina', 20),
            perPage: 20,
        );
    }

    /**
     * @return array<string, scalar>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->administrationId !== null) {
            $query['administracao_id'] = $this->administrationId;
        }

        if ($this->comumId !== null) {
            $query['comum_id'] = $this->comumId;
        }

        if ($this->search !== '') {
            $query['busca'] = $this->search;
        }

        if ($this->dependencyId !== null) {
            $query['dependencia_id'] = $this->dependencyId;
        }

        if ($this->assetTypeId !== null) {
            $query['tipo_bem_id'] = $this->assetTypeId;
        }

        if ($this->state !== null && $this->state !== '') {
            $query['estado'] = $this->state;
        }

        if ($this->status !== '') {
            $query['status'] = $this->status;
        }

        if ($this->onlyNew && $this->status !== 'novos') {
            $query['somente_novos'] = 1;
        }

        return $query;
    }

    public function activeCriteriaCount(): int
    {
        $count = 0;

        foreach ([
            $this->administrationId,
            $this->comumId,
            $this->search,
            $this->dependencyId,
            $this->assetTypeId,
            $this->state,
        ] as $value) {
            if ($value !== null && $value !== '') {
                $count++;
            }
        }

        if ($this->status !== '') {
            $count++;
        }

        if ($this->onlyNew && $this->status !== 'novos') {
            $count++;
        }

        return $count;
    }
}
