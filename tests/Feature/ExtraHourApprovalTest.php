<?php

namespace Tests\Feature;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use App\Services\ExtraHourApprovalService;
use App\Services\ExtraHourPendingQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regla canónica de autorización de tiempo extra:
 * un día cuenta y es accionable SOLO si tiene tiempo extra, está pendiente, tiene
 * nivel de aprobación asignado, ese nivel es mío, el colaborador pertenece al grupo
 * de ese nivel y nadie ha decidido todavía en ese nivel.
 * Los días con nivel NULL ("sin flujo de autorización") no cuentan y no se deciden.
 */
class ExtraHourApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Payroll $payroll;

    private User $jefe;        // aprobador del nivel 1 (grupo MANUFACTURA)
    private User $direccion;   // aprobador del nivel 2 (grupo MANUFACTURA)
    private User $ajeno;       // aprobador de otra área

    private User $empleado1;
    private User $empleado2;
    private User $sinGrupo;

    private ExtraHourApprovalLevel $nivel1;
    private ExtraHourApprovalLevel $nivel2;

    protected function setUp(): void
    {
        parent::setUp();

        // El catálogo de permisos exige `category` (NOT NULL)
        $permission = Permission::where('name', 'Ver incidencias')->where('guard_name', 'web')->first();
        if (!$permission) {
            $permission = new Permission();
            $permission->name = 'Ver incidencias';
            $permission->guard_name = 'web';
            $permission->category = 'INCIDENCIAS';
            $permission->save();
        }

        $this->payroll = Payroll::create([
            'start_date' => now()->startOfDay()->toDateString(),
            'biweekly' => 43,
            'is_active' => true,
        ]);

        $this->jefe = $this->makeUser('Jefe Manufactura', 'Manufactura');
        $this->direccion = $this->makeUser('Direccion', 'Direccion');
        $this->ajeno = $this->makeUser('Jefe Proyectos', 'Proyectos');
        $this->empleado1 = $this->makeUser('Operador', 'Manufactura');
        $this->empleado2 = $this->makeUser('Operador', 'Manufactura');
        $this->sinGrupo = $this->makeUser('Operador', 'Manufactura');

        $group = ExtraHourApprovalGroup::create([
            'payroll_id' => $this->payroll->id,
            'name' => 'MANUFACTURA',
        ]);
        $group->employees()->sync([$this->empleado1->id, $this->empleado2->id]);

        $this->nivel1 = ExtraHourApprovalLevel::create([
            'payroll_id' => $this->payroll->id,
            'approval_group_id' => $group->id,
            'level' => 1,
            'name' => 'Jefe Manufactura',
        ]);
        $this->nivel1->approvers()->sync([$this->jefe->id]);

        $this->nivel2 = ExtraHourApprovalLevel::create([
            'payroll_id' => $this->payroll->id,
            'approval_group_id' => $group->id,
            'level' => 2,
            'name' => 'Direccion',
        ]);
        $this->nivel2->approvers()->sync([$this->direccion->id]);
    }

    private function makeUser(string $position, string $department): User
    {
        return User::factory()->create([
            'is_active' => true,
            'employees_in_charge' => [],
            'org_props' => ['position' => $position, 'department' => $department],
        ]);
    }

    private function day(User $user, int $offset = 0, array $attrs = []): PayrollUser
    {
        return PayrollUser::create(array_merge([
            'payroll_id' => $this->payroll->id,
            'user_id' => $user->id,
            'date' => $this->payroll->start_date->copy()->addDays($offset)->toDateString(),
            'extra_hours' => 2,
            'extra_minutes' => 0,
            'extra_hour_status' => 'pending',
            'current_approval_level_id' => $this->nivel1->id,
        ], $attrs));
    }

    private function pending(): ExtraHourPendingQuery
    {
        return app(ExtraHourPendingQuery::class);
    }

    /**
     * Grupos de la catorcena con la forma que consume evaluateIncidence():
     * [{id, employee_ids, levels: [{id, level, approvers: [{id}]}]}]
     */
    private function approvalGroupsPayload(): array
    {
        return [[
            'id' => 1,
            'employee_ids' => [$this->empleado1->id, $this->empleado2->id],
            'levels' => [
                ['id' => $this->nivel1->id, 'level' => 1, 'approvers' => [['id' => $this->jefe->id]]],
                ['id' => $this->nivel2->id, 'level' => 2, 'approvers' => [['id' => $this->direccion->id]]],
            ],
        ]];
    }

    /**
     * Registra la aprobación del nivel 1 (Jefe Manufactura) para un día, como si
     * ya hubiera pasado por su turno (necesario para que el día sea decidible por
     * el nivel 2).
     */
    private function approveAtLevelOne(PayrollUser $day): void
    {
        ExtraHourApprovalDecision::create([
            'payroll_user_id' => $day->id,
            'approval_level_id' => $this->nivel1->id,
            'approver_id' => $this->jefe->id,
            'status' => 'approved',
            'decided_at' => now(),
        ]);
    }

    public function test_solo_cuentan_los_dias_con_nivel_propio_y_sin_decision(): void
    {
        $this->day($this->empleado1, 0);                       // nivel 1 → mío
        $this->day($this->empleado2, 0);                       // nivel 1 → mío
        // Nivel 2 → dirección; sólo cuenta con el nivel 1 ya aprobado
        $this->approveAtLevelOne(
            $this->day($this->empleado2, 1, ['current_approval_level_id' => $this->nivel2->id])
        );
        $this->day($this->empleado1, 2, ['current_approval_level_id' => null]);  // sin flujo (rescatable)
        $this->day($this->sinGrupo, 0, ['current_approval_level_id' => null]);   // sin flujo y sin grupo
        $this->day($this->empleado1, 3, ['extra_hours' => 0, 'extra_minutes' => 0]); // residuo sin horas

        $this->assertSame(2, $this->pending()->pendingQuery($this->jefe, $this->payroll->id)->count());
        $this->assertSame(1, $this->pending()->pendingQuery($this->direccion, $this->payroll->id)->count());
        $this->assertSame(0, $this->pending()->pendingQuery($this->ajeno, $this->payroll->id)->count());

        $summary = $this->pending()->summaryForPayroll($this->jefe, $this->payroll);
        $this->assertSame(2, $summary['pending_days']);
        $this->assertSame(2, $summary['pending_employees']);
        $this->assertSame(1, $summary['orphan_days']);
        $this->assertSame(0, $summary['orphan_uncovered_days']);

        // El día sin flujo del colaborador que NO está en ningún grupo queda fuera de su scope
        $this->assertSame(1, $this->pending()->orphanQuery($this->jefe, $this->payroll->id)->count());
    }

    public function test_un_dia_sin_flujo_nunca_es_accionable_ni_decidible(): void
    {
        $orphan = $this->day($this->sinGrupo, 0, ['current_approval_level_id' => null]);

        $this->assertFalse(app(ExtraHourApprovalService::class)->canAct($orphan, $this->jefe));

        $this->actingAs($this->jefe)
            ->post(route('payrolls.extra-hours-decide', $this->payroll->id), [
                'payroll_user_id' => $orphan->id,
                'status' => 'approved',
            ])
            ->assertStatus(422);

        $fresh = $orphan->fresh();
        $this->assertSame('pending', $fresh->extra_hour_status);
        $this->assertNull($fresh->current_approval_level_id);
    }

    public function test_un_dia_sin_flujo_de_colaborador_con_grupo_se_rescata_al_decidir(): void
    {
        $orphan = $this->day($this->empleado1, 0, ['current_approval_level_id' => null]);

        $this->actingAs($this->jefe)
            ->post(route('payrolls.extra-hours-decide', $this->payroll->id), [
                'payroll_user_id' => $orphan->id,
                'status' => 'approved',
                'approved_extra_hours' => 2,
                'approved_extra_minutes' => 0,
            ])
            ->assertStatus(200);

        $fresh = $orphan->fresh();
        $this->assertSame('pending', $fresh->extra_hour_status);
        $this->assertSame($this->nivel2->id, $fresh->current_approval_level_id);
        $this->assertDatabaseHas('extra_hour_approval_decisions', [
            'payroll_user_id' => $orphan->id,
            'approval_level_id' => $this->nivel1->id,
            'approver_id' => $this->jefe->id,
            'status' => 'approved',
        ]);
    }

    public function test_inicializar_workflow_no_rebobina_un_registro_en_nivel_2(): void
    {
        $row = $this->day($this->empleado2, 0, ['current_approval_level_id' => $this->nivel2->id]);

        app(ExtraHourApprovalService::class)->initializeWorkflow($row);

        // Antes, cada recálculo de checadas devolvía el día al nivel 1
        $this->assertSame($this->nivel2->id, $row->fresh()->current_approval_level_id);
    }

    public function test_reconcile_rescata_solo_los_dias_con_grupo_y_limpia_residuos(): void
    {
        $rescatable = $this->day($this->empleado1, 0, ['current_approval_level_id' => null]);
        $sinGrupo = $this->day($this->sinGrupo, 0, ['current_approval_level_id' => null]);
        $residuo = $this->day($this->empleado2, 0, ['extra_hours' => 0, 'extra_minutes' => 0]);

        // Dry-run: informa pero no escribe
        $this->artisan('extra-hours:reconcile', ['--payroll' => $this->payroll->id])->assertSuccessful();
        $this->assertNull($rescatable->fresh()->current_approval_level_id);
        $this->assertSame('pending', $residuo->fresh()->extra_hour_status);

        // Aplicar
        $this->artisan('extra-hours:reconcile', ['--payroll' => $this->payroll->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame($this->nivel1->id, $rescatable->fresh()->current_approval_level_id);
        $this->assertNull($sinGrupo->fresh()->current_approval_level_id);
        $this->assertSame('none', $residuo->fresh()->extra_hour_status);
        $this->assertSame(1, $this->pending()->pendingQuery($this->jefe, $this->payroll->id)->count());
    }

    public function test_clear_extra_time_limpia_tambien_el_estado_del_flujo(): void
    {
        $row = $this->day($this->empleado1);

        $this->actingAs($this->jefe)
            ->put(route('payroll-users.clear-extra-time'), [
                'user_id' => $this->empleado1->id,
                'payroll_id' => $this->payroll->id,
                'date' => $row->date->toDateString(),
            ])
            ->assertStatus(200);

        $fresh = $row->fresh();
        $this->assertNull($fresh->extra_hours);
        $this->assertSame('none', $fresh->extra_hour_status);
        $this->assertNull($fresh->current_approval_level_id);
        $this->assertSame(0, $this->pending()->pendingQuery($this->jefe, $this->payroll->id)->count());
    }


    public function test_evaluate_incidence_marca_turno_propio_otro_nivel_y_sin_flujo(): void
    {
        $groups = $this->approvalGroupsPayload();

        $enTurno = $this->day($this->empleado1, 0);
        $flag = $this->pending()->evaluateIncidence($enTurno, $groups, $this->jefe->id);
        $this->assertTrue($flag['can_act']);
        $this->assertFalse($flag['orphan']);

        $deDireccion = $this->day($this->empleado2, 1, ['current_approval_level_id' => $this->nivel2->id]);
        $flag = $this->pending()->evaluateIncidence($deDireccion, $groups, $this->jefe->id);
        $this->assertFalse($flag['can_act']);
        $this->assertSame('Esperando decisión de otro nivel', $flag['reason']);

        $sinFlujo = $this->day($this->empleado1, 2, ['current_approval_level_id' => null]);
        $flag = $this->pending()->evaluateIncidence($sinFlujo, $groups, $this->jefe->id);
        $this->assertFalse($flag['can_act']);
        $this->assertTrue($flag['orphan']);
    }

    public function test_el_payload_de_la_nomina_expone_el_resumen_canonico(): void
    {
        $this->day($this->empleado1, 0);   // en turno del jefe
        $this->day($this->empleado2, 0);   // en turno del jefe
        $this->day($this->empleado2, 1, ['current_approval_level_id' => $this->nivel2->id]);
        $this->day($this->empleado1, 2, ['current_approval_level_id' => null]); // sin flujo

        $response = $this->actingAs($this->jefe)->get(route('payrolls.show', $this->payroll->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Show')
            // Mismo número que el badge de la barra superior
            ->where('extraTimeSummary.pending_days', 2)
            ->where('extraTimeSummary.pending_employees', 2)
            ->where('extraTimeSummary.orphan_days', 1)
            // Un aprobador sin permiso global ya ve a los colaboradores de su grupo
            // (2 empleados) + a sí mismo
            ->has('payrollUsers', 3)
        );

        // El resumen canónico del payload coincide con el conteo de días accionables
        $this->assertSame(2, $this->pending()->pendingQuery($this->jefe, $this->payroll->id)->count());
    }

    public function test_cerrar_catorcena_arrastra_la_jerarquia(): void
    {
        $this->artisan('payrolls:close')->assertSuccessful();

        $nueva = Payroll::where('biweekly', 44)->first();
        $this->assertNotNull($nueva);

        $grupo = $nueva->approvalGroups()->first();
        $this->assertNotNull($grupo);
        // Se arrastran los 2 niveles con sus aprobadores
        $this->assertSame(2, $grupo->levels()->count());
        $this->assertSame(
            [$this->jefe->id],
            $grupo->levels()->orderBy('level')->first()->approvers()->pluck('users.id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertSame(0, (int) $this->payroll->fresh()->is_active);
    }

    /**
     * Requisito del panel de tiempo extra: un aprobador (aunque sea del 2º o 3er
     * nivel) puede autorizar a los colaboradores de SU GRUPO sin tenerlos como
     * "Empleados a cargo" y sin que su catorcena esté entre las visibles para él.
     */
    public function test_el_segundo_nivel_aprueba_a_su_grupo_sin_ser_personal_a_cargo(): void
    {
        // El nivel 1 aprueba el día → avanza al nivel 2 (donde está $direccion)
        $day = $this->day($this->empleado1, 0);

        $this->actingAs($this->jefe)
            ->post(route('payrolls.extra-hours-decide', $this->payroll->id), [
                'payroll_user_id' => $day->id,
                'status' => 'approved',
                'approved_extra_hours' => 2,
                'approved_extra_minutes' => 0,
            ])
            ->assertStatus(200);

        $this->assertSame($this->nivel2->id, (int) $day->fresh()->current_approval_level_id);

        // El 2º nivel NO tiene a esos colaboradores como personal a cargo
        $this->assertSame([], $this->direccion->fresh()->employees_in_charge);

        // El panel de la catorcena (misma fuente que el modal) sí se los muestra
        $payload = $this->actingAs($this->direccion)
            ->get(route('payrolls.extra-time-data', $this->payroll->id))
            ->assertStatus(200);

        $rows = collect($payload->json('payrollUsers'));
        $this->assertContains($this->empleado1->id, $rows->pluck('user.id')->all());

        $incidence = $rows->flatMap(fn ($row) => $row['incidences'])->firstWhere('id', $day->id);
        $this->assertNotNull($incidence);
        $this->assertTrue($incidence['approval']['can_act']);

        // Y puede decidirlo (el permiso sale del grupo de aprobación, no de la catorcena visible)
        $this->actingAs($this->direccion)
            ->post(route('payrolls.extra-hours-decide', $this->payroll->id), [
                'payroll_user_id' => $day->id,
                'status' => 'approved',
                'approved_extra_hours' => 2,
                'approved_extra_minutes' => 0,
            ])
            ->assertStatus(200);

        $this->assertSame('approved', $day->fresh()->extra_hour_status);
    }

    /**
     * La regla canónica exige el nivel previo aprobado (igual que decide()): antes
     * el badge / los KPI contaban estos días como "en tu turno" y al decidirlos el
     * backend respondía "El nivel anterior aún no ha sido aprobado".
     */
    public function test_un_dia_de_nivel_2_con_el_nivel_1_pendiente_no_es_mi_turno(): void
    {
        $day = $this->day($this->empleado1, 0, ['current_approval_level_id' => $this->nivel2->id]);

        // No cuenta en los indicadores del 2º nivel
        $this->assertSame(0, $this->pending()->pendingQuery($this->direccion, $this->payroll->id)->count());

        $flag = $this->pending()->evaluateIncidence($day, $this->approvalGroupsPayload(), $this->direccion->id);
        $this->assertFalse($flag['can_act']);
        $this->assertTrue($flag['is_my_employee']);
        $this->assertSame('Esperando aprobación del nivel previo', $flag['reason']);

        // El servidor rechaza la decisión con el mismo motivo
        $this->actingAs($this->direccion)
            ->post(route('payrolls.extra-hours-decide', $this->payroll->id), [
                'payroll_user_id' => $day->id,
                'status' => 'approved',
                'approved_extra_hours' => 2,
                'approved_extra_minutes' => 0,
            ])
            ->assertStatus(422);

        $this->assertSame('pending', $day->fresh()->extra_hour_status);
    }
}
