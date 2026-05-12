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
                'Eliminar expedientes de usuarios',
            ],
            'PRODUCTOS' => [
                'Ver productos',
                'Crear productos',
                'Editar productos',
                'Eliminar productos',
                'Importar productos',
                'Exportar productos',
            ],
            'CONFIGURACIONES' => [
                'Ver puestos',
                'Ver departamentos',
                'Ver caracteristicas',
                'Ver permisos',
                'Ver categorias',
                'Ver roles',
                'Crear puestos',
                'Editar puestos',
                'Eliminar puestos',
                'Crear departamentos',
                'Editar departamentos',
                'Eliminar departamentos',
                'Crear cateforias',
                'Editar cateforias',
                'Eliminar cateforias',
                'Crear roles',
                'Editar roles',
                'Eliminar roles',
                'Crear permisos',
                'Editar permisos',
                'Eliminar permisos',
                'Crear caracteristicas',
                'Editar caracteristicas',
                'Eliminar caracteristicas',
            ],
            'DIAS FESTIVOS' => [
                'Ver dias festivos',
                'Crear dias festivos',
                'Editar dias festivos',
                'Eliminar dias festivos',
            ],
            'INCIDENCIAS' => [
                'Ver incidencias',
                'Crear incidencias',
                'Ver pre-nominas',
                'Aprobar tiempo extra',
            ],
            'PROYECTOS' => [
                'Ver proyectos',
                'Crear proyectos',
                'Editar proyectos',
                'Eliminar proyectos',
                'Terminar tareas de proyectos',
                'Gestionar tiempo en tareas',
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
