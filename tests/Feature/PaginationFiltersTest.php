<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Tests\TestCase;

final class PaginationFiltersTest extends TestCase
{
    public function testSharedPaginationLinksKeepTheActiveFilters(): void
    {
        $this->app->instance('request', Request::create('/produtos?busca=cadeira&estado=SP', 'GET'));
        $paginator = new LengthAwarePaginator(
            items: range(1, 20),
            total: 21,
            perPage: 20,
            currentPage: 1,
            options: ['path' => '/produtos', 'pageName' => 'pagina'],
        );

        $html = view('partials.pagination', ['paginator' => $paginator])->render();
        $links = html_entity_decode($html);

        self::assertStringContainsString('/produtos?busca=cadeira&estado=SP&pagina=2', $links);
    }
}
