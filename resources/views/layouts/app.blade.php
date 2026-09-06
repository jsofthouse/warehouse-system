<!DOCTYPE html>
<html lang="id" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Dashboard') · Sistem Gudang Alkap Pertanian</title>
    <link rel="icon" href="data:,">

    {{-- Tabler core (vendored locally, no CDN — lihat CLAUDE.md §3) --}}
    <link href="{{ asset('vendor/tabler/css/tabler.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/tom-select/css/tom-select.bootstrap5.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet">

    {{-- Alpine belum sempat sembunyiin elemen x-show sebelum render pertama tanpa ini --}}
    <style>
        [x-cloak] {
            display: none !important;
        }

        /* tabler.min.css sizing (.nav-link-icon, .dropdown-item-icon, .icon) ditujukan
           buat markup <svg class="icon">, bukan glyph webfont <i class="ti ti-*">.
           Tanpa ini glyph ikut font-size teks sekitarnya, jadi kelihatan kecil/kurus
           dibanding demo Tabler yang pakai SVG. */
        .ti {
            font-size: var(--tblr-icon-size, 1.25rem);
            vertical-align: middle;
        }

        /* Tema tom-select.bootstrap5 menulis warnanya sebagai var(--bs-*), padahal
           Tabler menerbitkan var(--tblr-*). Semua var itu tidak pernah resolve, jadi
           background dropdown jatuh ke transparan dan bordernya ikut hilang. Dijembatani
           di elemen Tom Select-nya sendiri (bukan di :root) supaya nilainya ikut
           data-bs-theme tempat elemennya berada. */
        .ts-wrapper,
        .ts-control,
        .ts-dropdown {
            --bs-body-bg: var(--tblr-bg-surface);
            --bs-body-color: var(--tblr-body-color);
            --bs-border-color: var(--tblr-border-color);
            --bs-border-color-translucent: var(--tblr-border-color-translucent);
            --bs-border-radius: var(--tblr-border-radius);
            --bs-border-radius-lg: var(--tblr-border-radius-lg);
            --bs-border-radius-sm: var(--tblr-border-radius-sm);
            --bs-border-width: var(--tblr-border-width);
            --bs-box-shadow-inset: var(--tblr-box-shadow-inset);
            --bs-form-invalid-color: var(--tblr-form-invalid-color);
            --bs-form-valid-color: var(--tblr-form-valid-color);
            --bs-secondary-bg: var(--tblr-secondary-bg);
            --bs-secondary-color: var(--tblr-secondary-color);
            --bs-tertiary-bg: var(--tblr-tertiary-bg);
        }

        /* Dropdown yang dipasang dengan dropdownParent: 'body' keluar dari stacking
           context wrapper-nya, jadi z-index bawaannya kalah dari navbar, header sticky,
           dan modal Tabler. Diangkat ke atas modal (1055).

           top/left di-nol-kan karena default tema (top: 100%) dihitung relatif ke <body>:
           sepersekian frame sebelum Tom Select menulis posisi aslinya, dropdown menempel
           di dasar dokumen, halaman jadi lebih tinggi, scrollbar muncul, dan layout
           bergeser ~15px. Posisi yang terlanjur diukur di frame itu ikut meleset. */
        body > .ts-dropdown {
            z-index: 1056;
            top: 0;
            left: 0;
            color: var(--bs-body-color);
        }
    </style>

    @stack('styles')
</head>

<body>
    <script src="{{ asset('vendor/tabler/js/tabler.min.js') }}" defer></script>

    <div class="page">

        {{-- ============ SIDEBAR ============ --}}
        <aside class="navbar navbar-vertical navbar-expand-lg navbar-folded-hover" data-bs-theme="dark">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
                    aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="navbar-brand navbar-brand-autodark">
                    <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                        <span class="avatar avatar-sm bg-primary-lt me-2"><i
                                class="ti ti-building-warehouse fs-3"></i></span>
                        <span class="fw-bold text-white d-none d-lg-inline">Gudang Alkap</span>
                    </a>
                    <button type="button" class="btn btn-action btn-sm text-white-50" data-bs-toggle="sidebar-folded"
                        aria-pressed="false" aria-label="Pin sidebar">
                        <i class="ti ti-layout-sidebar-left-collapse"></i>
                    </button>
                </div>

                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <ul class="navbar-nav pt-lg-3">

                        <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('dashboard') }}">
                                <span class="nav-link-icon"><i class="ti ti-home"></i></span>
                                <span class="nav-link-title">Dashboard</span>
                            </a>
                        </li>

                        <li class="nav-section-title">Transaksi</li>

                        <li class="nav-item {{ request()->routeIs('penerimaan.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('penerimaan.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-truck-loading"></i></span>
                                <span class="nav-link-title">Penerimaan Barang</span>
                            </a>
                        </li>

                        <li class="nav-item {{ request()->routeIs('surat-jalan.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('surat-jalan.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-file-text"></i></span>
                                <span class="nav-link-title">Surat Jalan</span>
                            </a>
                        </li>

                        <li class="nav-item {{ request()->routeIs('invoice.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('invoice.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-receipt"></i></span>
                                <span class="nav-link-title">Invoice Sewa Gudang</span>
                            </a>
                        </li>

                        <li class="nav-item {{ request()->routeIs('stok.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('stok.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-clipboard-list"></i></span>
                                <span class="nav-link-title">Kartu Stok</span>
                            </a>
                        </li>

                        <li class="nav-section-title">Master Data</li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"
                                data-bs-auto-close="outside" role="button" aria-expanded="false">
                                <span class="nav-link-icon"><i class="ti ti-database"></i></span>
                                <span class="nav-link-title">Master</span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="{{ route('master.item.index') }}">Item / Alkap</a>
                                <a class="dropdown-item" href="{{ route('master.lokasi.index') }}">Lokasi Kodim</a>
                                <a class="dropdown-item" href="{{ route('master.alokasi.index') }}">Alokasi
                                    Kebutuhan</a>
                                <a class="dropdown-item" href="{{ route('master.gudang.index') }}">Gudang</a>
                                @if (auth()->user()->role !== \App\Enums\UserRole::Viewer)
                                    <a class="dropdown-item" href="{{ route('master.tarif.index') }}">Tarif Sewa</a>
                                @endif
                            </div>
                        </li>

                        <li class="nav-section-title">Sistem</li>

                        @if (auth()->user()->role === \App\Enums\UserRole::SuperAdmin)
                            <li class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('users.index') }}">
                                    <span class="nav-link-icon"><i class="ti ti-users"></i></span>
                                    <span class="nav-link-title">Pengguna</span>
                                </a>
                            </li>
                        @endif

                        <li class="nav-item {{ request()->routeIs('activity-log.*') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('activity-log.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-history"></i></span>
                                <span class="nav-link-title">Activity Log</span>
                            </a>
                        </li>

                    </ul>
                </div>

                <div class="navbar-footer">
                    <ul class="navbar-nav">
                        <li class="nav-item dropup">
                            <a href="#" class="nav-link" data-bs-toggle="dropdown"
                                aria-label="Buka menu pengguna">
                                <span class="avatar avatar-sm bg-blue-lt"><i class="ti ti-user"></i></span>
                                <span class="nav-link-title">
                                    {{ auth()->user()->name }}
                                    <div class="small text-secondary">{{ auth()->user()->role->label() }}</div>
                                </span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="{{ route('password.edit') }}">
                                    <i class="ti ti-key me-2"></i>Ubah Password
                                </a>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="ti ti-logout me-2"></i>Keluar
                                    </button>
                                </form>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>

        {{-- ============ MAIN ============ --}}
        <div class="page-wrapper">

            <div class="page-header d-print-none">
                <div class="container-fluid">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            @hasSection('pretitle')
                                <div class="page-pretitle">@yield('pretitle')</div>
                            @endif
                            <h2 class="page-title">@yield('title', 'Dashboard')</h2>
                        </div>
                        <div class="col-auto ms-auto d-print-none">
                            @yield('page-actions')
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-body">
                <div class="container-fluid">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible" role="alert">
                            {{ session('success') }}
                            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible" role="alert">
                            {{ session('error') }}
                            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>

            <footer class="footer footer-transparent d-print-none">
                <div class="container-fluid">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">Dibangun dengan Laravel + Tabler</li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    &copy; {{ date('Y') }} Sistem Gudang &amp; Distribusi Alkap Pertanian
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="{{ asset('vendor/alpinejs/cdn.min.js') }}" defer></script>
    <script src="{{ asset('vendor/tom-select/js/tom-select.complete.min.js') }}"></script>
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.min.js') }}"></script>
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
    @stack('scripts')
</body>

</html>
