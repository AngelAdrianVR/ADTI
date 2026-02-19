<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\JobPosition;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
   public function index()
    {
        // Rango de la semana actual
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $users = User::latest()
            ->whereNotIn('org_props->position', ['Soporte DTW'])
            // Sumar segundos de las tareas de la semana actual
            ->withSum(['timeEntries as current_week_seconds' => function ($query) use ($startOfWeek, $endOfWeek) {
                $query->whereBetween('start_time', [$startOfWeek, $endOfWeek]);
            }], 'total_duration_seconds')
            ->get()
            ->map(function ($user) {
                // Formatear segundos a "Xh Ym"
                $seconds = $user->current_week_seconds ?? 0;
                $h = floor($seconds / 3600);
                $m = floor(($seconds % 3600) / 60);
                
                // Agregamos el atributo formateado
                $user->weekly_time_formatted = "{$h}h {$m}m";
                
                return $user;
            });

        return inertia('User/Index', compact('users'));
    }

    // Método para "Mis Nóminas"
    public function myPayrolls()
    {
        $user = auth()->user();

        $payrolls = Payroll::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->orderBy('start_date', 'desc')
        ->get();

        if ($payrolls->isEmpty()) {
            return inertia('User/MyPayrolls', ['payrolls' => []]);
        }

        $allAttendances = PayrollUser::whereIn('payroll_id', $payrolls->pluck('id'))
            ->where('user_id', $user->id)
            ->get()
            ->groupBy('payroll_id');

        $minDate = $payrolls->min('start_date');
        $maxDate = $payrolls->max('start_date')->copy()->addDays(14);
        $allHolidays = Holiday::whereBetween('date', [$minDate, $maxDate])->get();

        $processedPayrolls = $payrolls->map(function ($payroll) use ($user, $allAttendances, $allHolidays) {
            $rawAttendances = $allAttendances->get($payroll->id);
            
            $endDate = $payroll->start_date->copy()->addDays(14);
            $payrollHolidays = $allHolidays->filter(function($holiday) use ($payroll, $endDate) {
                return $holiday->date >= $payroll->start_date && $holiday->date <= $endDate;
            });

            return [
                'id' => $payroll->id,
                'biweekly' => $payroll->biweekly,
                'start_date' => $payroll->start_date,
                'is_active' => $payroll->is_active,
                'incidences' => $payroll->getProcessedAttendances($user->id, $rawAttendances, $payrollHolidays),
            ];
        });

        return inertia('User/MyPayrolls', [
            'payrolls' => $processedPayrolls
        ]);
    }
    
    public function create()
    {
        $roles = Role::all();
        $departments = Department::latest()->get();
        $job_positions = JobPosition::latest()->get();
        // Obtener todos los usuarios activos para el selector de "Empleados a cargo"
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return inertia('User/Create', compact('roles' ,'departments', 'job_positions', 'users'));
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $user_roles = $user->roles->pluck('id');
        $departments = Department::latest()->get();
        $job_positions = JobPosition::latest()->get();
        // Obtener usuarios para editar empleados a cargo (excluyendo al mismo usuario)
        $users = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return inertia('User/Edit', compact('user', 'roles', 'user_roles','departments', 'job_positions', 'users'));
    }

    public function reactivation(User $user)
    {
        $roles = Role::all();
        $user_roles = $user->roles->pluck('id');

        return inertia('User/Reactivation', compact('user', 'roles', 'user_roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:10',
            'name' => 'required|string|max:255|unique:users,name',
            'email' => 'nullable',
            'phone' => 'nullable|string|max:15',
            'birthdate' => 'nullable|date',
            'civil_state' => 'nullable|string',
            'address' => 'nullable|string',
            'rfc' => 'nullable|string',
            'curp' => 'nullable|string',
            'ssn' => 'nullable|string',
            'org_props.entry_date' => 'required|date',
            'org_props.position' => 'required|string|max:255',
            'org_props.department' => 'required|string|max:255',
            'org_props.phone' => 'nullable|string|max:255',
            'org_props.biweekly_complement' => 'nullable|numeric|min:1',
            'org_props.month_complement' => 'nullable|numeric|min:1',
            'org_props.net_salary' => 'nullable|numeric|min:1',
            'org_props.email' => 'nullable|string|max:255',
            'org_props.vacations' => 'nullable',
            'org_props.updated_date_vacations' => 'nullable',
            'roles' => 'required|array|min:1',
            'employees_in_charge' => 'nullable|array', // Validación array
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'org_props.entry_date.required' => 'Campo obligatorio.',
            'org_props.position.required' => 'Campo obligatorio.',
            'org_props.email.required' => 'Campo obligatorio.',
        ]);

        $validated['org_props']['vacations'] = 0;
        $validated['org_props']['updated_date_vacations'] = now()->toDateString();

        $user = User::create($validated + ['password' => bcrypt('123456')]);

        if ($request->hasFile('image')) {
            $this->storeProfilePhoto($request, $user);
            $roles = array_map('intval', $request->roles);
            $user->syncRoles($roles);
        } else {
            $user->syncRoles($request->roles);
        }

        return to_route('users.show', $user->id);
    }

    public function show(User $user)
    {
        $users = User::get(['id', 'name']);
        $user->load(['media']);

        // Obtener vacaciones
        $vacations = PayrollUser::where(['user_id' => $user->id, 'incidence' => 'Vacaciones'])
            ->get()
            ->groupBy(function ($vacation) {
                return $vacation->date->format('Y'); 
            })
            ->map(function ($vacations, $year) {
                return [
                    'label' => $year,
                    'children' => $vacations->map(function ($vacation) {
                        return [
                            'label' => $vacation->date->isoFormat('dd, DD MMM')
                        ];
                    })->values()->all()
                ];
            })->values()->all();
        
        // Cargar objetos User completos de los empleados a cargo
        $employeesInCharge = [];
        if (!empty($user->employees_in_charge)) {
            $employeesInCharge = User::whereIn('id', $user->employees_in_charge)->get(['id', 'name', 'profile_photo_path', 'org_props']);
        }

        return inertia('User/Show', compact('user', 'users', 'vacations', 'employeesInCharge'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:10',
            'name' => 'required|string|max:255|unique:users,name,' . $user->id,
            'email' => 'nullable',
            'phone' => 'nullable|string|max:15',
            'birthdate' => 'nullable|date',
            'civil_state' => 'nullable|string',
            'address' => 'nullable|string',
            'rfc' => 'nullable|string',
            'curp' => 'nullable|string',
            'ssn' => 'nullable|string',
            'org_props.entry_date' => 'required|date',
            'org_props.position' => 'required|string|max:255',
            'org_props.department' => 'required|string|max:255',
            'org_props.phone' => 'nullable|string|max:255',
            'org_props.email' => 'nullable|string|max:255',
            'org_props.vacations' => 'nullable',
            'org_props.updated_date_vacations' => 'nullable',
            'org_props.biweekly_complement' => 'nullable|numeric|min:1',
            'org_props.month_complement' => 'nullable|numeric|min:1',
            'org_props.net_salary' => 'nullable|numeric|min:1',
            'roles' => 'required|array|min:1',
            'employees_in_charge' => 'nullable|array', // Validación array
        ], [
            'org_props.entry_date.required' => 'Campo obligatorio.',
            'org_props.position.required' => 'Campo obligatorio.',
            'org_props.position.department' => 'Campo obligatorio.',
            'org_props.email.required' => 'Campo obligatorio.',
        ]);

        $user->update($request->all());
        $user->syncRoles($request->roles);

        if (!$request->selectedImage) {
            $this->deleteProfilePhoto($user);
        }

        return to_route('users.show', $user->id);
    }

    public function updateWithMedia(Request $request, User $user)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:10',
            'name' => 'required|string|max:255|unique:users,name,' . $user->id,
            'email' => 'nullable',
            'phone' => 'nullable|string|max:15',
            'birthdate' => 'nullable|date',
            'civil_state' => 'nullable|string',
            'address' => 'nullable|string',
            'rfc' => 'nullable|string',
            'curp' => 'nullable|string',
            'ssn' => 'nullable|string',
            'org_props.entry_date' => 'nullable|date',
            'org_props.position' => 'nullable|string|max:255',
            'org_props.department' => 'nullable|string|max:255',
            'org_props.phone' => 'nullable|string|max:255',
            'org_props.email' => 'nullable|string|max:255',
            'org_props.vacations' => 'nullable',
            'org_props.updated_date_vacations' => 'nullable',
            'org_props.biweekly_complement' => 'nullable|numeric|min:1',
            'org_props.month_complement' => 'nullable|numeric|min:1',
            'org_props.net_salary' => 'nullable|numeric|min:1',
            'roles' => 'required|array|min:1',
            'employees_in_charge' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'org_props.entry_date.required' => 'Campo obligatorio.',
            'org_props.position.required' => 'Campo obligatorio.',
            'org_props.email.required' => 'Campo obligatorio.',
        ]);

        $user->update($request->all());
        $roles = array_map('intval', $request->roles);
        $user->syncRoles($roles);

        $this->deleteProfilePhoto($user);
        $this->storeProfilePhoto($request, $user);

        return to_route('users.show', $user->id);
    }

    public function destroy(string $id)
    {
        //
    }

    public function storeProfilePhoto($request, User $user)
    {
        $path = $request->file('image')->store('public/profile-photos');
        $path = str_replace('public/', '', $path);
        $user->update([
            'profile_photo_path' => $path,
        ]);
    }

    public function deleteProfilePhoto(User $user)
    {
        $currentPhoto = $user->profile_photo_path;

        if ($currentPhoto) {
            Storage::delete('public/' . $currentPhoto);
            $user->update([
                'profile_photo_path' => null,
            ]);
        }
    }

    public function resetPassword(User $user)
    {
        $user->update(['password' => bcrypt('123456')]);
    }

    public function toggleHomeOffice(User $user)
    {
        $user->update(['home_office' => !$user->home_office]);
        return back();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->items_ids as $id) {
            if ($id != auth()->id()) {
                $item = User::find($id);
                $item?->delete();
            }
        }
    }

    public function massiveDeleteMedia(Request $request)
    {
        foreach ($request->items_ids as $id) {
            $item = Media::find($id);
            $item?->delete();
        }
    }

    public function inactivate(Request $request, User $user)
    {
        $request->validate([
            'inactivate_date' => 'required|date',
            'inactivate_reason' => 'required|string|max:300',
        ]);

        $user->update([
            'is_active' => false,
            'inactivate_date' => $request->inactivate_date,
            'inactivate_reason' => $request->inactivate_reason,
        ]);
    }

    public function updateVacations(Request $request, User $user)
    {
        $props = $user->org_props;
        $props['vacations'] = $request->vacations;

        $user->update([
            'org_props' => $props
        ]);
    }

    public function storeMedia(Request $request, User $user)
    {
        $user->addAllMediaFromRequest()->each(fn($file) => $file->toMediaCollection('digitalFiles'));
    }

    public function updateMediaName(Request $request, Media $media)
    {
        $media->name = $request->media_name;
        $media->file_name = $request->media_name . ".$media->extension";
        $media->save();
    }

    // --- MÉTODOS DE ASISTENCIA ---

    public function getNextAttendance()
    {
        $next = auth()->user()->getNextAttendance();

        return response()->json(compact('next'));
    }

    public function setAttendance()
    {
        $user = auth()->user();
        $next = $user->setAttendance();

        return response()->json(compact('next'));
    }

    public function getPauseStatus()
    {
        $status = auth()->user()->paused;

        return response()->json(compact('status'));
    }

    public function setPause()
    {
        $user = auth()->user();

        $is_paused = $user->setPause();

        $message = $is_paused
            ? "Se ha pausado tu tiempo laboral"
            : "Se ha reanudado tu tiempo laboral";

        return response()->json(['message' => $message, 'status' => $is_paused]);
    }

    // --- NUEVO: API para Desempeño ---
    public function getPerformance(Request $request, User $user)
    {
        $range = $request->input('range', 'today'); // 'today', 'week', 'month', 'custom'
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TimeEntry::with(['project', 'task.department'])
            ->where('user_id', $user->id)
            ->whereNotNull('end_time') // Solo tiempos finalizados
            ->orderBy('start_time', 'desc');

        // Filtros de fecha
        switch ($range) {
            case 'today':
                $query->whereDate('start_time', Carbon::today());
                break;
            case 'week':
                $query->whereBetween('start_time', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('start_time', Carbon::now()->month)
                      ->whereYear('start_time', Carbon::now()->year);
                break;
            case 'custom':
                if ($startDate && $endDate) {
                    $start = Carbon::parse($startDate)->startOfDay();
                    $end = Carbon::parse($endDate)->endOfDay();
                    $query->whereBetween('start_time', [$start, $end]);
                }
                break;
        }

        $entries = $query->get();

        return response()->json(['items' => $entries]);
    }
}