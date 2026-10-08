<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->string('check_in_location')->nullable()->after('check_in')->comment('Coordenadas GPS de la hora de entrada');
            $table->string('check_out_location')->nullable()->after('check_out')->comment('Coordenadas GPS de la hora de salida');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->dropColumn(['check_in_location', 'check_out_location']);
        });
    }
};