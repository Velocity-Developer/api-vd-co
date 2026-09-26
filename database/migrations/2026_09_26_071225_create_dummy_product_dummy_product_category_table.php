<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dummy_product_dummy_product_category', function (Blueprint $table): void {
            $table->foreignId('dummy_product_id')->constrained(indexName: 'dummy_product_category_product_foreign')->cascadeOnDelete();
            $table->foreignId('dummy_product_category_id')->constrained(indexName: 'dummy_product_category_category_foreign')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['dummy_product_id', 'dummy_product_category_id'], 'dummy_product_category_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dummy_product_dummy_product_category');
    }
};
