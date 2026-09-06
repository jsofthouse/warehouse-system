<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // super_admin & operator_pusat: gudang_id tetap null (akses lintas gudang).
            // operator_gudang: WAJIB gudang_id terisi — dipakai buat scoping query
            // (lihat "Spesifikasi Sistem Gudang Alkap.md" §4, Data scoping).
            $table->enum('role', ['super_admin', 'operator_pusat', 'operator_gudang', 'viewer'])
                ->default('viewer')
                ->after('password');

            $table->foreignId('gudang_id')
                ->nullable()
                ->after('role')
                ->constrained('gudangs')
                ->restrictOnDelete();

            $table->boolean('is_active')->default(true)->after('gudang_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gudang_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
