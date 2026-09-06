@if ($paginator->hasPages())
    <nav class="d-flex justify-items-center justify-content-between w-100" aria-label="Navigasi halaman">
        {{-- Versi ringkas untuk layar sempit: cuma tombol Sebelumnya/Berikutnya --}}
        <div class="d-flex justify-content-between flex-fill d-sm-none">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm disabled">Sebelumnya</span>
            @else
                <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
            @else
                <span class="btn btn-sm disabled">Berikutnya</span>
            @endif
        </div>

        {{-- Versi lengkap: info jumlah data + navigasi halaman --}}
        <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
            <p class="m-0 text-secondary">
                Menampilkan <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
                &ndash; <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
                dari <span class="fw-semibold">{{ $paginator->total() }}</span> data
            </p>

            <ul class="pagination m-0">
                {{-- Tombol Sebelumnya --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link" aria-hidden="true">&lsaquo;</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">&lsaquo;</a>
                    </li>
                @endif

                {{-- Nomor halaman, dengan elipsis kalau rentangnya panjang --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Tombol Berikutnya --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">&rsaquo;</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link" aria-hidden="true">&rsaquo;</span>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
@endif
