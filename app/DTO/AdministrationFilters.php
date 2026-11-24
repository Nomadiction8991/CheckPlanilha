<?php

declare(strict_types=1);

namespace App\DTO;

use App\Support\BrazilStateCode;
use App\Support\QueryParameter;
use Illuminate\Http\Request;

final readonly class AdministrationFilters
{
    public function __construct(
        public string $search,
        public ?string $state,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $state = BrazilStateCode::normalizePrefix(QueryParameter::string($request, 'estado'));

        return new self(
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

        if ($this->search !== '') {
            $query['busca'] = $this->search;
        }

        if ($this->state !== null && $this->state !== '') {
            $query['estado'] = $this->state;
        }

        return $query;
    }
}
