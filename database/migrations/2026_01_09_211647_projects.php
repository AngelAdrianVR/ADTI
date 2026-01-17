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
        // 1. Tabla de Proyectos
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client'); // Cliente
            $table->date('start_date');
            $table->date('estimated_end_date')->nullable();
            $table->decimal('budgeted_hours', 10, 2)->default(0); // Horas Presupuestadas
            
            // Estado para los filtros (Activos, Terminados)
            $table->enum('status', ['active', 'finished'])->default('active'); 
            
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Buena práctica en ERPs
        });

        // 2. Tabla de Registros de Tiempo (Time Tracking)
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            
            $table->timestamp('start_time')->nullable(); // Hora Inicio
            $table->timestamp('end_time')->nullable(); // Hora Fin (Null significa en progreso)
            
            // Control de Pausas
            $table->boolean('is_paused')->default(false); // ¿Está pausado actualmente?
            $table->timestamp('last_pause_start')->nullable(); // ¿Cuándo empezó la pausa actual?
            $table->integer('total_pause_seconds')->default(0); // Acumulador de segundos pausados
            
            $table->integer('total_duration_seconds')->default(0); // Duración final real (End - Start - Pausas)
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('projects');
    }
};