<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\JobPosition;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->get();

        return inertia('User/Index', compact('users'));
    }

    public function create()
    {
        $roles = Role::all();
        $departments = Department::latest()->get();
        $job_positions = JobPosition::latest()->get();

        return inertia('User/Create', compact('roles' ,'departments', 'job_positions'));
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $user_roles = $user->roles->pluck('id');
        $departments = Department::latest()->get();
        $job_positions = JobPosition::latest()->get();

        return inertia('User/Edit', compact('user', 'roles', 'user_roles','departments', 'job_positions'));
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'org_props.entry_date.required' => 'Campo obligatorio.',
            'org_props.position.required' => 'Campo obligatorio.',
            'org_props.email.required' => 'Campo obligatorio.',
        ]);

        // agregar propiedades de vacaciones
        $validated['org_props']['vacations'] = 0;
        $validated['org_props']['updated_date_vacations'] = now()->toDateString();

        $user = User::create($validated + ['password' => bcrypt('123456')]);

        // guardar foto de perfil en caso de haberse seleccionado una
        if ($request->hasFile('image')) {
            $this->storeProfilePhoto($request, $user);
            // convertir a int los roles para que no ocurra error al guardar
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

        // Obtener vacaciones y agruparlas por año
        $vacations = PayrollUser::where(['user_id' => $user->id, 'incidence' => 'Vacaciones'])
            ->get()
            ->groupBy(function ($vacation) {
                return $vacation->date->format('Y'); // Agrupar por año
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

        return inertia('User/Show', compact('user', 'users', 'vacations'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:10',
            'name' => 'required|string|max:255|unique:users,name,' . $user->id, //ignora si es el mismo para este id
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
            'name' => 'required|string|max:255|unique:users,name,' . $user->id, //ignora si es el mismo para este id
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'org_props.entry_date.required' => 'Campo obligatorio.',
            'org_props.position.required' => 'Campo obligatorio.',
            'org_props.email.required' => 'Campo obligatorio.',
        ]);

        $user->update($request->all());
        // convertir a int los roles para que no ocurra error
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
        // Guarda la imagen en el sistema de archivos.
        $path = $request->file('image')->store('public/profile-photos');
        // Elimina el prefijo 'public' de la ruta.
        $path = str_replace('public/', '', $path);
        // Actualiza la propiedad 'profile_photo_path' del usuario.
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
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->items_ids as $id) {
            // evitar eliminar al usuario autenticado
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
}
