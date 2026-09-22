<?php

namespace Tests\Feature;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollUserControllerTest extends TestCase
{
    use RefreshDatabase; // Usamos RefreshDatabase con SQLite en memoria

    protected $admin;
    protected $employee;
    protected $payroll;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario administrador
        $this->admin = User::factory()->create();

        // Crear un empleado con código específico para pruebas de BioTime
        $this->employee = User::factory()->create([
            'code' => 'EMP-001',
            'org_props' => [
                'entry_date' => now()->subYear()->toDateString(),
                'position' => 'Operador',
                'department' => 'Producción',
                'work_shift' => 'Diurno',
                'vacations' => 12, // Le damos 12 días de saldo inicial
            ]
        ]);

        // Crear una nómina activa actual
        $this->payroll = Payroll::create([
            'start_date' => Carbon::now()->startOfWeek()->toDateString(),
            'biweekly' => 1,
            'is_active' => true,
        ]);
    }

    public function test_can_store_manual_attendance()
    {
        // Formateamos la fecha explícitamente a como la guarda SQLite por defecto
        $date = Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00');

        $response = $this->actingAs($this->admin)->post(route('payroll-users.set-attendance'), [
            'date' => $date, // Pasamos el formato completo para que el controlador lo encuentre
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'check_in' => '09:00',
            'check_out' => '18:00',
        ]);

        $response->assertStatus(200);

        // Verificar que se haya guardado en la base de datos
        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'date' => $date, 
            'check_in' => '09:00',
            'check_out' => '18:00',
            'incidence' => 'Día normal',
            'checked_in_platform' => 1,
        ]);
    }

    public function test_updating_with_empty_hours_deletes_normal_day_record()
    {
        $date = Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00');

        // Crear un registro previo de "Día normal"
        PayrollUser::create([
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'date' => $date,
            'check_in' => '09:00',
            'check_out' => '18:00',
            'incidence' => 'Día normal'
        ]);

        // Enviar petición PUT con horas vacías
        $response = $this->actingAs($this->admin)->put(route('payroll-users.update-attendance'), [
            'date' => $date,
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'check_in' => null,
            'check_out' => null,
        ]);

        $response->assertStatus(200);

        // Verificar que el registro fue eliminado
        $this->assertDatabaseMissing('payroll_user', [
            'user_id' => $this->employee->id,
            'date' => $date,
        ]);
    }

    public function test_setting_vacation_incidence_deducts_vacation_balance()
    {
        $date = Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00');
        
        // Confirmar saldo inicial
        $this->assertEquals(12, $this->employee->org_props['vacations']);

        $response = $this->actingAs($this->admin)->put(route('payroll-users.set-incidence'), [
            'date' => $date,
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'incidence' => 'Vacaciones',
        ]);

        $response->assertStatus(200);

        // Refrescar modelo del empleado desde BD
        $this->employee->refresh();

        // Verificar que el saldo de vacaciones bajó de 12 a 11
        $this->assertEquals(11, $this->employee->org_props['vacations']);
        
        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $this->employee->id,
            'date' => $date, 
            'incidence' => 'Vacaciones',
        ]);
    }

    public function test_can_approve_extra_time()
    {
        $date = Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00');

        // Crear asistencia con tiempo extra pendiente
        PayrollUser::create([
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'date' => $date,
            'check_in' => '09:00',
            'check_out' => '20:30', // 2.5 horas extra
            'extra_hours' => 2,
            'extra_minutes' => 30,
        ]);

        // Aprobar 2 horas cerrado
        $response = $this->actingAs($this->admin)->put(route('payroll-users.approve-extra-time'), [
            'date' => $date,
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'approved_extra_hours' => 2,
            'approved_extra_minutes' => 0,
            'comments' => 'Se autorizan 2 horas por cierre de mes'
        ]);

        $response->assertStatus(302); // back() redirect

        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $this->employee->id,
            'date' => $date, 
            'approved_extra_hours' => 2,
            'approved_extra_minutes' => 0,
            'approved_by' => $this->admin->id,
        ]);

        // Verificar que el comentario se guardó
        $this->assertDatabaseHas('payroll_comments', [
            'user_id' => $this->employee->id,
            'date' => $date, 
            'comments' => 'Se autorizan 2 horas por cierre de mes'
        ]);
    }

    public function test_biotime_transaction_creates_entry()
    {
        // Simulamos la primera checada (entrada) que manda el reloj
        $punchTime = $this->payroll->start_date->copy()->setTime(8, 55)->format('Y-m-d H:i:s');
        $encodedTime = urlencode($punchTime);

        $response = $this->get("/api/process-transaction/{$encodedTime}/EMP-001");
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $this->employee->id,
            'date' => $this->payroll->start_date->format('Y-m-d 00:00:00'), // Formateado sin uso de clone
            'check_in' => '08:55',
            'check_out' => null, // Aún no sale
        ]);
    }

    public function test_biotime_transaction_anti_burst_protection()
    {
        $date = $this->payroll->start_date;
        
        // Creamos una entrada manual a las 09:00
        PayrollUser::create([
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'date' => $date->format('Y-m-d 00:00:00'),
            'check_in' => '09:00',
            'check_out' => null,
        ]);

        // Simular que el empleado volvió a poner su dedo en el checador a las 09:02 (2 minutos después)
        // La protección anti-ráfaga de 3 minutos debería ignorarlo y NO ponerlo como hora de salida.
        $punchTime = clone $date->setTime(9, 2)->format('Y-m-d H:i:s');
        $encodedTime = urlencode($punchTime);

        $this->get("/api/process-transaction/{$encodedTime}/EMP-001");

        // Verificamos que la salida siga siendo nula
        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $this->employee->id,
            'date' => $date->format('Y-m-d 00:00:00'), 
            'check_in' => '09:00',
            'check_out' => null, // Protegido exitosamente
        ]);
        
        // Ahora simulamos una checada válida a las 18:00
        $validOutTime = clone $date->setTime(18, 0)->format('Y-m-d H:i:s');
        $this->get("/api/process-transaction/" . urlencode($validOutTime) . "/EMP-001");

        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $this->employee->id,
            'date' => $date->format('Y-m-d 00:00:00'), 
            'check_out' => '18:00', // Guardado exitosamente
        ]);
    }

    public function test_can_clear_extra_time_for_multiple_days_at_once()
    {
        $start = Carbon::now()->startOfWeek();

        // Dos días con tiempo extra (que se limpiarán) y uno que debe conservarse
        $daysToClear = [$start->copy()->addDay(), $start->copy()->addDays(2)];
        $dayToKeep = $start->copy()->addDays(3);

        $records = [];
        foreach (array_merge($daysToClear, [$dayToKeep]) as $day) {
            $records[$day->toDateString()] = PayrollUser::create([
                'user_id' => $this->employee->id,
                'payroll_id' => $this->payroll->id,
                'date' => $day->format('Y-m-d 00:00:00'),
                'check_in' => '09:00',
                'check_out' => '19:00',
                'extra_hours' => 2,
                'extra_minutes' => 30,
                'approved_extra_hours' => 2,
                'approved_extra_minutes' => 0,
                'approved_by' => $this->admin->id,
                'approved_at' => now(),
            ]);
        }

        // Decisión de aprobación asociada a uno de los días con tiempo extra
        $level = ExtraHourApprovalLevel::create([
            'payroll_id' => $this->payroll->id,
            'level' => 1,
            'name' => 'Supervisor Directo',
        ]);

        ExtraHourApprovalDecision::create([
            'payroll_user_id' => $records[$daysToClear[0]->toDateString()]->id,
            'approval_level_id' => $level->id,
            'approver_id' => $this->admin->id,
            'status' => 'approved',
            'decided_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->put(route('payroll-users.clear-extra-time'), [
            'user_id' => $this->employee->id,
            'dates' => array_map(fn ($day) => $day->toDateString(), $daysToClear),
        ]);

        $response->assertOk(); // Sin cabecera X-Inertia la respuesta es JSON

        // Los días seleccionados quedan sin tiempo extra ni aprobación
        foreach ($daysToClear as $day) {
            $this->assertDatabaseHas('payroll_user', [
                'id' => $records[$day->toDateString()]->id,
                'extra_hours' => null,
                'extra_minutes' => null,
                'approved_extra_hours' => null,
                'approved_extra_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }

        // El día no seleccionado conserva su tiempo extra
        $this->assertDatabaseHas('payroll_user', [
            'id' => $records[$dayToKeep->toDateString()]->id,
            'extra_hours' => 2,
            'extra_minutes' => 30,
        ]);

        // Las decisiones de aprobación de los días limpiados fueron eliminadas
        $this->assertDatabaseCount('extra_hour_approval_decisions', 0);
    }

    public function test_can_clear_extra_time_for_a_single_day()
    {
        $date = Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00');

        PayrollUser::create([
            'user_id' => $this->employee->id,
            'payroll_id' => $this->payroll->id,
            'date' => $date,
            'check_in' => '09:00',
            'check_out' => '19:00',
            'extra_hours' => 1,
            'extra_minutes' => 15,
        ]);

        $response = $this->actingAs($this->admin)->put(route('payroll-users.clear-extra-time'), [
            'user_id' => $this->employee->id,
            'date' => $date,
        ]);

        $response->assertOk(); // Sin cabecera X-Inertia la respuesta es JSON

        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $this->employee->id,
            'date' => $date,
            'extra_hours' => null,
            'extra_minutes' => null,
        ]);
    }

    public function test_clear_extra_time_requires_a_date_or_dates()
    {
        $response = $this->actingAs($this->admin)->put(route('payroll-users.clear-extra-time'), [
            'user_id' => $this->employee->id,
        ]);

        $response->assertSessionHasErrors(['date', 'dates']);
    }
}