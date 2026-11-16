@if ($paginator->hasPages())
    @php($paginator->appends(request()->query()))
    <div class="pagination">
        <div class="pagination-summary">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}.
            {{ $paginator->total() }} registro(s).
        </div>

        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="disabled">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}">Anterior</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}">Próxima</a>
            @else
                <span class="disabled">Próxima</span>
            @endif
        </div>
    </div>
@endif
