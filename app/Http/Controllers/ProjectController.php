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
        $projects = Project::query()
            ->with(['users'])
            ->withCount(['timeEntries as total_entries'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($project) {
                // Forzamos la carga de los accessors
                $project->append(['current_workers', 'consumed_hours']);
                return $project;
            });

        // Verificamos si el usuario actual tiene una tarea activa
        $activeEntry = Auth::user()->activeTimeEntry;
        if ($activeEntry) {
            $activeEntry->load('project');
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
        
        // Variables para totales globales
        $projectTotalSeconds = 0;

        $projectMembers = $project->timeEntries->groupBy('user_id')->map(function ($entries) use (&$projectTotalSeconds) {
            $user = $entries->first()->user;
            
            $userTotalSeconds = 0;

            // Procesamos cada entrada del usuario
            $entries = $entries->map(function ($entry) use (&$userTotalSeconds) {
                
                // Cálculo de duración SIMPLE (Sin pausas)
                if ($entry->end_time) {
                    // Si ya terminó, usamos el guardado o calculamos la diferencia fija
                    $duration = $entry->total_duration_seconds > 0 
                        ? $entry->total_duration_seconds 
                        : $entry->start_time->diffInSeconds($entry->end_time);
                } else {
                    // Si sigue activo (en vivo), calculamos contra AHORA
                    $duration = $entry->start_time->diffInSeconds(Carbon::now());
                }

                $userTotalSeconds += $duration;
                $entry->calculated_duration = $duration;
                
                return $entry;
            });

            $projectTotalSeconds += $userTotalSeconds;

            // Agrupar por día
            $dailyBreakdown = $entries->groupBy(function ($entry) {
                return $entry->start_time->format('Y-m-d');
            })->map(function ($dayEntries, $date) {
                return [
                    'date' => $date,
                    'total_time' => $dayEntries->sum('calculated_duration'),
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

    // --- LÓGICA DE TIME TRACKING (SIMPLIFICADA) ---

    public function startWork(Project $project)
    {
        $user = Auth::user();
        $currentEntry = $user->activeTimeEntry;

        // 1. Si ya hay algo corriendo, lo detenemos primero (Auto-Switch)
        if ($currentEntry) {
            // Si intenta iniciar el mismo que ya corre, avisamos
            if ($currentEntry->project_id === $project->id) {
                return redirect()->back()->with('info', 'Ya estás trabajando en este proyecto.');
            }
            $this->stopCurrentWorkLogic($currentEntry);
        }

        // 2. Iniciamos nueva sesión limpia
        TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'start_time' => now(),
            // 'is_paused' y demás campos de pausa se quedan en default (false/0)
        ]);

        return redirect()->back()->with('message', "Iniciaste trabajo en: {$project->name}");
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

    // Lógica centralizada de cierre
    private function stopCurrentWorkLogic(TimeEntry $entry)
    {
        $now = Carbon::now();
        
        // Cálculo simple: Fin - Inicio
        // (Ignoramos pausas antiguas si existieran en la migración, asumimos flujo limpio)
        $duration = $entry->start_time->diffInSeconds($now);

        $entry->update([
            'end_time' => $now,
            'total_duration_seconds' => $duration,
        ]);
    }
}