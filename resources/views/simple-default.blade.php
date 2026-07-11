{{-- FILE: resources/views/simple-default.blade.php
     Memperbaiki error global "View [simple-default] not found".
     Dipakai otomatis oleh semua ->links() di seluruh aplikasi.
     Aman untuk paginate() maupun simplePaginate() (hanya tombol prev/next). --}}

@if ($paginator->hasPages())
<nav style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:4px 0;">
    {{-- Sebelumnya --}}
    @if ($paginator->onFirstPage())
        <span style="background:#1e293b; color:#475569; padding:8px 16px; border-radius:8px; font-size:13px; cursor:not-allowed;">← Sebelumnya</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
           style="background:#334155; color:#e2e8f0; padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none;">← Sebelumnya</a>
    @endif

    {{-- Berikutnya --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next"
           style="background:#334155; color:#e2e8f0; padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none;">Berikutnya →</a>
    @else
        <span style="background:#1e293b; color:#475569; padding:8px 16px; border-radius:8px; font-size:13px; cursor:not-allowed;">Berikutnya →</span>
    @endif
</nav>
@endif