<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket privat (mis. fork Beaver Builder): zip disimpan di disk lokal (bukan publik), tidak tampil di API publik,
 * dan hanya dibagikan ke situs di server terdaftar lewat /api/v1/plugin-updates.
 * Kanal: rilis terbaru (version/package_file) hanya untuk domain_uji; situs lain mendapat versi_stabil/paket_stabil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('paket_privat')->default(false)->after('package_external_url');
            $table->string('versi_stabil')->nullable()->after('paket_privat');
            $table->string('paket_stabil')->nullable()->after('versi_stabil');
            $table->json('domain_uji')->nullable()->after('paket_stabil');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['paket_privat', 'versi_stabil', 'paket_stabil', 'domain_uji']);
        });
    }
};
