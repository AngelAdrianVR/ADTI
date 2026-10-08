<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VacationRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class VacationRequestControllerTest extends TestCase
{
    use RefreshDatabase; // SQLite en memoria

    protected $admin;
    protected $manager;
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear el permiso global para ver/gestionar todas las solicitudes
        $permission = Permission::where('name', 'Gestionar cualquier solicitud de vacaciones')->where('guard_name', 'web')->first();
        if (!$permission) {
            $permission = new Permission();
            $permission->name = 'Gestionar cualquier solicitud de vacaciones';
            $permission->guard_name = 'web';
            $permission->category = 'CONFIGURACIONES';
            $permission->save();
        }

        // 2. Crear un Empleado Estándar (Con 12 días de vacaciones)
        $this->employee = User::factory()->create([
            'org_props' => [
                'vacations' => 12,
                'entry_date' => now()->subYears(2)->toDateString(),
                'position' => 'Operador',
                'department' => 'Producción',
            ]
        ]);

        // 3. Crear un Gerente/Supervisor (Tiene al empleado anterior a su cargo)
        $this->manager = User::factory()->create([
            'employees_in_charge' => [$this->employee->id],
            'org_props' => [
                'vacations' => 15,
                'position' => 'Supervisor',
                'department' => 'Producción',
            ]
        ]);

        // 4. Crear un Administrador de RH (Con permiso global)
        $this->admin = User::factory()->create([
            'org_props' => [
                'vacations' => 20,
                'position' => 'Gerente RRHH',
                'department' => 'Recursos Humanos',
            ]
        ]);
        $this->admin->givePermissionTo('Gestionar cualquier solicitud de vacaciones');
    }

    public function test_admin_can_view_all_vacation_requests()
    {
        // Creamos una solicitud del empleado
        VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'days_requested' => 3,
            'status' => 'Pendiente'
        ]);

        // Creamos una solicitud del gerente
        VacationRequest::create([
            'user_id' => $this->manager->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'days_requested' => 3,
            'status' => 'Pendiente'
        ]);

        // El admin debe poder ver AMBAS peticiones
        $response = $this->actingAs($this->admin)->get(route('vacation-requests.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('VacationRequest/Index')
            ->has('requests', 2)
        );
    }

    public function test_manager_can_view_only_subordinates_requests()
    {
        // Solicitud del empleado subordinado
        VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'days_requested' => 3,
            'status' => 'Pendiente'
        ]);

        // Solicitud de un tercero no subordinado (ej. el admin)
        VacationRequest::create([
            'user_id' => $this->admin->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'days_requested' => 3,
            'status' => 'Pendiente'
        ]);

        // El manager no tiene permiso global, solo subordinados. Debería ver solo 1 (la del empleado)
        $response = $this->actingAs($this->manager)->get(route('vacation-requests.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('VacationRequest/Index')
            ->has('requests', 1)
        );
    }

    public function test_employee_can_create_vacation_request()
    {
        $response = $this->actingAs($this->employee)->post(route('vacation-requests.store'), [
            'start_date' => now()->addDays(20)->toDateString(), // 20 días de anticipación (Cumple la regla > 14)
            'end_date' => now()->addDays(24)->toDateString(),
            'days_requested' => 5, // Tiene 12 de saldo, 5 es válido
            'employee_notes' => 'Viaje familiar'
        ]);

        $response->assertSessionHas('success', 'Solicitud de vacaciones enviada correctamente.');

        $this->assertDatabaseHas('vacation_requests', [
            'user_id' => $this->employee->id,
            'days_requested' => 5,
            'status' => 'Pendiente',
        ]);
    }

    public function test_cannot_create_request_with_insufficient_notice()
    {
        // Intentar pedir vacaciones para dentro de 10 días (la regla exige mínimo 15 días, o sea > 14)
        $response = $this->actingAs($this->employee)->post(route('vacation-requests.store'), [
            'start_date' => now()->addDays(10)->toDateString(), 
            'end_date' => now()->addDays(12)->toDateString(),
            'days_requested' => 2,
        ]);

        // Debe fallar la validación en la fecha de inicio
        $response->assertSessionHasErrors(['start_date']);
        $this->assertDatabaseCount('vacation_requests', 0);
    }

    public function test_cannot_create_request_if_balance_is_insufficient()
    {
        // El empleado tiene 12 días de saldo. Intentaremos pedir 15.
        $response = $this->actingAs($this->employee)->post(route('vacation-requests.store'), [
            'start_date' => now()->addDays(20)->toDateString(), 
            'end_date' => now()->addDays(35)->toDateString(),
            'days_requested' => 15, // Superior al saldo
        ]);

        $response->assertSessionHasErrors(['days_requested']);
        $this->assertDatabaseCount('vacation_requests', 0);
    }

    public function test_cannot_create_request_if_locked_days_deplete_balance()
    {
        // El empleado tiene 12 días. Ya pidió 10 días a futuro que están aprobados o pendientes.
        VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(30)->toDateString(),
            'end_date' => now()->addDays(40)->toDateString(),
            'days_requested' => 10,
            'status' => 'Aprobada'
        ]);

        // Saldo real restante = 2. Intentaremos pedir 3 más.
        $response = $this->actingAs($this->employee)->post(route('vacation-requests.store'), [
            'start_date' => now()->addDays(50)->toDateString(), 
            'end_date' => now()->addDays(53)->toDateString(),
            'days_requested' => 3, 
        ]);

        // Debe fallar porque 10 (bloqueados) + 3 (nuevos) = 13 > 12 (saldo total)
        $response->assertSessionHasErrors(['days_requested']);
    }

    public function test_employee_can_cancel_own_pending_request()
    {
        $request = VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'days_requested' => 3,
            'status' => 'Pendiente'
        ]);

        $response = $this->actingAs($this->employee)->put(route('vacation-requests.cancel', $request->id));

        $response->assertSessionHas('success');
        $this->assertEquals('Cancelada', $request->fresh()->status);
    }

    public function test_employee_cannot_cancel_others_request()
    {
        $request = VacationRequest::create([
            'user_id' => $this->manager->id, // Petición de otra persona
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'days_requested' => 3,
            'status' => 'Pendiente'
        ]);

        $response = $this->actingAs($this->employee)->put(route('vacation-requests.cancel', $request->id));

        // Debe lanzar un error 403 Forbidden
        $response->assertStatus(403);
        $this->assertEquals('Pendiente', $request->fresh()->status);
    }

    public function test_admin_can_approve_request()
    {
        $request = VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'days_requested' => 3,
            'status' => 'Pendiente'
        ]);

        $response = $this->actingAs($this->admin)->put(route('vacation-requests.approve', $request->id), [
            'reviewer_notes' => 'Aprobadas, buen viaje.'
        ]);

        $response->assertSessionHas('success');
        
        $request->refresh();
        $this->assertEquals('Aprobada', $request->status);
        $this->assertEquals($this->admin->id, $request->resolved_by);
        $this->assertEquals('Aprobadas, buen viaje.', $request->reviewer_notes);
    }

    public function test_admin_can_reject_request_with_mandatory_notes()
    {
        $request = VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
            'days_requested' => 3,
            'status' => 'Pendiente'
        ]);

        // 1. Intentar rechazar sin notas (debe fallar por validación)
        $responseFail = $this->actingAs($this->admin)->put(route('vacation-requests.reject', $request->id), [
            'reviewer_notes' => ''
        ]);
        $responseFail->assertSessionHasErrors(['reviewer_notes']);

        // 2. Rechazar correctamente con justificación
        $responseSuccess = $this->actingAs($this->admin)->put(route('vacation-requests.reject', $request->id), [
            'reviewer_notes' => 'No es posible por cierre de trimestre.'
        ]);
        
        $responseSuccess->assertSessionHas('success');
        
        $request->refresh();
        $this->assertEquals('Rechazada', $request->status);
        $this->assertEquals($this->admin->id, $request->resolved_by);
    }

    public function test_get_pending_count_api_returns_correct_number()
    {
        // Crear 1 pendiente del empleado
        VacationRequest::create([
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'days_requested' => 1,
            'status' => 'Pendiente'
        ]);

        // Crear 1 pendiente del manager
        VacationRequest::create([
            'user_id' => $this->manager->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'days_requested' => 1,
            'status' => 'Pendiente'
        ]);

        // 1. El Admin (permiso global) debería contar TODAS (2 pendientes)
        $responseAdmin = $this->actingAs($this->admin)->getJson(route('vacation-requests.pending-count'));
        $responseAdmin->assertJson(['count' => 2]);

        // 2. El Manager (solo a cargo del employee) debería contar SOLO LA DEL EMPLEADO (1 pendiente)
        $responseManager = $this->actingAs($this->manager)->getJson(route('vacation-requests.pending-count'));
        $responseManager->assertJson(['count' => 1]);

        // 3. El Empleado (sin gente a cargo ni permiso global) debería contar 0
        $responseEmployee = $this->actingAs($this->employee)->getJson(route('vacation-requests.pending-count'));
        $responseEmployee->assertJson(['count' => 0]);
    }
}