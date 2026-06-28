<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->time('break_start')->nullable()->after('check_out_location')->comment('Hora de inicio de pausa/comida');
            $table->time('break_end')->nullable()->after('break_start')->comment('Hora de regreso de pausa/comida');
            $table->unsignedSmallInteger('break_minutes')->nullable()->after('break_end')->comment('Duración total de la pausa en minutos');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->dropColumn(['break_start', 'break_end', 'break_minutes']);
        });
    }
};
