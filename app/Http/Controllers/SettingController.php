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
    // --- VISTAS PRINCIPALES ---

    public function index()
    {
        return Inertia::render('Setting/Index', [
            'section' => 'categories',
        ]);
    }

    public function permissions()
    {
        // Cargamos Roles con sus permisos
        $roles = Role::with(['permissions'])->latest()->get();
        
        // Cargamos Permisos agrupados por categoría
        $permissions = Permission::all()->groupBy(function ($data) {
            return $data->category;
        });

        return Inertia::render('Setting/Index', [
            'section' => 'permissions',
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function general()
    {
        $features = Feature::latest()->get();
        $departments = Department::latest()->get();
        $job_positions = JobPosition::latest()->get();
        $currentTab = request('currentTab');

        return Inertia::render('Setting/Index', [
            'section' => 'general',
            'features' => $features,
            'departments' => $departments,
            'job_positions' => $job_positions,
            'currentTab' => $currentTab
        ]);
    }

    // --- LÓGICA DE ROLES ---

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191|unique:roles,name',
            'permissions' => 'array'
        ]);

        // FIX: Forzar guard 'web' para evitar conflicto con Sanctum
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web' 
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
        
        return back();
    }

    public function updateRole(Request $request, $role_id)
    {
        $request->validate([
            'name' => 'required|string|max:255', // Unique validation podría necesitar ignorar el ID actual
            'permissions' => 'array'
        ]);

        $role = Role::find($role_id);
        
        if ($role) {
            $role->name = $request->name;
            // Asegurar que el guard sea web si se cambiara, aunque no debería ser necesario editarlo
            // $role->guard_name = 'web'; 
            $role->save();
            
            if ($request->has('permissions')) {
                // Sincroniza usando IDs. Como forzamos guard 'web' al crear, esto funcionará.
                $role->syncPermissions($request->permissions);
            }
        }
        
        return back();
    }

    public function deleteRole($role_id)
    {
        $role = Role::find($role_id);
        if ($role) {
            $role->delete();
        }
        
        return back();
    }

    // --- LÓGICA DE PERMISOS ---

    public function storePermission(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191|unique:permissions,name',
            'category' => 'required|string|max:191'
        ]);

        // FIX: Forzar guard 'web'
        Permission::create([
            'name' => $request->name,
            'category' => $request->category,
            'guard_name' => 'web'
        ]);
        
        return back();
    }

    public function updatePermission(Request $request, $permission_id)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'category' => 'required|string|max:191'
        ]);

        $permission = Permission::find($permission_id);
        if ($permission) {
            $permission->update([
                'name' => $request->name,
                'category' => $request->category,
                // 'guard_name' => 'web' // Opcional, mantener consistencia
            ]);
        }
        
        return back();
    }

    public function deletePermission($permission_id)
    {
        $permission = Permission::find($permission_id);
        if ($permission) {
            $permission->delete();
        }
        
        return back();
    }
}