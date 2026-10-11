<?php

namespace Tests\Feature;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Diagnóstico: ¿qué operaciones de la UI borran (o reinician) las
 * autorizaciones de tiempo extra ya registradas en `extra_hour_approval_decisions`?
 *
 * Cada test parte de un día con T.E. TOTALMENTE APROBADO (decisiones nivel 1 y 2
 * con status=approved) y aplica una operación; luego cuenta las decisiones.
 */
class ExtraHourApprovalDeletionVectorsTest extends TestCase
{
    use RefreshDatabase;

    private Payroll $payroll;
    private User $admin;
    private User $jefe;
    private User $direccion;
    private User $empleado;
    private ExtraHourApprovalGroup $group;
    private ExtraHourApprovalLevel $nivel1;
    private ExtraHourApprovalLevel $nivel2;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'Ver incidencias', 'guard_name' => 'web', 'category' => 'INCIDENCIAS']);
        Role::create(['name' => 'Empleado', 'guard_name' => 'web']);

        $this->payroll = Payroll::create([
            'start_date' => now()->startOfDay()->toDateString(),
            'biweekly' => 43,
            'is_active' => true,
        ]);

        $this->admin = $this->makeUser('Admin', 'Sistemas');
        $this->jefe = $this->makeUser('Jefe', 'Manufactura');
        $this->direccion = $this->makeUser('Direccion', 'Direccion');
        $this->empleado = $this->makeUser('Operador', 'Manufactura');

        $this->group = ExtraHourApprovalGroup::create([
            'payroll_id' => $this->payroll->id,
            'name' => 'MANUFACTURA',
        ]);
        $this->group->employees()->sync([$this->empleado->id]);

        $this->nivel1 = ExtraHourApprovalLevel::create([
            'payroll_id' => $this->payroll->id,
            'approval_group_id' => $this->group->id,
            'level' => 1,
            'name' => 'Jefe Manufactura',
        ]);
        $this->nivel1->approvers()->sync([$this->jefe->id]);

        $this->nivel2 = ExtraHourApprovalLevel::create([
            'payroll_id' => $this->payroll->id,
            'approval_group_id' => $this->group->id,
            'level' => 2,
            'name' => 'Direccion',
        ]);
        $this->nivel2->approvers()->sync([$this->direccion->id]);
    }

    private function makeUser(string $position, string $department): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'employees_in_charge' => [],
            'org_props' => [
                'position' => $position,
                'department' => $department,
                'work_shift' => 'Turno 3 (09:00 - 18:00)',
                'entry_date' => '2024-01-01',
            ],
        ]);
        $user->syncRoles(['Empleado']);

        return $user;
    }

    /** Día con 2 h de T.E. y aprobación final ya registrada (niveles 1 y 2). */
    private function approvedDay(int $offset = 0): PayrollUser
    {
        $day = PayrollUser::create([
            'payroll_id' => $this->payroll->id,
            'user_id' => $this->empleado->id,
            'date' => $this->payroll->start_date->copy()->addDays($offset)->toDateString(),
            'check_in' => '09:00',
            'check_out' => '20:00',
            'incidence' => 'Día normal',
            'extra_hours' => 2,
            'extra_minutes' => 0,
            'extra_hour_status' => 'approved',
            'current_approval_level_id' => null,
            'approved_extra_hours' => 2,
            'approved_extra_minutes' => 0,
            'approved_by' => $this->direccion->id,
            'approved_at' => now(),
        ]);

        foreach ([[$this->nivel1, $this->jefe], [$this->nivel2, $this->direccion]] as [$nivel, $approver]) {
            ExtraHourApprovalDecision::create([
                'payroll_user_id' => $day->id,
                'approval_level_id' => $nivel->id,
                'approver_id' => $approver->id,
                'status' => 'approved',
                'decided_at' => now(),
            ]);
        }

        // SQLite no aplica el tipo DATE: el cast 'date' guarda "Y-m-d H:i:s" y
        // rompe los where('date', 'Y-m-d') del controlador (en MySQL sí coincide).
        // Normalizamos para reproducir el comportamiento de producción.
        \Illuminate\Support\Facades\DB::table('payroll_user')
            ->where('id', $day->id)
            ->update(['date' => $day->date->toDateString()]);

        return $day->refresh();
    }

    private function decisionsFor(PayrollUser $day): int
    {
        return ExtraHourApprovalDecision::where('payroll_user_id', $day->id)->count();
    }

    private function assertPreserved(PayrollUser $day, string $escenario): void
    {
        $this->assertSame(2, $this->decisionsFor($day), "{$escenario}: las decisiones NO deben borrarse");
        $this->assertSame('approved', $day->fresh()->extra_hour_status, "{$escenario}: el estado debe seguir aprobado");
    }

    // ─── Operaciones NO destructivas (esperado) ──────────────────────

    public function test_vincular_proyectos_conserva_decisiones(): void
    {
        $day = $this->approvedDay();
        $project = Project::create(['name' => 'P1', 'client' => 'C1']);

        $this->actingAs($this->admin)
            ->putJson(route('payroll-users.set-projects'), [
                'date' => $day->date->toDateString(),
                'user_id' => $this->empleado->id,
                'projects' => [[
                    'project_id' => $project->id,
                    'work_type' => 'internal',
                    'department_id' => null,
                    'extra_hours' => 2,
                    'extra_minutes' => 0,
                ]],
            ])
            ->assertOk();

        $this->assertPreserved($day, 'vincular proyecto');
    }

    public function test_desvincular_proyectos_conserva_decisiones(): void
    {
        $day = $this->approvedDay();

        $this->actingAs($this->admin)
            ->putJson(route('payroll-users.set-projects'), [
                'date' => $day->date->toDateString(),
                'user_id' => $this->empleado->id,
                'projects' => [],
            ])
            ->assertOk();

        $this->assertPreserved($day, 'desvincular proyecto');
    }

    public function test_set_project_legacy_conserva_decisiones(): void
    {
        $day = $this->approvedDay();
        $project = Project::create(['name' => 'P2', 'client' => 'C2']);

        $this->actingAs($this->admin)
            ->putJson(route('payroll-users.set-project'), [
                'date' => $day->date->toDateString(),
                'user_id' => $this->empleado->id,
                'project_id' => $project->id,
                'work_type' => 'internal',
            ])
            ->assertOk();

        $this->assertPreserved($day, 'set-project legacy');
    }

    public function test_actualizar_horas_entrada_salida_conserva_decisiones(): void
    {
        $day = $this->approvedDay();

        $this->actingAs($this->admin)
            ->put(route('payroll-users.update-attendance'), [
                'user_id' => $this->empleado->id,
                'payroll_id' => $this->payroll->id,
                'date' => $day->date->toDateString(),
                'check_in' => '09:00',
                'check_out' => '19:00',
            ]);

        $this->assertPreserved($day, 'editar checadas');
    }

    public function test_cambiar_costo_hora_extra_conserva_decisiones(): void
    {
        $day = $this->approvedDay();

        $this->actingAs($this->admin)
            ->post(route('payrolls.extra-hours-costs.save', $this->payroll->id), [
                'costs' => [[
                    'user_id' => null,
                    'range_type' => 'weekday',
                    'cost_per_hour' => 123.45,
                ]],
            ]);

        $this->assertPreserved($day, 'cambiar costo hora extra');
    }

    public function test_actualizar_info_de_usuario_conserva_decisiones(): void
    {
        $day = $this->approvedDay();

        $this->actingAs($this->admin)
            ->put(route('users.update', $this->empleado->id), [
                'name' => $this->empleado->name . ' Editado',
                'org_props' => [
                    'entry_date' => '2024-01-01',
                    'position' => 'Operador Senior',
                    'department' => 'Manufactura',
                    'work_shift' => 'Turno 1 (06:00 - 15:00)',
                ],
                'roles' => ['Empleado'],
            ]);

        $this->assertPreserved($day, 'actualizar info de usuario');
    }

    public function test_editar_checadas_sin_tiempo_extra_deja_el_dia_como_none(): void
    {
        // Lunes (offset 2) → dentro del turno 09:00-18:00 no hay tiempo extra
        $day = $this->approvedDay(2);

        $this->actingAs($this->admin)
            ->put(route('payroll-users.update-attendance'), [
                'user_id' => $this->empleado->id,
                'payroll_id' => $this->payroll->id,
                'date' => $day->date->toDateString(),
                'check_in' => '09:00',
                'check_out' => '18:00',
            ]);

        // Las decisiones NO se borran, pero el día deja de estar aprobado
        $this->assertSame(2, $this->decisionsFor($day));
        $this->assertSame('none', $day->fresh()->extra_hour_status,
            'Al recalcular 0 h extra, initializeWorkflow deja el día en none (la autorización "desaparece" de la vista)');
    }

    // ─── Operaciones DESTRUCTIVAS (confirmadas) ──────────────────────

    public function test_dejar_checadas_vacias_borra_decisiones(): void
    {
        $day = $this->approvedDay();

        $this->actingAs($this->admin)
            ->put(route('payroll-users.update-attendance'), [
                'user_id' => $this->empleado->id,
                'payroll_id' => $this->payroll->id,
                'date' => $day->date->toDateString(),
                'check_in' => '',
                'check_out' => '',
            ]);

        $this->assertNull(PayrollUser::find($day->id), 'El registro completo se borró');
        $this->assertSame(0, ExtraHourApprovalDecision::where('payroll_user_id', $day->id)->count(),
            'Las decisiones se borraron en cascada al borrar el payroll_user');
    }

    public function test_clear_extra_time_borra_decisiones(): void
    {
        $day = $this->approvedDay();

        $this->actingAs($this->admin)
            ->putJson(route('payroll-users.clear-extra-time'), [
                'user_id' => $this->empleado->id,
                'payroll_id' => $this->payroll->id,
                'date' => $day->date->toDateString(),
            ])
            ->assertOk();

        $this->assertSame(0, $this->decisionsFor($day), 'clearExtraTime borra las decisiones');
        $this->assertSame('none', $day->fresh()->extra_hour_status);
    }

    public function test_guardar_grupos_de_aprobacion_conserva_decisiones(): void
    {
        $day = $this->approvedDay();

        $this->actingAs($this->admin)
            ->post(route('payrolls.extra-hours-groups.save', $this->payroll->id), [
                'groups' => [[
                    'name' => 'MANUFACTURA',
                    'employee_ids' => [$this->empleado->id],
                    'levels' => [
                        ['name' => 'Jefe Manufactura', 'approver_ids' => [$this->jefe->id]],
                        ['name' => 'Direccion', 'approver_ids' => [$this->direccion->id]],
                    ],
                ]],
            ])
            ->assertSessionHasNoErrors();

        // Antes: recrear los grupos borraba TODAS las decisiones de la catorcena
        // (cascade grupo→nivel→decisión). Ahora los grupos y niveles se actualizan
        // por identidad (id, o nombre/número de nivel si el payload no trae ids),
        // así que las autorizaciones registradas se conservan.
        $this->assertPreserved($day, 'guardar la misma configuración de grupos');
    }
}
