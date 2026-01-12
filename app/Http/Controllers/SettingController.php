<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Feature;
use App\Models\JobPosition;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Inertia\Inertia;

class SettingController extends Controller
{
    // VISTA 1: Categorías
    // Ruta: settings/catalogos
    public function index()
    {
        return Inertia::render('Setting/Index', [
            'section' => 'categories',
        ]);
    }

    // VISTA 2: Roles y Permisos
    // Ruta: settings/permisos
    public function permissions()
    {
        // Cargamos los roles con sus permisos asignados
        $roles = Role::with(['permissions'])->latest()->get();
        
        // Cargamos todos los permisos y los agrupamos por categoría para facilitar la UI
        $permissions = Permission::all()->groupBy(function ($data) {
            return $data->category;
        });

        return Inertia::render('Setting/Index', [
            'section' => 'permissions',
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    // VISTA 3: General
    // Ruta: settings/general
    public function general()
    {
        $features = Feature::latest()->get();
        $departments = Department::latest()->get();
        $job_positions = JobPosition::latest()->get();

        // Tab por defecto si viene en la url
        $currentTab = request('currentTab');

        return Inertia::render('Setting/Index', [
            'section' => 'general',
            'features' => $features,
            'departments' => $departments,
            'job_positions' => $job_positions,
            'currentTab' => $currentTab
        ]);
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'permissions' => 'array'
        ]);

        $role = Role::create(['name' => $request->name]);
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
    }

    public function updateRole(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'array'
        ]);

        $role->name = $request->name;
        $role->save();
        
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
    }

    public function deleteRole(Role $role)
    {
        $role->delete();
    }

    public function storePermission(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'category' => 'required|string|max:191'
        ]);

        Permission::create($request->all());
    }

    public function updatePermission(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'category' => 'required|string|max:191'
        ]);

        $permission->update($request->all());
    }

    public function deletePermission(Permission $permission)
    {
        $permission->delete();
    }
}