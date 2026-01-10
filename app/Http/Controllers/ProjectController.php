<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class ProjectController extends Controller
{
    // --- CRUD DE PROYECTOS ---

    public function index()
    {
        // Obtenemos proyectos con contadores útiles para la vista
        $projects = Project::query()
            ->with(['users']) // Para ver quiénes han participado
            ->withCount(['timeEntries as total_entries'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($project) {
                // Adjuntamos atributos calculados
                $project->current_workers = $project->current_workers;
                $project->consumed_hours = $project->consumed_hours;
                return $project;
            });

        // Verificamos si el usuario actual tiene una tarea activa
        $activeEntry = Auth::user()->activeTimeEntry;
        if ($activeEntry) {
            $activeEntry->load('project');
            // Forzamos el append del atributo calculado para que llegue al frontend
            $activeEntry->append('current_duration');
        }

        return Inertia::render('Project/Index', [
            'projects' => $projects,
            'activeEntry' => $activeEntry,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client' => 'required|string|max:255',
            'start_date' => 'required|date',
            'estimated_end_date' => 'nullable|date|after_or_equal:start_date',
            'budgeted_hours' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        Project::create($validated);

        return redirect()->back()->with('message', 'Proyecto creado exitosamente');
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client' => 'required|string|max:255',
            'start_date' => 'required|date',
            'estimated_end_date' => 'nullable|date|after_or_equal:start_date',
            'budgeted_hours' => 'required|numeric|min:0',
            'status' => 'required|in:active,finished',
            'description' => 'nullable|string',
        ]);

        $project->update($validated);

        return redirect()->back()->with('message', 'Proyecto actualizado');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect()->back()->with('message', 'Proyecto eliminado');
    }

    public function show($id)
    {
        $project = Project::with(['timeEntries.user'])->findOrFail($id);
        $projectTotalSeconds = 0;

        $projectMembers = $project->timeEntries->groupBy('user_id')->map(function ($entries) use (&$projectTotalSeconds) {
            $user = $entries->first()->user;
            
            $userTotalSeconds = 0;
            $entries = $entries->map(function ($entry) use (&$userTotalSeconds) {
                if ($entry->end_time) {
                    $duration = $entry->total_duration_seconds;
                } else {
                    $now = Carbon::now();
                    $gross = $entry->start_time->diffInSeconds($now);
                    $currentPause = ($entry->is_paused && $entry->last_pause_start) 
                        ? $entry->last_pause_start->diffInSeconds($now) 
                        : 0;
                    $duration = max(0, $gross - $entry->total_pause_seconds - $currentPause);
                }

                $userTotalSeconds += $duration;
                $entry->calculated_duration = $duration;
                return $entry;
            });

            $projectTotalSeconds += $userTotalSeconds;

            $dailyBreakdown = $entries->groupBy(function ($entry) {
                return $entry->start_time->format('Y-m-d');
            })->map(function ($dayEntries, $date) {
                return [
                    'date' => $date,
                    'total_time' => $dayEntries->sum('calculated_duration'),
                    'total_pause' => $dayEntries->sum('total_pause_seconds'),
                    'entries' => $dayEntries
                ];
            })->sortKeysDesc();

            return [
                'user' => $user,
                'total_seconds' => $userTotalSeconds,
                'daily_breakdown' => $dailyBreakdown
            ];
        })->values();

        $project->real_total_seconds = $projectTotalSeconds;
        $project->real_consumed_hours = round($projectTotalSeconds / 3600, 2);

        return Inertia::render('Project/Show', [
            'project' => $project,
            'members' => $projectMembers,
        ]);
    }

    // --- LÓGICA DE TIME TRACKING ---

    public function startWork(Project $project)
    {
        $user = Auth::user();
        $currentEntry = $user->activeTimeEntry;

        if ($currentEntry) {
            if ($currentEntry->project_id === $project->id) {
                return redirect()->back()->with('error', 'Ya estás trabajando en este proyecto.');
            }
            $this->stopCurrentWorkLogic($currentEntry);
        }

        TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'start_time' => now(),
            'is_paused' => false,
        ]);

        return redirect()->back()->with('message', "Iniciaste trabajo en: {$project->name}");
    }

    public function togglePause(Project $project)
    {
        $user = Auth::user();
        $entry = $user->activeTimeEntry;

        if (!$entry || $entry->project_id !== $project->id) {
            return redirect()->back()->with('error', 'No tienes una sesión activa en este proyecto.');
        }

        // Usamos una sola instancia de tiempo para consistencia
        $now = Carbon::now();

        if ($entry->is_paused) {
            // --- REANUDAR ---
            // Calcular tiempo que duró la pausa
            // Usamos abs() para evitar negativos por micro-desfases, aunque diffInSeconds suele ser seguro
            $pauseDuration = 0;
            if ($entry->last_pause_start) {
                $pauseDuration = $entry->last_pause_start->diffInSeconds($now);
            }
            
            $entry->update([
                'is_paused' => false,
                'total_pause_seconds' => $entry->total_pause_seconds + $pauseDuration,
                'last_pause_start' => null,
            ]);

            $msg = 'Has reanudado tu trabajo.';
        } else {
            // --- PAUSAR ---
            $entry->update([
                'is_paused' => true,
                'last_pause_start' => $now,
            ]);

            $msg = 'Trabajo pausado.';
        }

        return redirect()->back()->with('message', $msg);
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

        // Si estaba pausado al detener, cerramos la pausa primero
        if ($entry->is_paused && $entry->last_pause_start) {
            $pauseDuration = $entry->last_pause_start->diffInSeconds($now);
            $entry->total_pause_seconds += $pauseDuration;
        }

        // Cálculo final robusto
        $grossDuration = $entry->start_time->diffInSeconds($now);
        $netDuration = max(0, $grossDuration - $entry->total_pause_seconds);

        $entry->update([
            'end_time' => $now,
            'is_paused' => false,
            'last_pause_start' => null,
            'total_duration_seconds' => $netDuration,
        ]);
    }
}