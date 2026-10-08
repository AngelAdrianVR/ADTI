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
        Schema::table('payroll_comments', function (Blueprint $table) {
            // Agregamos la columna date que puede ser nula.
            // Si es null, se considera un comentario general de la nómina.
            // Si tiene fecha, es un comentario de una incidencia específica.
            $table->date('date')->nullable()->after('payroll_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_comments', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};