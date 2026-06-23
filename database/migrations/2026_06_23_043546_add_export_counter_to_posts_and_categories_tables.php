<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('export_counter')->default(0)->after('published_at');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('export_counter')->default(0)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('export_counter');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('export_counter');
        });
    }
};
