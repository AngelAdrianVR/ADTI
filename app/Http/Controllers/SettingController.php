<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettingController extends Controller
{
    public function index()
    {
        $roles = Role::with(['permissions'])->get();
        $permissions = Permission::all()->groupBy(function ($data) {
            return $data->category;
        });
        $features = Feature::latest()->get();

        // mandar el unmero de tab por defecto desde la url
        $currentTab = request('currentTab');

        return inertia('Setting/Index', compact('roles', 'permissions', 'currentTab', 'features'));
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'permissions' => 'array|min:1'
        ]);

        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions);
    }

    public function updateRole(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'array|min:1'
        ]);

        $role->name = $request->name;
        $role->save();
        $role->syncPermissions($request->permissions);
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
