<?php

namespace App\Http\Controllers;

use App\Models\DefaultTask;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ProjectController extends Controller
{
    // --- CRUD DE PROYECTOS ---

    public function index()
    {
        $projects = Project::query()
            ->with(['users', 'tasks.department']) 
            ->withCount(['timeEntries as total_entries'])
            // ORDENAMIENTO: Primero por Cliente (Agrupar), luego por fecha de creación (Recientes primero)
            ->orderBy('client', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($project) {
                $project->append(['current_workers', 'consumed_hours']);
                return $project;
            });

        $activeEntry = Auth::user()->activeTimeEntry;
        if ($activeEntry) {
            $activeEntry->load('project');
        }

        return Inertia::render('Project/Index', [
            'projects' => $projects,
            'activeEntry' => $activeEntry,
        ]);
    }

   public function create()
    {
        $departments = Department::all(['id', 'name']);
        // NUEVO: Cargamos el catálogo de tareas
        $defaultTasks = DefaultTask::with('department:id,name')->latest()->get();
        
        return Inertia::render('Project/Create', [
            'departments' => $departments,
            'defaultTasks' => $defaultTasks // Pasamos a la vista
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
            'tasks.*.hours' => 'required|numeric|min:0.1',
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
        // NUEVO: Cargar catálogo para la edición
        $defaultTasks = DefaultTask::with('department:id,name')->latest()->get();
        
        $project->load('tasks');

        return Inertia::render('Project/Edit', [
            'project' => $project,
            'departments' => $departments,
            'defaultTasks' => $defaultTasks // Pasamos a la vista
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
            'tasks.*.hours' => 'required|numeric|min:0.1',
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

    // --- TIME TRACKING LOGIC ---

    public function startWork(Request $request, Project $project)
    {
        $user = Auth::user();

        $taskId = $request->input('task_id');
        if ($taskId) {
            $task = Task::where('id', $taskId)->where('project_id', $project->id)->first();
            if (!$task) {
                return redirect()->back()->with('error', 'La tarea seleccionada no pertenece a este proyecto.');
            }
        }

        $currentEntry = $user->activeTimeEntry;
        if ($currentEntry) {
            if ($currentEntry->project_id === $project->id) {
                return redirect()->back()->with('info', 'Ya estás trabajando en este proyecto.');
            }
            $this->stopCurrentWorkLogic($currentEntry);
        }

        TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $taskId, 
            'start_time' => now(),
        ]);

        return redirect()->back()->with('message', "Iniciaste trabajo en: {$project->name}");
    }

    public function togglePause(Project $project)
    {
        // Lógica de pausa...
    }

    public function stopWork(Project $project)
    {
        $user = Auth::user();
        $entry = $user->activeTimeEntry;

        if (!$entry || $entry->project_id !== $project->id) {
            return redirect()->back()->with('error', 'No hay sesión activa para detener.');
        }

        $this->stopCurrentWorkLogic($entry);

        return redirect()->back()->with('message', 'Jornada terminada.');
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
    }

    // --- NUEVOS MÉTODOS: GESTIÓN CATÁLOGO TAREAS ---

    public function storeDefaultTask(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:default_tasks,name',
            'department_id' => 'nullable|exists:departments,id'
        ]);

        DefaultTask::create($request->all());
        
        return back(); // Inertia recargará los props automáticamente
    }

    public function destroyDefaultTask(DefaultTask $default_task)
    {
        $default_task->delete();
        return back();
    }
}