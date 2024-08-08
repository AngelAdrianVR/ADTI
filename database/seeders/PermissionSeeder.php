<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'USUARIOS' => [
                'Crear usuarios',
                'Editar usuarios',
                'Eliminar usuarios',
                'Resetear contraseñas',
            ],
            'CATEGORIAS' => [
                'Crear categorias',
                'Editar categorias',
                'Eliminar categorias',
            ],
            'PRODUCTOS' => [
                'Crear productos',
                'Editar productos',
                'Eliminar productos',
            ],
            'CONFIGURACIONES' => [
                'Crear roles',
                'Editar roles',
                'Eliminar roles',
                'Crear permisos',
                'Editar permisos',
                'Eliminar permisos',
            ],
        ];

        $roles = [
            'Administrador',
            'Encargado de almacén',
        ];

        // Crear permisos en base de datos
        foreach ($permissions as $category => $permissions) {
            foreach ($permissions as $name) {
                Permission::create(['name' => $name, 'category' => $category]);
            }
        }
        
        // Crear rol de super admin y dar todos los persmisos
        $super = Role::create(['name' => 'Super admin']);
        $all_permissions = Permission::all()->pluck('name');
        $super->syncPermissions($all_permissions);

        // Crear roles en base de datos
        foreach ($roles as $name) {
            Role::create(['name' => $name]);
        }

        // asignar rol a usuarios registrados al momento
        $users = User::all();
        $users->each(fn ($user) => $user->assignRole($super));
    }
}
