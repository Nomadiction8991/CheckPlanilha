<?php

declare(strict_types=1);

namespace App\DTO;

use App\Support\BrazilStateCode;
use App\Support\QueryParameter;
use Illuminate\Http\Request;

final readonly class DepartmentFilters
{
    public function __construct(
        public ?int $administrationId,
        public ?int $comumId,
        public string $search,
        public ?string $state,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $administrationId = QueryParameter::integer($request, 'administracao_id');
        $comumId = QueryParameter::integer($request, 'comum_id');
        $state = BrazilStateCode::normalizePrefix(QueryParameter::string($request, 'estado'));

        return new self(
            administrationId: $administrationId > 0 ? $administrationId : null,
            comumId: $comumId > 0 ? $comumId : null,
            search: trim(QueryParameter::string($request, 'busca')),
            state: $state,
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

        if ($this->state !== null && $this->state !== '') {
            $query['estado'] = $this->state;
        }

        return $query;
    }
}
