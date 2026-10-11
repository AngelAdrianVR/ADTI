<?php

namespace Tests\Feature;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guardar o copiar la configuración de grupos de aprobación NO debe destruir las
 * autorizaciones ya registradas (upsert por identidad).
 *
 * Regla implementada:
 *  - Grupo/nivel que sigue existiendo (mismo id; o mismo nombre/número de nivel
 *    cuando la petición no trae ids) → conserva sus decisiones y su avance.
 *  - Colaborador removido de un grupo → pierde SOLO sus decisiones en vuelo de
 *    ese grupo; las ya cerradas (`approved_at`) se conservan.
 *  - Nivel o grupo eliminado → se borra con sus decisiones (acción deliberada).
 */
class ExtraHourApprovalGroupSaveTest extends TestCase
{
    use RefreshDatabase;

    private Payroll $payroll;
    private Payroll $payrollAnterior;
    private User $admin;
    private User $jefe;
    private User $direccion;
    private User $empleado1;
    private User $empleado2;
    private User $empleado3;

    private ExtraHourApprovalGroup $grupoManufactura;
    private ExtraHourApprovalGroup $grupoAlmacen;
    private ExtraHourApprovalLevel $nivel1;
    private ExtraHourApprovalLevel $nivel2;
    private ExtraHourApprovalLevel $nivelAlmacen;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'Ver incidencias', 'guard_name' => 'web', 'category' => 'INCIDENCIAS']);
        Role::create(['name' => 'Empleado', 'guard_name' => 'web']);

        // Catorcena anterior (id menor): fuente para probar la copia "hacia atrás".
        $this->payrollAnterior = Payroll::create([
            'start_date' => now()->startOfDay()->subDays(14)->toDateString(),
            'biweekly' => 42,
            'is_active' => true,
        ]);

        $this->payroll = Payroll::create([
            'start_date' => now()->startOfDay()->toDateString(),
            'biweekly' => 43,
            'is_active' => true,
        ]);

        $this->admin = $this->makeUser('Admin', 'Sistemas');
        $this->admin->givePermissionTo('Ver incidencias');
        $this->jefe = $this->makeUser('Jefe Manufactura', 'Manufactura');
        $this->direccion = $this->makeUser('Direccion', 'Direccion');
        $this->empleado1 = $this->makeUser('Operador 1', 'Manufactura');
        $this->empleado2 = $this->makeUser('Operador 2', 'Manufactura');
        $this->empleado3 = $this->makeUser('Almacenista', 'Almacen');

        $this->grupoManufactura = $this->makeGroup('MANUFACTURA', [$this->empleado1->id, $this->empleado2->id]);
        $this->nivel1 = $this->makeLevel($this->grupoManufactura, 1, 'Jefe Manufactura', [$this->jefe->id]);
        $this->nivel2 = $this->makeLevel($this->grupoManufactura, 2, 'Direccion', [$this->direccion->id]);

        $this->grupoAlmacen = $this->makeGroup('ALMACEN', [$this->empleado3->id]);
        $this->nivelAlmacen = $this->makeLevel($this->grupoAlmacen, 1, 'Jefe Almacen', [$this->jefe->id]);
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

    private function makeGroup(string $name, array $employeeIds, ?Payroll $payroll = null): ExtraHourApprovalGroup
    {
        $group = ExtraHourApprovalGroup::create([
            'payroll_id' => ($payroll ?? $this->payroll)->id,
            'name' => $name,
        ]);
        $group->employees()->sync($employeeIds);

        return $group;
    }

    private function makeLevel(
        ExtraHourApprovalGroup $group,
        int $level,
        string $name,
        array $approverIds,
        ?Payroll $payroll = null
    ): ExtraHourApprovalLevel {
        $model = ExtraHourApprovalLevel::create([
            'payroll_id' => ($payroll ?? $this->payroll)->id,
            'approval_group_id' => $group->id,
            'level' => $level,
            'name' => $name,
        ]);
        $model->approvers()->sync($approverIds);

        return $model;
    }

    private function day(User $user, int $offset, array $attrs = []): PayrollUser
    {
        return PayrollUser::create(array_merge([
            'payroll_id' => $this->payroll->id,
            'user_id' => $user->id,
            'date' => $this->payroll->start_date->copy()->addDays($offset)->toDateString(),
            'check_in' => '09:00',
            'check_out' => '20:00',
            'incidence' => 'Dia normal',
            'extra_hours' => 2,
            'extra_minutes' => 0,
        ], $attrs));
    }

    /** Día con T.E. y autorización FINAL ya registrada (niveles 1 y 2). */
    private function finalApprovedDay(User $user, int $offset = 0): PayrollUser
    {
        $day = $this->day($user, $offset, [
            'extra_hour_status' => 'approved',
            'current_approval_level_id' => null,
            'approved_extra_hours' => 2,
            'approved_extra_minutes' => 0,
            'approved_by' => $this->direccion->id,
            'approved_at' => now(),
        ]);

        $this->decide($day, $this->nivel1, $this->jefe);
        $this->decide($day, $this->nivel2, $this->direccion);

        return $day;
    }

    /** Día EN VUELO: nivel 1 ya aprobado, esperando en el nivel 2. */
    private function inFlightDayAtLevelTwo(User $user, int $offset = 0): PayrollUser
    {
        $day = $this->day($user, $offset, [
            'extra_hour_status' => 'pending',
            'current_approval_level_id' => $this->nivel2->id,
            'proposed_extra_hours' => 2,
            'proposed_extra_minutes' => 0,
        ]);

        $this->decide($day, $this->nivel1, $this->jefe);

        return $day;
    }

    private function decide(PayrollUser $day, ExtraHourApprovalLevel $level, User $approver): void
    {
        ExtraHourApprovalDecision::create([
            'payroll_user_id' => $day->id,
            'approval_level_id' => $level->id,
            'approver_id' => $approver->id,
            'status' => 'approved',
            'proposed_extra_hours' => 2,
            'proposed_extra_minutes' => 0,
            'decided_at' => now(),
        ]);
    }

    private function decisionsOf(PayrollUser $day): int
    {
        return ExtraHourApprovalDecision::where('payroll_user_id', $day->id)->count();
    }

    private function levelsCount(): int
    {
        return ExtraHourApprovalLevel::where('payroll_id', $this->payroll->id)->count();
    }

    private function groupsCount(): int
    {
        return ExtraHourApprovalGroup::where('payroll_id', $this->payroll->id)->count();
    }

    private function save(array $groups)
    {
        return $this->actingAs($this->admin)->post(
            route('payrolls.extra-hours-groups.save', $this->payroll->id),
            ['groups' => $groups]
        );
    }

    /** Payload del grupo MANUFACTURA tal como lo envía la UI (con ids). */
    private function manufacturaPayload(?array $employeeIds = null, array $levelNames = []): array
    {
        return [
            'id' => $this->grupoManufactura->id,
            'name' => $levelNames['group'] ?? 'MANUFACTURA',
            'employee_ids' => $employeeIds ?? [$this->empleado1->id, $this->empleado2->id],
            'levels' => [
                [
                    'id' => $this->nivel1->id,
                    'name' => $levelNames[1] ?? 'Jefe Manufactura',
                    'approver_ids' => [$this->jefe->id],
                ],
                [
                    'id' => $this->nivel2->id,
                    'name' => $levelNames[2] ?? 'Direccion',
                    'approver_ids' => [$this->direccion->id],
                ],
            ],
        ];
    }

    /** Siembra en una catorcena fuente el mismo grupo y niveles que la actual. */
    private function seedSourceManufactura(Payroll $source): void
    {
        $group = $this->makeGroup('MANUFACTURA', [$this->empleado1->id, $this->empleado2->id], $source);
        $this->makeLevel($group, 1, 'Jefe Manufactura', [$this->jefe->id], $source);
        $this->makeLevel($group, 2, 'Direccion', [$this->direccion->id], $source);
    }

    private function almacenPayload(): array
    {
        return [
            'id' => $this->grupoAlmacen->id,
            'name' => 'ALMACEN',
            'employee_ids' => [$this->empleado3->id],
            'levels' => [[
                'id' => $this->nivelAlmacen->id,
                'name' => 'Jefe Almacen',
                'approver_ids' => [$this->jefe->id],
            ]],
        ];
    }

    // ─── Guardar la configuración sin perder autorizaciones ──────────────

    public function test_guardar_la_misma_configuracion_con_ids_conserva_las_decisiones(): void
    {
        $day = $this->finalApprovedDay($this->empleado1);

        $this->save([$this->manufacturaPayload(), $this->almacenPayload()])
            ->assertSessionHasNoErrors();

        $fresh = $day->fresh();
        $this->assertSame(2, $this->decisionsOf($day), 'Las decisiones no deben borrarse');
        $this->assertSame('approved', $fresh->extra_hour_status);
        $this->assertNotNull($fresh->approved_at);
        $this->assertSame(2, $this->groupsCount(), 'No se duplican grupos');
        $this->assertSame(3, $this->levelsCount(), 'No se duplican niveles');
    }

    public function test_renombrar_grupo_y_nivel_conserva_las_decisiones(): void
    {
        $day = $this->finalApprovedDay($this->empleado1);

        $this->save([
            $this->manufacturaPayload(null, [
                'group' => 'MANUFACTURA Y ALMACEN',
                1 => 'Supervisor de Planta',
                2 => 'Gerencia',
            ]),
            $this->almacenPayload(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->decisionsOf($day), 'Renombrar no es recrear: las decisiones se conservan');
        $this->assertSame(2, $this->groupsCount());
        $this->assertSame('MANUFACTURA Y ALMACEN', $this->grupoManufactura->fresh()->name);
        $this->assertSame('Supervisor de Planta', $this->nivel1->fresh()->name);
        $this->assertSame('Gerencia', $this->nivel2->fresh()->name);
    }

    public function test_agregar_un_nivel_conserva_las_decisiones_previas(): void
    {
        $day = $this->finalApprovedDay($this->empleado1);

        $payload = $this->manufacturaPayload();
        $payload['levels'][] = [
            'id' => null,
            'name' => 'Recursos Humanos',
            'approver_ids' => [$this->admin->id],
        ];

        $this->save([$payload, $this->almacenPayload()])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->decisionsOf($day));

        $nuevo = ExtraHourApprovalLevel::where('approval_group_id', $this->grupoManufactura->id)
            ->where('name', 'Recursos Humanos')
            ->first();

        $this->assertNotNull($nuevo, 'El nivel nuevo se crea');
        $this->assertSame(3, (int) $nuevo->level, 'El nivel nuevo se agrega al final');
        $this->assertSame(4, $this->levelsCount(), '2 niveles existentes + 1 nuevo + 1 de ALMACEN');
    }

    public function test_quitar_un_nivel_borra_solo_las_decisiones_de_ese_nivel(): void
    {
        $day = $this->finalApprovedDay($this->empleado1);

        $payload = $this->manufacturaPayload();
        $payload['levels'] = [$payload['levels'][0]]; // solo queda el nivel 1

        $this->save([$payload, $this->almacenPayload()])->assertSessionHasNoErrors();

        $fresh = $day->fresh();
        $this->assertSame(1, $this->decisionsOf($day), 'Desaparece la decisión del nivel eliminado');
        $this->assertNull($this->nivel2->fresh(), 'El nivel 2 se eliminó');
        $this->assertSame('approved', $fresh->extra_hour_status, 'Un día ya cerrado no se reabre');
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_quitar_el_ultimo_nivel_pendiente_cierra_el_dia_con_la_decision_existente(): void
    {
        $day = $this->inFlightDayAtLevelTwo($this->empleado1);

        $payload = $this->manufacturaPayload();
        $payload['levels'] = [$payload['levels'][0]];

        $this->save([$payload, $this->almacenPayload()])->assertSessionHasNoErrors();

        $fresh = $day->fresh();
        $this->assertSame(1, $this->decisionsOf($day));
        $this->assertSame('approved', $fresh->extra_hour_status);
        $this->assertSame(2, (int) $fresh->approved_extra_hours);
        $this->assertSame($this->jefe->id, (int) $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at, 'El cierre se completa con la última decisión aprobada');
    }

    public function test_quitar_el_nivel_ya_aprobado_deja_el_dia_en_el_nivel_restante(): void
    {
        $day = $this->inFlightDayAtLevelTwo($this->empleado1);

        $payload = $this->manufacturaPayload();
        $payload['levels'] = [$payload['levels'][1]]; // se elimina el nivel 1

        $this->save([$payload, $this->almacenPayload()])->assertSessionHasNoErrors();

        $fresh = $day->fresh();
        $this->assertSame(0, $this->decisionsOf($day), 'La decisión se va con el nivel eliminado');
        $this->assertSame('pending', $fresh->extra_hour_status);
        $this->assertSame((int) $this->nivel2->id, (int) $fresh->current_approval_level_id,
            'El día sigue esperando en su nivel (que ahora es el 1)');
        $this->assertSame(1, (int) $this->nivel2->fresh()->level, 'La numeración se compacta');
    }

    // ─── Colaboradores removidos y grupos eliminados ─────────────────────

    public function test_remover_un_empleado_borra_solo_sus_decisiones_en_vuelo(): void
    {
        $cerrado = $this->finalApprovedDay($this->empleado1, 0);
        $enVuelo = $this->inFlightDayAtLevelTwo($this->empleado2, 1);

        $this->save([
            $this->manufacturaPayload([$this->empleado1->id]),
            $this->almacenPayload(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->decisionsOf($cerrado), 'Las autorizaciones cerradas no se tocan');
        $this->assertSame('approved', $cerrado->fresh()->extra_hour_status);

        $this->assertSame(0, $this->decisionsOf($enVuelo), 'El colaborador removido pierde sus decisiones en vuelo');
        $fresh = $enVuelo->fresh();
        $this->assertSame('pending', $fresh->extra_hour_status);
        $this->assertNull($fresh->current_approval_level_id, 'Sin grupo: día sin flujo de autorización');

        $this->assertFalse(
            $this->grupoManufactura->fresh()->employees()->where('users.id', $this->empleado2->id)->exists(),
            'El colaborador ya no pertenece al grupo'
        );
        $this->assertNotNull($this->nivel1->fresh(), 'Los niveles del grupo siguen existiendo');
    }

    public function test_un_dia_rechazado_conserva_su_decision_al_remover_al_colaborador(): void
    {
        $day = $this->day($this->empleado1, 0, [
            'extra_hour_status' => 'rejected',
            'current_approval_level_id' => null,
            'approved_extra_hours' => 0,
            'approved_extra_minutes' => 0,
        ]);
        ExtraHourApprovalDecision::create([
            'payroll_user_id' => $day->id,
            'approval_level_id' => $this->nivel1->id,
            'approver_id' => $this->jefe->id,
            'status' => 'rejected',
            'comments' => 'Sin autorizacion previa',
            'decided_at' => now(),
        ]);

        // El colaborador sale del grupo (la jerarquía del grupo sigue existiendo)
        $this->save([
            $this->manufacturaPayload([$this->empleado2->id]),
            $this->almacenPayload(),
        ])->assertSessionHasNoErrors();

        $fresh = $day->fresh();
        $this->assertSame('rejected', $fresh->extra_hour_status, 'Un rechazo ya registrado no se reabre');
        $this->assertNull($fresh->current_approval_level_id);
        $this->assertSame(0, (int) $fresh->approved_extra_hours);
        $this->assertSame(1, $this->decisionsOf($day), 'El rechazo no es una autorización en vuelo');
    }

    /**
     * Regresión del problema reportado: editar un grupo NO debe reiniciar los
     * días en vuelo de los demás grupos.
     */
    public function test_editar_un_grupo_no_reinicia_los_dias_de_los_demas(): void
    {
        $diaManufactura = $this->inFlightDayAtLevelTwo($this->empleado1, 0);
        $diaAlmacen = $this->day($this->empleado3, 1, [
            'extra_hour_status' => 'pending',
            'current_approval_level_id' => $this->nivelAlmacen->id,
        ]);

        // Solo cambia el nombre del nivel del grupo ALMACEN
        $almacen = $this->almacenPayload();
        $almacen['levels'][0]['name'] = 'Jefe de Almacen';

        $this->save([$this->manufacturaPayload(), $almacen])->assertSessionHasNoErrors();

        $freshManufactura = $diaManufactura->fresh();
        $this->assertSame((int) $this->nivel2->id, (int) $freshManufactura->current_approval_level_id,
            'MANUFACTURA no cambió: su día debe seguir esperando en el nivel 2');
        $this->assertSame('pending', $freshManufactura->extra_hour_status);
        $this->assertSame(1, $this->decisionsOf($diaManufactura), 'La decisión del nivel 1 se conserva');

        $freshAlmacen = $diaAlmacen->fresh();
        $this->assertSame((int) $this->nivelAlmacen->id, (int) $freshAlmacen->current_approval_level_id);
        $this->assertSame('pending', $freshAlmacen->extra_hour_status);
        $this->assertSame('Jefe de Almacen', $this->nivelAlmacen->fresh()->name);
    }

    public function test_eliminar_un_grupo_borra_sus_niveles_y_decisiones(): void
    {
        $cerrado = $this->finalApprovedDay($this->empleado1, 0);

        $diaAlmacen = $this->day($this->empleado3, 1, [
            'extra_hour_status' => 'pending',
            'current_approval_level_id' => $this->nivelAlmacen->id,
        ]);
        $this->decide($diaAlmacen, $this->nivelAlmacen, $this->jefe);

        // Se guarda solo MANUFACTURA: ALMACEN desaparece de la configuración
        $this->save([$this->manufacturaPayload()])->assertSessionHasNoErrors();

        $this->assertNull($this->grupoAlmacen->fresh(), 'El grupo eliminado se borra');
        $this->assertNull($this->nivelAlmacen->fresh(), 'Sus niveles se borran');
        $this->assertSame(0, $this->decisionsOf($diaAlmacen), 'Sus decisiones se borran');

        $this->assertSame(2, $this->decisionsOf($cerrado), 'El grupo que sigue existiendo conserva las suyas');
        $this->assertSame('approved', $cerrado->fresh()->extra_hour_status);

        $fresh = $diaAlmacen->fresh();
        $this->assertSame('pending', $fresh->extra_hour_status);
        $this->assertNull($fresh->current_approval_level_id, 'Sin grupo: día sin flujo de autorización');
    }

    // ─── Copiar de otra catorcena y compatibilidad sin ids ───────────────

    public function test_copiar_de_la_catorcena_anterior_conserva_las_decisiones(): void
    {
        $this->seedSourceManufactura($this->payrollAnterior);

        $dia = $this->inFlightDayAtLevelTwo($this->empleado1);

        $this->actingAs($this->admin)
            ->post(route('payrolls.extra-hours-copy', $this->payroll->id))
            ->assertSessionHasNoErrors();

        $fresh = $dia->fresh();
        $this->assertSame((int) $this->nivel2->id, (int) $fresh->current_approval_level_id,
            'El grupo copiado mantiene su id: el día sigue esperando en el nivel 2');
        $this->assertSame(1, $this->decisionsOf($dia), 'La decisión del nivel 1 se conserva');
        $this->assertSame('pending', $fresh->extra_hour_status);
        $this->assertSame(2, $this->levelsCount(), 'No se duplican niveles al copiar');
        $this->assertNull($this->grupoAlmacen->fresh(), 'La copia reemplaza los grupos que no están en la fuente');
    }

    public function test_copiar_de_la_catorcena_siguiente_conserva_las_decisiones(): void
    {
        $siguiente = Payroll::create([
            'start_date' => now()->startOfDay()->addDays(14)->toDateString(),
            'biweekly' => 44,
            'is_active' => true,
        ]);
        $this->seedSourceManufactura($siguiente);

        $dia = $this->inFlightDayAtLevelTwo($this->empleado1);

        $this->actingAs($this->admin)
            ->post(route('payrolls.extra-hours-copy-next', $this->payroll->id))
            ->assertSessionHasNoErrors();

        $fresh = $dia->fresh();
        $this->assertSame((int) $this->nivel2->id, (int) $fresh->current_approval_level_id);
        $this->assertSame(1, $this->decisionsOf($dia));
        $this->assertSame('pending', $fresh->extra_hour_status);
        $this->assertSame(2, $this->levelsCount());
    }

    public function test_guardar_sin_ids_conserva_por_nombre_y_numero_de_nivel(): void
    {
        $day = $this->finalApprovedDay($this->empleado1);

        // Payload "antiguo" sin ids (compatibilidad con clientes que no los envían)
        $this->save([
            [
                'name' => 'MANUFACTURA',
                'employee_ids' => [$this->empleado1->id, $this->empleado2->id],
                'levels' => [
                    ['name' => 'Jefe Manufactura', 'approver_ids' => [$this->jefe->id]],
                    ['name' => 'Direccion', 'approver_ids' => [$this->direccion->id]],
                ],
            ],
            [
                'name' => 'ALMACEN',
                'employee_ids' => [$this->empleado3->id],
                'levels' => [
                    ['name' => 'Jefe Almacen', 'approver_ids' => [$this->jefe->id]],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->decisionsOf($day), 'Sin ids, el emparejamiento es por nombre y número de nivel');
        $this->assertSame(2, $this->groupsCount(), 'No se recrean grupos');
        $this->assertSame(3, $this->levelsCount(), 'No se recrean niveles');
    }

    // ─── Reasignar un colaborador entre grupos ───────────────────────────

    public function test_mover_un_empleado_entre_grupos_lo_conserva_solo_en_el_destino(): void
    {
        // Payload tal como lo enviaria un cliente que aun NO resuelve el "mover"
        // en pantalla: el colaborador sigue listado en su grupo anterior y, a la
        // vez, se agrega al nuevo. El backend debe conservarlo SOLO en el ultimo
        // grupo que lo incluye (el destino), sin fallar ni duplicarlo.
        $this->save([
            [
                'id' => $this->grupoManufactura->id,
                'name' => 'MANUFACTURA',
                'employee_ids' => [$this->empleado1->id, $this->empleado2->id],
                'levels' => [
                    ['id' => $this->nivel1->id, 'name' => 'Jefe Manufactura', 'approver_ids' => [$this->jefe->id]],
                    ['id' => $this->nivel2->id, 'name' => 'Direccion', 'approver_ids' => [$this->direccion->id]],
                ],
            ],
            [
                'id' => $this->grupoAlmacen->id,
                'name' => 'ALMACEN',
                'employee_ids' => [$this->empleado3->id, $this->empleado1->id],
                'levels' => [
                    ['id' => $this->nivelAlmacen->id, 'name' => 'Jefe Almacen', 'approver_ids' => [$this->jefe->id]],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(
            $this->grupoManufactura->fresh()->employees()->where('user_id', $this->empleado1->id)->exists(),
            'El colaborador reasignado se retira de su grupo anterior'
        );

        $this->assertTrue(
            $this->grupoAlmacen->fresh()->employees()->where('user_id', $this->empleado1->id)->exists(),
            'El colaborador queda en el grupo destino (el ultimo que lo incluye)'
        );

        $this->assertSame(
            1,
            ExtraHourApprovalGroup::where('payroll_id', $this->payroll->id)
                ->whereHas('employees', fn ($q) => $q->where('user_id', $this->empleado1->id))
                ->count(),
            'No queda duplicado: pertenece a un solo grupo'
        );
    }
}
