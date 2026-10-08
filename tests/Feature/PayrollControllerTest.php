<?php

namespace Tests\Feature;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;
use Carbon\Carbon;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase; // Resetea la base de datos después de cada test

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear el permiso global requerido por la lógica evadiendo la asignación masiva
        $permission = Permission::where('name', 'Ver incidencias')->where('guard_name', 'web')->first();
        if (!$permission) {
            $permission = new Permission();
            $permission->name = 'Ver incidencias';
            $permission->guard_name = 'web';
            $permission->category = 'INCIDENCIAS'; // <- Asignación explícita de la categoría
            $permission->save();
        }
        
        // 2. Crear un usuario "Administrador / RRHH"
        $this->adminUser = User::factory()->create([
            'org_props' => [
                'position' => 'Recursos Humanos',
                'department' => 'Administración'
            ],
            'is_active' => true,
            'employees_in_charge' => [],
        ]);
        
        $this->adminUser->givePermissionTo('Ver incidencias');
    }

    public function test_can_view_payrolls_index()
    {
        // Crear una nómina de prueba
        Payroll::create([
            'start_date' => Carbon::now()->startOfWeek()->toDateString(),
            'biweekly' => 1,
            'is_active' => true,
        ]);

        // Simular inicio de sesión y petición GET
        $response = $this->actingAs($this->adminUser)->get(route('payrolls.index'));

        // Aserciones
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Index') // Verifica que renderice la vista correcta
            ->has('payrolls')            // Verifica que envíe la variable 'payrolls'
        );
    }

    public function test_can_view_payroll_show()
    {
        $payroll = Payroll::create([
            'start_date' => Carbon::now()->startOfWeek()->toDateString(),
            'biweekly' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('payrolls.show', $payroll->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Show')
            ->has('payroll')
            ->has('payrollUsers')
            ->has('adjacentPayrolls')
        );
    }

    public function test_can_view_pre_payroll_template()
    {
        $payroll = Payroll::create([
            'start_date' => Carbon::now()->startOfWeek()->toDateString(),
            'biweekly' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('payrolls.pre-payroll', $payroll->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/PrePayrollTemplate')
            ->has('payroll')
            ->has('payrollUsers')
        );
    }

    public function test_can_view_receipts_template()
    {
        $payroll = Payroll::create([
            'start_date' => Carbon::now()->startOfWeek()->toDateString(),
            'biweekly' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('payrolls.receipts', $payroll->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/PayrollReceiptTemplate')
            ->has('payroll')
            ->has('payrollUsers')
        );
    }

    public function test_user_without_permission_and_without_employees_in_charge_sees_empty_list()
    {
        $payroll = Payroll::create([
            'start_date' => Carbon::now()->startOfWeek()->toDateString(),
            'biweekly' => 1,
            'is_active' => true,
        ]);
        
        // Usuario estándar (sin permiso y sin subordinados)
        $unprivilegedUser = User::factory()->create([
            'org_props' => [
                'position' => 'Staff',
                'department' => 'Operaciones'
            ],
            'employees_in_charge' => []
        ]);

        $response = $this->actingAs($unprivilegedUser)->get(route('payrolls.show', $payroll->id));

        $response->assertStatus(200);
        
        // Comprobamos que el array de payrollUsers llegue VACÍO por falta de permisos
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Show')
            ->has('payrollUsers', 0)
        );
    }

    public function test_user_without_permission_but_with_employees_in_charge_sees_specific_users()
    {
        $payroll = Payroll::create([
            'start_date' => Carbon::now()->startOfWeek()->toDateString(),
            'biweekly' => 1,
            'is_active' => true,
        ]);

        $employee = User::factory()->create([
            'org_props' => ['position' => 'Operador', 'department' => 'Operaciones'],
            'is_active' => true,
        ]);
        
        // Usuario Supervisor (no tiene permiso global, pero tiene 1 empleado a cargo)
        $managerUser = User::factory()->create([
            'org_props' => ['position' => 'Supervisor', 'department' => 'Operaciones'],
            'is_active' => true,
            'employees_in_charge' => [$employee->id] 
        ]);

        $response = $this->actingAs($managerUser)->get(route('payrolls.show', $payroll->id));

        $response->assertStatus(200);
        
        // Debería ver a 2 usuarios obligatoriamente: el empleado a su cargo y a SÍ MISMO.
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Show')
            ->has('payrollUsers', 2)
        );
    }

    public function test_filtering_by_user_ids()
    {
        $payroll = Payroll::create([
            'start_date' => Carbon::now()->startOfWeek()->toDateString(),
            'biweekly' => 1,
            'is_active' => true,
        ]);

        $employee1 = User::factory()->create(['is_active' => true, 'org_props' => ['position' => 'Staff']]);
        $employee2 = User::factory()->create(['is_active' => true, 'org_props' => ['position' => 'Staff']]);

        // Petición con query params ?user_ids=id (Ej. Al imprimir solo algunos recibos)
        $response = $this->actingAs($this->adminUser)->get(route('payrolls.receipts', [
            'payroll' => $payroll->id,
            'user_ids' => $employee1->id
        ]));

        $response->assertStatus(200);
        
        // Debería devolver solo al usuario filtrado (1 registro) en lugar de ambos
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/PayrollReceiptTemplate')
            ->has('payrollUsers', 1)
        );
    }
}