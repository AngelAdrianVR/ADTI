<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Inicio y fin del periodo solicitado
            $table->date('start_date');
            $table->date('end_date');
            
            // Total de días solicitados (descontando fines de semana o festivos si aplica)
            $table->integer('days_requested'); 
            
            // Estados: Pendiente, Aprobada, Rechazada, Cancelada
            $table->string('status')->default('Pendiente'); 
            
            // Campos de resolución
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            
            // Notas del empleado y notas del aprobador
            $table->text('employee_notes')->nullable();
            $table->text('reviewer_notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_requests');
    }
};