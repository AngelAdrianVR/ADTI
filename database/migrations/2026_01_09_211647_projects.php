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
            $table->date('start_date')->nullable();
            $table->date('estimated_end_date')->nullable();
            // Este campo ahora será la suma de las tareas, pero lo mantenemos para consultas rápidas
            $table->decimal('budgeted_hours', 10, 2)->default(0); 
            
            // Estado para los filtros (Activos, Terminados)
            $table->enum('status', ['active', 'finished'])->default('active'); 
            
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Tabla de Tareas del Proyecto (NUEVA)
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained(); // Relación con departamentos
            $table->string('description'); // Descripción de la tarea (ej. "Diseño de Planos")
            $table->decimal('budgeted_hours', 8, 2); // Horas asignadas a esta tarea específica
            $table->timestamps();
        });

        // 3. Tabla de Registros de Tiempo (Time Tracking)
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            
            // ACTUALIZACIÓN: Vinculamos el tiempo a una tarea específica. 
            // Puede ser nullable si permites registrar tiempo "general" al proyecto sin tarea específica.
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete(); 

            $table->timestamp('start_time')->nullable(); 
            $table->timestamp('end_time')->nullable(); 
            
            // Control de Pausas
            $table->boolean('is_paused')->default(false); 
            $table->timestamp('last_pause_start')->nullable(); 
            $table->integer('total_pause_seconds')->default(0); 
            
            $table->integer('total_duration_seconds')->default(0); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('tasks'); // Eliminar primero por la FK
        Schema::dropIfExists('projects');
    }
};