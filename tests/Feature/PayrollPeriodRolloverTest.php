<?php

namespace Tests\Feature;

use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cierre de catorcena + auto-roll de checadas tardías.
 *
 * Escenario real que cubren estas pruebas: el cron `payrolls:close` no corre y
 * las checadas del período nuevo se estampan en la catorcena vencida porque el
 * sincronizador cae al respaldo `is_active`. La catorcena nueva se queda sin
 * registros y la pantalla pinta "Falta injustificada" en días que sí se trabajó.
 */
class PayrollPeriodRolloverTest extends TestCase
{
    use RefreshDatabase;

    private function makePayroll(string $startDate, int $biweekly, bool $active = true): Payroll
    {
        return Payroll::create([
            'start_date' => $startDate,
            'biweekly' => $biweekly,
            'is_active' => $active,
        ]);
    }

    private function makeEmployee(string $code): User
    {
        return User::factory()->create([
            'code' => $code,
            'is_active' => true,
            'employees_in_charge' => [],
            'org_props' => [
                'position' => 'Operador',
                'department' => 'Manufactura',
                'work_shift' => 'Turno 3 (09:00 - 18:00)',
                'vacations' => 12,
            ],
        ]);
    }

    /**
     * Grupo MANUFACTURA con dos niveles de autorización (como en producción).
     *
     * @return array{group: ExtraHourApprovalGroup, level1: ExtraHourApprovalLevel, level2: ExtraHourApprovalLevel}
     */
    private function makeHierarchy(Payroll $payroll, User $employee, User $approver): array
    {
        $group = ExtraHourApprovalGroup::create([
            'payroll_id' => $payroll->id,
            'name' => 'MANUFACTURA',
        ]);
        $group->employees()->sync([$employee->id]);

        $level1 = ExtraHourApprovalLevel::create([
            'payroll_id' => $payroll->id,
            'approval_group_id' => $group->id,
            'level' => 1,
            'name' => 'Jefe Manufactura',
        ]);
        $level1->approvers()->sync([$approver->id]);

        $level2 = ExtraHourApprovalLevel::create([
            'payroll_id' => $payroll->id,
            'approval_group_id' => $group->id,
            'level' => 2,
            'name' => 'Direccion',
        ]);
        $level2->approvers()->sync([$approver->id]);

        return ['group' => $group, 'level1' => $level1, 'level2' => $level2];
    }

    public function test_close_command_relinks_attendance_that_had_already_landed_in_the_new_window()
    {
        $previous = $this->makePayroll('2026-10-27', 52);
        $employee = $this->makeEmployee('EMP-100');
        $approver = $this->makeEmployee('EMP-101');
        $levels = $this->makeHierarchy($previous, $employee, $approver);

        // Día del período nuevo (Nov 10) estampado en la catorcena vencida.
        $day = PayrollUser::create([
            'payroll_id' => $previous->id,
            'user_id' => $employee->id,
            'date' => '2026-11-10',
            'check_in' => '08:00',
            'check_out' => '18:00',
            'extra_hours' => 2,
            'extra_minutes' => 0,
            'extra_hour_status' => 'pending',
            'current_approval_level_id' => $levels['level1']->id,
        ]);

        $this->artisan('payrolls:close')->assertExitCode(0);

        $opened = Payroll::where('biweekly', 53)->firstOrFail();
        $this->assertSame('2026-11-10', $opened->start_date->toDateString());
        $this->assertTrue((bool) $opened->is_active);
        $this->assertFalse((bool) $previous->fresh()->is_active);

        $day->refresh();
        $this->assertSame($opened->id, $day->payroll_id);

        // El nivel de aprobación se remapea al equivalente del período nuevo.
        $newLevel1 = ExtraHourApprovalLevel::where('payroll_id', $opened->id)->where('level', 1)->firstOrFail();
        $this->assertSame($newLevel1->id, $day->current_approval_level_id);
        $this->assertNotSame($levels['level1']->id, $day->current_approval_level_id);
    }

    public function test_biotime_punch_after_the_active_window_opens_the_next_period_automatically()
    {
        $previous = $this->makePayroll('2026-10-27', 52);
        $employee = $this->makeEmployee('EMP-300');
        $approver = $this->makeEmployee('EMP-301');
        $this->makeHierarchy($previous, $employee, $approver);

        $punch = urlencode('2026-11-11 08:05:00');

        $this->get("/api/process-transaction/{$punch}/EMP-300")->assertStatus(200);

        // AUTO-ROLL: la catorcena siguiente se abre reutilizando la lógica del cierre.
        $opened = Payroll::where('biweekly', 53)->first();
        $this->assertNotNull($opened, 'Una checada posterior al fin de la catorcena activa debe abrir la siguiente.');
        $this->assertSame('2026-11-10', $opened->start_date->toDateString());
        $this->assertTrue((bool) $opened->is_active);
        $this->assertFalse((bool) $previous->fresh()->is_active);

        // Y la jerarquía de autorización viaja junto con el cierre.
        $this->assertDatabaseHas('extra_hour_approval_groups', [
            'payroll_id' => $opened->id,
            'name' => 'MANUFACTURA',
        ]);

        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $employee->id,
            'payroll_id' => $opened->id,
            'check_in' => '08:05',
        ]);
    }

    public function test_biotime_punch_before_the_active_window_keeps_the_active_period_as_fallback()
    {
        $current = $this->makePayroll('2026-11-10', 53);
        $employee = $this->makeEmployee('EMP-500');
        $approver = $this->makeEmployee('EMP-501');
        $this->makeHierarchy($current, $employee, $approver);

        // Checada nocturna del día previo al inicio del período: ninguna ventana
        // la contiene, así que se conserva el respaldo histórico (no se abren
        // catorcenas hacia el pasado).
        $punch = urlencode('2026-11-09 20:10:00');

        $this->get("/api/process-transaction/{$punch}/EMP-500")->assertStatus(200);

        $this->assertSame(1, Payroll::count());

        $this->assertDatabaseHas('payroll_user', [
            'user_id' => $employee->id,
            'payroll_id' => $current->id,
        ]);
    }

    public function test_manual_attendance_relinks_a_day_to_the_period_that_owns_it()
    {
        $previous = $this->makePayroll('2026-10-27', 52, false);
        $current = $this->makePayroll('2026-11-10', 53);
        $admin = $this->makeEmployee('EMP-600');
        $employee = $this->makeEmployee('EMP-601');
        $oldLevels = $this->makeHierarchy($previous, $employee, $admin);
        $newLevels = $this->makeHierarchy($current, $employee, $admin);

        $day = PayrollUser::create([
            'payroll_id' => $previous->id,
            'user_id' => $employee->id,
            'date' => '2026-11-10 00:00:00',
            'extra_hours' => 2,
            'extra_hour_status' => 'pending',
            'current_approval_level_id' => $oldLevels['level1']->id,
        ]);

        // La pantalla manda el payroll_id viejo (el período que estaba abierto).
        $this->actingAs($admin)->post(route('payroll-users.set-attendance'), [
            'date' => '2026-11-10 00:00:00',
            'user_id' => $employee->id,
            'payroll_id' => $previous->id,
            'check_in' => '09:00',
            'check_out' => '20:00',
        ])->assertStatus(200);

        $day->refresh();
        $this->assertSame($current->id, $day->payroll_id, 'La captura manual debe ligar el día a la catorcena que lo contiene.');
        $this->assertSame($newLevels['level1']->id, $day->current_approval_level_id, 'El nivel de aprobación debe apuntar al equivalente del período nuevo.');
        $this->assertSame('09:00', substr((string) $day->check_in, 0, 5));
        $this->assertSame(2, (int) $day->extra_hours);
    }

    public function test_reassign_orphans_command_reports_in_dry_run_and_moves_with_apply()
    {
        $previous = $this->makePayroll('2026-10-27', 52, false);
        $current = $this->makePayroll('2026-11-10', 53);
        $employee = $this->makeEmployee('EMP-700');
        $approver = $this->makeEmployee('EMP-701');
        $oldLevels = $this->makeHierarchy($previous, $employee, $approver);
        $newLevels = $this->makeHierarchy($current, $employee, $approver);

        $day = PayrollUser::create([
            'payroll_id' => $previous->id,
            'user_id' => $employee->id,
            'date' => '2026-11-12',
            'extra_hours' => 1,
            'extra_hour_status' => 'pending',
            'current_approval_level_id' => $oldLevels['level1']->id,
        ]);

        // Sin --apply sólo reporta.
        $this->artisan('payrolls:reassign-orphans', ['payroll' => $current->id])->assertExitCode(0);
        $this->assertSame($previous->id, $day->fresh()->payroll_id);

        // Con --apply re-liga y remapea el nivel.
        $this->artisan('payrolls:reassign-orphans', [
            'payroll' => $current->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $day->refresh();
        $this->assertSame($current->id, $day->payroll_id);
        $this->assertSame($newLevels['level1']->id, $day->current_approval_level_id);
    }

    public function test_close_command_fails_gracefully_without_active_payroll()
    {
        $this->makePayroll('2026-10-27', 52, false);

        $this->artisan('payrolls:close')->assertExitCode(1);

        // No se creó ninguna catorcena nueva.
        $this->assertSame(1, Payroll::count());
    }
}
