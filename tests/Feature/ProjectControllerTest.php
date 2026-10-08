<?php

namespace Tests\Feature;

use App\Models\DefaultTask;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase; // Usamos la base de datos en memoria (SQLite) súper rápida

    protected $admin;
    protected $employee;
    protected $department;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear permiso requerido
        $permission = Permission::where('name', 'Gestionar tiempo en tareas')->where('guard_name', 'web')->first();
        if (!$permission) {
            $permission = new Permission();
            $permission->name = 'Gestionar tiempo en tareas';
            $permission->guard_name = 'web';
            $permission->category = 'PROYECTOS';
            $permission->save();
        }

        // 2. Crear Usuarios
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('Gestionar tiempo en tareas');

        $this->employee = User::factory()->create(['is_active' => true]);

        // 3. Crear Departamento (Necesario para las tareas)
        $this->department = Department::create(['name' => 'Ingeniería']);
    }

    public function test_can_view_projects_index()
    {
        $project = Project::create([
            'name' => 'Proyecto ERP',
            'client' => 'Cliente ABC',
            'status' => 'active',
            'budgeted_hours' => 100
        ]);

        $response = $this->actingAs($this->admin)->get(route('projects.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Project/Index')
            ->has('projects')
            ->has('users')
        );
    }

    public function test_can_store_project_with_tasks()
    {
        $response = $this->actingAs($this->admin)->post(route('projects.store'), [
            'name' => 'Nuevo Proyecto',
            'client' => 'Empresa XYZ',
            'start_date' => now()->toDateString(),
            'estimated_end_date' => now()->addDays(30)->toDateString(),
            'description' => 'Desarrollo web',
            'tasks' => [
                [
                    'department_id' => $this->department->id,
                    'description' => 'Diseño UI/UX',
                    'hours' => 20
                ],
                [
                    'department_id' => $this->department->id,
                    'description' => 'Programación Backend',
                    'hours' => 40
                ]
            ]
        ]);

        $response->assertRedirect(route('projects.index'));

        // Verificar Proyecto
        $this->assertDatabaseHas('projects', [
            'name' => 'Nuevo Proyecto',
            'client' => 'Empresa XYZ',
            'budgeted_hours' => 60 // 20 + 40
        ]);

        $project = Project::where('name', 'Nuevo Proyecto')->first();

        // Verificar que las tareas se hayan creado correctamente y vinculado al proyecto
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'description' => 'Diseño UI/UX',
            'budgeted_hours' => 20
        ]);
        
        $this->assertDatabaseCount('tasks', 2);
    }

    public function test_can_start_and_stop_work()
    {
        $project = Project::create([
            'name' => 'App Móvil',
            'client' => 'Cliente iOS',
            'status' => 'active',
            'budgeted_hours' => 50
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'department_id' => $this->department->id,
            'description' => 'Codificación',
            'budgeted_hours' => 50
        ]);

        // 1. INICIAR TRABAJO
        $responseStart = $this->actingAs($this->employee)->post(route('projects.start', $project->id), [
            'task_id' => $task->id
        ]);

        $responseStart->assertStatus(302); // Redirect back()

        $this->assertDatabaseHas('time_entries', [
            'user_id' => $this->employee->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'end_time' => null, // El trabajo acaba de iniciar, end_time debe ser nulo
        ]);

        // Adelantar el tiempo 2 horas en la memoria para simular trabajo
        Carbon::setTestNow(now()->addHours(2));

        // 2. DETENER TRABAJO
        $responseStop = $this->actingAs($this->employee)->post(route('projects.stop', $project->id));
        $responseStop->assertStatus(302);

        $this->assertDatabaseMissing('time_entries', [
            'user_id' => $this->employee->id,
            'project_id' => $project->id,
            'end_time' => null, // Ya no debe haber registros en curso
        ]);

        // Comprobar que se guardó la duración correctamente (Aprox 7200 segundos = 2 horas)
        $entry = TimeEntry::where('user_id', $this->employee->id)->first();
        $this->assertNotNull($entry->end_time);
        
        // CORRECCIÓN: Usamos round() para evitar que fracciones de milisegundos rompan la aserción
        $this->assertEquals(7200, floor($entry->total_duration_seconds));

        // Resetear el reloj
        Carbon::setTestNow();
    }

    public function test_can_add_manual_time_entry()
    {
        $project = Project::create([
            'name' => 'Consultoría',
            'client' => 'Bimbo',
            'status' => 'active',
            'budgeted_hours' => 10
        ]);

        $response = $this->actingAs($this->admin)->post(route('projects.add-time-entry'), [
            'project_id' => $project->id,
            'user_id' => $this->employee->id, // El admin le carga horas a un empleado
            'task_id' => null,
            'duration' => 3.5, // 3 horas y media
            'date' => now()->toDateString()
        ]);

        $response->assertStatus(302);

        // 3.5 horas * 3600 segundos = 12600 segundos
        $this->assertDatabaseHas('time_entries', [
            'user_id' => $this->employee->id,
            'project_id' => $project->id,
            'total_duration_seconds' => 12600,
        ]);
    }

    public function test_can_toggle_task_status()
    {
        $project = Project::create(['name' => 'Test', 'client' => 'Test']);
        
        $task = Task::create([
            'project_id' => $project->id,
            'department_id' => $this->department->id,
            'description' => 'Hacer pruebas',
            'budgeted_hours' => 5
        ]);

        $this->assertNull($task->completed_at);

        // Completar tarea
        $this->actingAs($this->admin)->put(route('tasks.toggle-status', $task->id));
        $this->assertNotNull($task->fresh()->completed_at);

        // Reactivar tarea
        $this->actingAs($this->admin)->put(route('tasks.toggle-status', $task->id));
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_can_manage_default_tasks()
    {
        // 1. Crear Tarea Predeterminada
        $responseCreate = $this->actingAs($this->admin)->post(route('default-tasks.store'), [
            'name' => 'Auditoría mensual',
            'department_id' => $this->department->id
        ]);

        $responseCreate->assertStatus(302);
        
        $this->assertDatabaseHas('default_tasks', [
            'name' => 'Auditoría mensual',
            'department_id' => $this->department->id
        ]);

        $defaultTask = DefaultTask::where('name', 'Auditoría mensual')->first();

        // 2. Eliminar Tarea Predeterminada
        $responseDelete = $this->actingAs($this->admin)->delete(route('default-tasks.destroy', $defaultTask->id));
        $responseDelete->assertStatus(302);

        $this->assertDatabaseMissing('default_tasks', [
            'name' => 'Auditoría mensual'
        ]);
    }
}