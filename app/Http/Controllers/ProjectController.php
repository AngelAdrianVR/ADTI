<?php

namespace App\Http\Controllers;

use App\Models\DefaultTask;
use App\Models\Department;
use App\Models\PayrollUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\ProjectExtraTimeMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::query()
            ->with([
                'users', 
                'tasks.department',
                'activeTimeEntries.user', 
                'activeTimeEntries.task'
            ]) 
            ->withCount(['timeEntries as total_entries'])
            ->orderBy('client', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($project) {
                $project->append(['consumed_hours']);
                return $project;
            });

        $activeEntry = Auth::user()->activeTimeEntry;
        if ($activeEntry) {
            $activeEntry->load('project');
        }

        // NUEVO: Obtener usuarios activos con su estado de trabajo actual
        // Necesario para el modal de administración "Iniciar tarea a tercero"
        $users = User::where('is_active', true)
            ->whereNotIn('org_props->position', ['Soporte DTW']) 
            ->with('activeTimeEntry') // Cargamos si tienen tarea activa
            ->get(['id', 'name', 'profile_photo_path']);

        return Inertia::render('Project/Index', [
            'projects' => $projects,
            'activeEntry' => $activeEntry,
            'users' => $users 
        ]);
    }

    public function create()
    {
        $departments = Department::all(['id', 'name']);
        $defaultTasks = DefaultTask::with('department:id,name')->latest()->get();
        
        return Inertia::render('Project/Create', [
            'departments' => $departments,
            'defaultTasks' => $defaultTasks 
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'estimated_end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'tasks' => 'required|array|min:1',
            'tasks.*.department_id' => 'required|exists:departments,id',
            'tasks.*.description' => 'required|string|max:255',
            'tasks.*.hours' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $totalBudgetedHours = collect($request->tasks)->sum('hours');

            $project = Project::create([
                'name' => $request->name,
                'client' => $request->client,
                'start_date' => $request->start_date,
                'estimated_end_date' => $request->estimated_end_date,
                'budgeted_hours' => $totalBudgetedHours,
                'description' => $request->description,
                'status' => 'active'
            ]);

            foreach ($request->tasks as $taskData) {
                Task::create([
                    'project_id' => $project->id,
                    'department_id' => $taskData['department_id'],
                    'description' => $taskData['description'],
                    'budgeted_hours' => $taskData['hours'],
                ]);
            }

            DB::commit();

            return to_route('projects.index');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error al crear el proyecto: ' . $e->getMessage()]);
        }
    }

    public function show(Project $project)
    {
        $project->load(['tasks.department', 'timeEntries.user', 'timeEntries.task']);
        $project->append(['consumed_hours']);
        
        return Inertia::render('Project/Show', [
            'project' => $project
        ]);
    }

    public function edit(Project $project)
    {
        $departments = Department::all(['id', 'name']);
        $defaultTasks = DefaultTask::with('department:id,name')->latest()->get();
        $project->load('tasks');

        return Inertia::render('Project/Edit', [
            'project' => $project,
            'departments' => $departments,
            'defaultTasks' => $defaultTasks 
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'estimated_end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'status' => 'required|in:active,finished',
            'tasks' => 'required|array|min:1',
            'tasks.*.id' => 'nullable|exists:tasks,id',
            'tasks.*.department_id' => 'required|exists:departments,id',
            'tasks.*.description' => 'required|string|max:255',
            'tasks.*.hours' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $totalBudgetedHours = collect($request->tasks)->sum('hours');

            $project->update([
                'name' => $request->name,
                'client' => $request->client,
                'start_date' => $request->start_date,
                'estimated_end_date' => $request->estimated_end_date,
                'budgeted_hours' => $totalBudgetedHours,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            $taskIdsToKeep = collect($request->tasks)->pluck('id')->filter()->toArray();
            
            $project->tasks()->whereNotIn('id', $taskIdsToKeep)->delete();

            foreach ($request->tasks as $taskData) {
                if (isset($taskData['id'])) {
                    Task::where('id', $taskData['id'])->update([
                        'department_id' => $taskData['department_id'],
                        'description' => $taskData['description'],
                        'budgeted_hours' => $taskData['hours'],
                    ]);
                } else {
                    Task::create([
                        'project_id' => $project->id,
                        'department_id' => $taskData['department_id'],
                        'description' => $taskData['description'],
                        'budgeted_hours' => $taskData['hours'],
                    ]);
                }
            }

            DB::commit();

            return to_route('projects.index');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error al actualizar el proyecto: ' . $e->getMessage()]);
        }
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return to_route('projects.index');
    }

    public function updateStatus(Request $request, Project $project)
    {
        $request->validate([
            'status' => 'required|in:active,finished',
        ]);

        $newStatus = $request->status;

        // Si se marca como terminado, detener automáticamente todas las sesiones activas
        if ($newStatus === 'finished') {
            $activeEntries = $project->activeTimeEntries()->get();
            foreach ($activeEntries as $entry) {
                $this->stopCurrentWorkLogic($entry);
            }
        }

        $project->update(['status' => $newStatus]);

        return back()->with('message', "Estado del proyecto actualizado.");
    }

    // --- TIME TRACKING LOGIC ---

    public function startWork(Request $request, Project $project)
    {
        // 1. Determinar usuario objetivo (Admin puede forzar otro usuario)
        $targetUserId = Auth::id();
        
        if ($request->has('user_id') && Auth::user()->can('Gestionar tiempo en tareas')) {
            $targetUserId = $request->user_id;
        }
        $user = User::find($targetUserId);

        // 2. Validar tarea
        $taskId = $request->input('task_id');
        if ($taskId) {
            $task = Task::where('id', $taskId)->where('project_id', $project->id)->first();
            if (!$task) {
                return back()->with('error', 'La tarea seleccionada no pertenece a este proyecto.');
            }
            // Validar que la tarea no esté finalizada
            if ($task->completed_at) {
                return back()->with('error', 'No se puede iniciar trabajo en una tarea finalizada.');
            }
        }

        // 3. Detener tarea actual del usuario objetivo (si existe)
        $currentEntry = $user->activeTimeEntry;
        if ($currentEntry) {
            if ($currentEntry->project_id === $project->id && $currentEntry->task_id == $taskId) {
                return back()->with('info', "{$user->name} ya está trabajando en esta tarea.");
            }
            $this->stopCurrentWorkLogic($currentEntry);
        }

        // 4. Iniciar sesión
        TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $taskId, 
            'start_time' => now(),
        ]);

        // Sincronización simple: vincular el proyecto a la asistencia del día si existe
        $this->linkPayrollProjectToDate($user->id, $project->id);

        return back()->with('message', "Trabajo iniciado para {$user->name}.");
    }

    public function togglePause(Project $project)
    {
        // Lógica de pausa...
    }

    public function stopWork(Request $request, Project $project)
    {
        $targetUserId = Auth::id();
        
        if ($request->has('user_id') && Auth::user()->can('Gestionar tiempo en tareas')) {
            $targetUserId = $request->user_id;
        }
        $user = User::find($targetUserId);

        $entry = $user->activeTimeEntry;

        if (!$entry || $entry->project_id !== $project->id) {
            return back()->with('error', "{$user->name} no tiene una sesión activa en este proyecto.");
        }

        $this->stopCurrentWorkLogic($entry);

        return back()->with('message', "Jornada terminada para {$user->name}.");
    }

    private function stopCurrentWorkLogic(TimeEntry $entry)
    {
        $now = Carbon::now();
        $duration = $entry->start_time->diffInSeconds($now);

        $entry->update([
            'end_time' => $now,
            'total_duration_seconds' => $duration - $entry->total_pause_seconds,
            'is_paused' => false,
        ]);

        // Sincronización simple: vincular el proyecto a la asistencia del día si existe
        $this->linkPayrollProjectToDate($entry->user_id, $entry->project_id, $now);
    }

    public function addTimeEntry(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'user_id' => 'required|exists:users,id',
            'task_id' => 'nullable|exists:tasks,id',
            'duration' => 'required|numeric|min:0.1', 
            'date' => 'required|date',
        ]);

        $durationSeconds = (int)($request->duration * 3600);
        $startTime = Carbon::parse($request->date)->setTime(9, 0, 0); 
        $endTime = $startTime->copy()->addSeconds($durationSeconds);

        TimeEntry::create([
            'user_id' => $request->user_id,
            'project_id' => $request->project_id,
            'task_id' => $request->task_id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_duration_seconds' => $durationSeconds,
            'is_paused' => false,
        ]);

        // Sincronización simple: vincular el proyecto a la asistencia del día indicado
        $this->linkPayrollProjectToDate($request->user_id, $request->project_id, Carbon::parse($request->date));

        return back()->with('message', 'Tiempo agregado correctamente.');
    }

    public function storeDefaultTask(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:default_tasks,name',
            'department_id' => 'nullable|exists:departments,id'
        ]);

        DefaultTask::create($request->all());
        
        return back();
    }

    public function destroyDefaultTask(DefaultTask $default_task)
    {
        $default_task->delete();
        return back();
    }

    public function toggleTaskStatus(Task $task)
    {
        if ($task->completed_at) {
            $task->update(['completed_at' => null]);
            $message = 'Tarea reactivada.';
        } else {
            $task->update(['completed_at' => now()]);
            $message = 'Tarea marcada como terminada.';
        }

        return back()->with('message', $message);
    }

    // --- MÉTRICAS DE COSTOS Y HORAS EXTRA ---

    /**
     * Métricas de costos y horas extra de un proyecto (KPIs, desglose por empleado
     * e histórico diario). Usado por la pestaña "Costos y horas extra".
     */
    public function extraTimeMetrics(Request $request, Project $project)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : null;
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : null;

        $metrics = app(ProjectExtraTimeMetricsService::class)->forProject($project, $startDate, $endDate);

        return response()->json($metrics);
    }

    /**
     * Métricas globales: ranking de proyectos con mayor impacto financiero por
     * tiempo extra y ranking de empleados con más horas extra acumuladas.
     * Usado por el panel "Métricas globales" de la lista de proyectos.
     */
    public function globalExtraTimeMetrics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : null;
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : null;

        $metrics = app(ProjectExtraTimeMetricsService::class)->globalMetrics($startDate, $endDate);

        return response()->json($metrics);
    }

    /**
     * Sincronización simple (mejor esfuerzo): al registrar tiempo en un proyecto,
     * si existe una asistencia (PayrollUser) para ese usuario/fecha y aún no tiene
     * proyecto vinculado, se le asigna. Así el tiempo extra aprobado de incidencias
     * se refleja automáticamente en el tiempo invertido del proyecto.
     */
    private function linkPayrollProjectToDate(int $userId, int $projectId, ?Carbon $date = null)
    {
        $date = $date ?? Carbon::now();

        PayrollUser::where('user_id', $userId)
            ->whereDate('date', $date->toDateString())
            ->whereNull('project_id')
            ->update(['project_id' => $projectId]);
    }
}
