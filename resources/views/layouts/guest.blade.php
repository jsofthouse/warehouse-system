<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>@yield('title', 'Masuk') · Sistem Gudang Alkap Pertanian</title>
  <link rel="icon" href="data:,">

  {{-- Tabler core (vendored lokal, no CDN — lihat CLAUDE.md §3) --}}
  <link href="{{ asset('vendor/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <link href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}" rel="stylesheet">

  @stack('styles')
</head>
<body class="d-flex flex-column">
  <script src="{{ asset('vendor/tabler/js/tabler.min.js') }}" defer></script>

  <div class="page page-center">
    <div class="container container-tight py-4">

      <div class="text-center mb-4">
        <a href="{{ route('login') }}" class="d-inline-flex align-items-center text-decoration-none">
          <span class="avatar avatar-md bg-primary-lt me-2"><i class="ti ti-building-warehouse fs-2"></i></span>
          <span class="fw-bold fs-2">Gudang Alkap</span>
        </a>
      </div>

      @yield('content')

      <div class="text-center text-secondary mt-3">
        &copy; {{ date('Y') }} Sistem Gudang &amp; Distribusi Alkap Pertanian
      </div>

    </div>
  </div>

  @stack('scripts')
</body>
</html>
