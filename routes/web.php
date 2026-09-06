<?php

use App\Http\Controllers\Master\AlokasiController;
use App\Http\Controllers\Master\GudangController;
use App\Http\Controllers\Master\ItemController;
use App\Http\Controllers\Master\LokasiController;
use App\Http\Controllers\Master\TarifSewaController;
use App\Http\Controllers\Pengguna\UserController;
use App\Http\Controllers\Transaksi\PenerimaanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {

    Route::redirect('/', '/dashboard');

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // --- Transaksi -----------------------------------------------------------

    // Penerimaan (Barang Masuk). Matriks hak akses:
    // - Baca (index/show): semua role yang login, termasuk viewer. Scoping
    //   gudang_id-nya dikerjakan di query + PenerimaanPolicy, bukan di tampilan.
    // - Tulis draft & posting: super_admin, operator_pusat, operator_gudang.
    // - Pembatalan dokumen ter-posting: super_admin & operator_pusat saja
    //   ("03-aturan-bisnis.md" §7.3).
    Route::prefix('penerimaan')->name('penerimaan.')->group(function () {

        Route::get('/', [PenerimaanController::class, 'index'])->name('index');

        Route::middleware('role:super_admin,operator_pusat,operator_gudang')->group(function () {
            Route::get('create', [PenerimaanController::class, 'create'])->name('create');
            Route::post('/', [PenerimaanController::class, 'store'])->name('store');
            Route::get('{penerimaan}/edit', [PenerimaanController::class, 'edit'])->name('edit');
            Route::put('{penerimaan}', [PenerimaanController::class, 'update'])->name('update');
            Route::post('{penerimaan}/posting', [PenerimaanController::class, 'posting'])->name('posting');
        });

        Route::middleware('role:super_admin,operator_pusat')->group(function () {
            Route::get('{penerimaan}/batalkan', [PenerimaanController::class, 'formBatalkan'])->name('batalkan.form');
            Route::post('{penerimaan}/batalkan', [PenerimaanController::class, 'batalkan'])->name('batalkan');
        });

        // Render PDF mahal, jadi dibatasi lajunya ("08-keamanan.md" §11.5).
        Route::get('{penerimaan}/cetak', [PenerimaanController::class, 'cetak'])
            ->middleware('throttle:30,1')
            ->name('cetak');

        Route::get('{penerimaan}', [PenerimaanController::class, 'show'])->name('show');
    });

    Route::view('/surat-jalan', 'placeholder', [
        'title' => 'Surat Jalan',
        'pretitle' => 'Transaksi',
    ])->name('surat-jalan.index');

    Route::view('/invoice', 'placeholder', [
        'title' => 'Invoice Sewa Gudang',
        'pretitle' => 'Transaksi',
    ])->name('invoice.index');

    Route::view('/stok', 'placeholder', [
        'title' => 'Kartu Stok',
        'pretitle' => 'Transaksi',
    ])->name('stok.index');

    // --- Master data -----------------------------------------------------------
    //
    // Hak akses ikut matriks di "Spesifikasi Sistem Gudang Alkap.md" §4:
    // - Baca: super_admin, operator_pusat, operator_gudang, viewer (semua yang login)
    // - Baca Tarif Sewa: SEMUA KECUALI viewer
    // - Tulis (tambah/ubah/hapus) apa pun di sini: cuma super_admin & operator_pusat

    Route::prefix('master')->name('master.')->group(function () {

        Route::get('gudang', [GudangController::class, 'index'])->name('gudang.index');
        Route::get('item', [ItemController::class, 'index'])->name('item.index');
        Route::get('lokasi', [LokasiController::class, 'index'])->name('lokasi.index');
        Route::get('alokasi', [AlokasiController::class, 'index'])->name('alokasi.index');
        Route::get('alokasi/{lokasi}', [AlokasiController::class, 'show'])->name('alokasi.show');

        Route::middleware('role:super_admin,operator_pusat,operator_gudang')->group(function () {
            Route::get('tarif', [TarifSewaController::class, 'index'])->name('tarif.index');
        });

        Route::middleware('role:super_admin,operator_pusat')->group(function () {
            Route::get('gudang/create', [GudangController::class, 'create'])->name('gudang.create');
            Route::post('gudang', [GudangController::class, 'store'])->name('gudang.store');
            Route::get('gudang/{gudang}/edit', [GudangController::class, 'edit'])->name('gudang.edit');
            Route::put('gudang/{gudang}', [GudangController::class, 'update'])->name('gudang.update');
            Route::delete('gudang/{gudang}', [GudangController::class, 'destroy'])->name('gudang.destroy');

            Route::get('item/create', [ItemController::class, 'create'])->name('item.create');
            Route::post('item', [ItemController::class, 'store'])->name('item.store');
            Route::get('item/{item}/edit', [ItemController::class, 'edit'])->name('item.edit');
            Route::put('item/{item}', [ItemController::class, 'update'])->name('item.update');
            Route::delete('item/{item}', [ItemController::class, 'destroy'])->name('item.destroy');

            Route::get('lokasi/import', [LokasiController::class, 'importForm'])->name('lokasi.import.form');
            Route::post('lokasi/import', [LokasiController::class, 'import'])->name('lokasi.import');
            Route::get('lokasi/import/template', [LokasiController::class, 'downloadTemplate'])->name('lokasi.import.template');
            Route::get('lokasi/create', [LokasiController::class, 'create'])->name('lokasi.create');
            Route::post('lokasi', [LokasiController::class, 'store'])->name('lokasi.store');
            Route::get('lokasi/{lokasi}/edit', [LokasiController::class, 'edit'])->name('lokasi.edit');
            Route::put('lokasi/{lokasi}', [LokasiController::class, 'update'])->name('lokasi.update');
            Route::delete('lokasi/{lokasi}', [LokasiController::class, 'destroy'])->name('lokasi.destroy');

            Route::get('alokasi-set-massal', [AlokasiController::class, 'setMassalForm'])->name('alokasi.set-massal.form');
            Route::post('alokasi-set-massal', [AlokasiController::class, 'setMassal'])->name('alokasi.set-massal');
            Route::put('alokasi/{lokasi}', [AlokasiController::class, 'update'])->name('alokasi.update');

            Route::get('tarif/create', [TarifSewaController::class, 'create'])->name('tarif.create');
            Route::post('tarif', [TarifSewaController::class, 'store'])->name('tarif.store');
            Route::get('tarif/{tarif}/edit', [TarifSewaController::class, 'edit'])->name('tarif.edit');
            Route::put('tarif/{tarif}', [TarifSewaController::class, 'update'])->name('tarif.update');
            Route::delete('tarif/{tarif}', [TarifSewaController::class, 'destroy'])->name('tarif.destroy');
        });
    });

    // --- Sistem -----------------------------------------------------------

    // Kelola user & role cuma buat Super Admin — lihat matriks hak akses
    // di "Spesifikasi Sistem Gudang Alkap.md" §4.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::view('/activity-log', 'placeholder', [
        'title' => 'Activity Log',
        'pretitle' => 'Sistem',
    ])->name('activity-log.index');

});

require __DIR__.'/auth.php';
