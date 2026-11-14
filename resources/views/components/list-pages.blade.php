@if($paginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $paginator->hasPages())
<nav class="catalog-pagination" aria-label="Navigasi halaman">
    <span>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }} · {{ $paginator->total() }} data</span>
    <div class="actions">
        @unless($paginator->onFirstPage())<a class="btn outline" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>@endunless
        @if($paginator->hasMorePages())<a class="btn outline" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>@endif
    </div>
</nav>
@endif
