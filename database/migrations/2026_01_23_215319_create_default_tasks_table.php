<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('default_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Opcional: Relacionar con departamento para sugerencias más inteligentes
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('default_tasks');
    }
};