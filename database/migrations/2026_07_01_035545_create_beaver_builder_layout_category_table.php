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
        Schema::create('beaver_builder_layout_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beaver_builder_layout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beaver_builder_template_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beaver_builder_layout_category');
    }
};
