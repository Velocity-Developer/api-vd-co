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
        Schema::table('dummy_products', function (Blueprint $table) {
            $table->foreignId('dummy_seller_id')
                ->nullable()
                ->after('dummy_product_brand_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dummy_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dummy_seller_id');
        });
    }
};
