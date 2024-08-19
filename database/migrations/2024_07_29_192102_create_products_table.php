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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('consecutivo')->nullable();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('part_number');
            $table->string('part_number_supplier')->nullable();
            $table->string('location')->nullable();
            $table->float('line_cost')->nullable();
            $table->json('features')->nullable();
            $table->json('features_keys')->nullable();
            $table->json('bread_crumbles')->nullable();
            $table->foreignId('subcategory_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
