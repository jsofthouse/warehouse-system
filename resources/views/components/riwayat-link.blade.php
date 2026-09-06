@props(['subjek', 'label' => null])

{{-- Cuma Super Admin yang boleh lihat link Riwayat (docs/12-modul-activity-log.md
     §4.3) — komponen sendiri yang mengecek, jadi view pemanggil tidak perlu
     peduli soal otorisasi ini. Tanpa prop "label": icon saja (dipakai di kolom
     aksi tabel index). Dengan "label": icon + teks (dipakai di header show). --}}
@if (auth()->user()?->role === \App\Enums\UserRole::SuperAdmin)
    <a href="{{ route('activity-log.index', ['subjek_type' => $subjek->getMorphClass(), 'subjek_id' => $subjek->getKey()]) }}"
        {{ $attributes->class(['btn', 'btn-sm', 'btn-icon' => $label === null]) }} title="Riwayat aktivitas">
        <i class="ti ti-history {{ $label ? 'me-1' : '' }}"></i>{{ $label }}
    </a>
@endif
