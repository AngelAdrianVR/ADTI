// ─── Composable: Jerarquía de aprobación por niveles/grupos ───
import { computed } from 'vue';

/**
 * Determina si el usuario actual puede actuar sobre una incidencia
 * basándose en la jerarquía de grupos y niveles de aprobación.
 * 
 * Usa las columnas desnormalizadas extra_hour_status y current_approval_level_id
 * para evitar escanear decisiones en cada lectura.
 *
 * @param {Ref<Array>} approvalGroups - Grupos anidados [{id, name, employee_ids, levels:[{id, level, name, approvers}]}]
 * @param {Ref<Number>} currentUserId - ID del usuario logueado
 */

// ─── Funciones puras (evaluación por conjunto de grupos, p. ej. rangos multi-catorcena) ───

/**
 * Chequeo rápido: ¿un usuario puede actuar sobre una incidencia dado un conjunto de grupos?
 * Usa las columnas desnormalizadas para O(1).
 *
 * @param {Object} incidence - Debe tener extra_hour_status, current_approval_level_id, user_id
 * @param {Array} groups - Grupos [{id, name, employee_ids, levels:[{id, level, name, approvers}]}]
 * @param {Number} currentUserId - ID del usuario logueado
 * @returns {{ canAct: boolean, reason: string, isMyEmployee: boolean, alreadyDecided: boolean, status: string|null }}
 */
export function computeActionPermission(incidence, groups, currentUserId) {
    // ── 1) Permiso calculado por el SERVIDOR (regla canónica) ──
    // PayrollController adjunta `approval` a cada incidencia (evaluateIncidence).
    // Usarlo garantiza que el frontend y el badge de la barra superior muestren
    // EXACTAMENTE el mismo número y las mismas acciones.
    const server = incidence?.approval;
    if (server) {
        return {
            canAct: !!server.can_act,
            reason: server.reason || '',
            currentLevel: null,
            isMyEmployee: !!server.is_my_employee,
            alreadyDecided: !!server.already_decided,
            orphan: !!server.orphan,
            myDecision: null,
        };
    }

    // ── 2) Cálculo local (modo rango y payloads sin flags) ──
    const hasExtra = (incidence?.extra_hours || 0) > 0 || (incidence?.extra_minutes || 0) > 0;
    if (!hasExtra) {
        return { canAct: false, reason: 'Sin tiempo extra', currentLevel: null, isMyEmployee: false, alreadyDecided: false, orphan: false };
    }

    // Sin grupos en la catorcena → el día NO tiene flujo de autorización.
    // (Antes se devolvía canAct: true como "modo directo", lo que permitía a
    //  cualquier aprobador cerrar días que la jerarquía nunca inicializó.)
    if (!groups || groups.length === 0) {
        return {
            canAct: false,
            reason: 'Sin flujo de autorización (la catorcena no tiene grupos configurados)',
            currentLevel: null,
            isMyEmployee: false,
            alreadyDecided: false,
            orphan: true,
        };
    }

    const cid = Number(currentUserId);
    const employeeId = Number(incidence.user_id);

    // Grupo del empleado (el de menor id, igual que ExtraHourApprovalService::findGroupForUser)
    const employeeGroup = groups.find(g => (g.employee_ids || []).map(Number).includes(employeeId)) || null;

    const isMyEmployee = !!employeeGroup && (employeeGroup.levels || []).some(level =>
        (level.approvers || []).some(a => Number(a.id) === cid)
    );

    // Verificar si el empleado está en mi scope
    if (!isMyEmployee) {
        return { canAct: false, reason: 'Fuera de tu grupo', currentLevel: null, isMyEmployee: false, alreadyDecided: false, orphan: false };
    }

    const status = incidence.extra_hour_status || 'none';
    const currentLevelId = incidence.current_approval_level_id;
    const decisions = incidence.approval_decisions || [];

    // Ya resuelto (approved/rejected)
    if (status === 'approved' || status === 'rejected') {
        const myDecision = currentLevelId
            ? decisions.find(d => Number(d.approver?.id) === cid && Number(d.level_id) === Number(currentLevelId))
            : decisions.find(d => Number(d.approver?.id) === cid);
        return {
            canAct: false,
            reason: status === 'approved' ? 'Ya fue aprobado' : 'Ya fue rechazado',
            currentLevel: null,
            isMyEmployee: true,
            alreadyDecided: !!myDecision,
            myDecision: myDecision || null,
            orphan: false,
        };
    }

    // Sin nivel = día SIN FLUJO DE AUTORIZACIÓN: nunca accionable.
    // (Antes se devolvía canAct: true con la razón "Pendiente de decisión", y esos
    //  días sumaban en "en tu turno" aunque la jerarquía no los hubiera inicializado.)
    if (!currentLevelId) {
        return {
            canAct: false,
            reason: 'Sin flujo de autorización (el colaborador no está en ningún grupo)',
            currentLevel: null,
            isMyEmployee: true,
            alreadyDecided: false,
            orphan: true,
        };
    }

    // El nivel actual debe pertenecer al grupo del empleado Y yo ser su aprobador
    const isMyLevel = (employeeGroup.levels || []).some(level =>
        Number(level.id) === Number(currentLevelId) &&
        (level.approvers || []).some(a => Number(a.id) === cid)
    );

    if (!isMyLevel) {
        return { canAct: false, reason: 'Esperando decisión de otro nivel', currentLevel: null, isMyEmployee: true, alreadyDecided: false, orphan: false };
    }

    // Los niveles PREVIOS del grupo ya deben estar aprobados (misma regla que
    // ExtraHourApprovalService::decide()). Si no, el día sigue "en espera" y no
    // debe contarse como "en tu turno" ni ofrecer botones que fallarían.
    const currentLevelNumber = Number(
        (employeeGroup.levels || []).find(level => Number(level.id) === Number(currentLevelId))?.level || 0
    );
    const hasPendingPreviousLevel = (employeeGroup.levels || []).some(level => {
        if (Number(level.level || 0) >= currentLevelNumber) return false;
        return !decisions.some(d => Number(d.level_id) === Number(level.id) && d.status === 'approved');
    });
    if (hasPendingPreviousLevel) {
        return { canAct: false, reason: 'Esperando aprobación del nivel previo', currentLevel: null, isMyEmployee: true, alreadyDecided: false, orphan: false };
    }

    const myDecision = decisions.find(d =>
        Number(d.approver?.id) === cid && Number(d.level_id) === Number(currentLevelId)
    );
    if (myDecision) {
        return {
            canAct: false,
            reason: myDecision.status === 'approved' ? 'Has aprobado este tiempo extra' : 'Has rechazado este tiempo extra',
            currentLevel: null,
            isMyEmployee: true,
            alreadyDecided: true,
            myDecision,
            orphan: false,
        };
    }

    // ¿Otro aprobador del mismo nivel ya decidió?
    const hasOtherDecision = decisions.some(d =>
        Number(d.level_id) === Number(currentLevelId) && Number(d.approver?.id) !== cid
    );
    if (hasOtherDecision) {
        return { canAct: false, reason: 'Otro aprobador de tu nivel ya decidió', currentLevel: null, isMyEmployee: true, alreadyDecided: false, orphan: false };
    }

    return { canAct: true, reason: 'Es tu turno de revisar', currentLevel: null, isMyEmployee: true, alreadyDecided: false, orphan: false };
}

/**
 * Determina si un usuario puede revertir una decisión ya tomada (o reparar un estado
 * final huérfano) dentro de un conjunto de grupos.
 *
 * @param {Object} incidence - Debe tener extra_hour_status, user_id, approval_decisions
 * @param {Array} groups
 * @param {Number} currentUserId
 * @returns {boolean}
 */
export function computeCanRevert(incidence, groups, currentUserId) {
    const status = incidence.extra_hour_status || 'none';
    const hasExtra = (incidence.extra_hours || 0) > 0 || (incidence.extra_minutes || 0) > 0;
    if (!hasExtra) return false;

    // Solo se puede revertir si está en estado final (o legacy con approved_at)
    if (status !== 'approved' && status !== 'rejected') {
        if (!incidence.approved_at) return false;
    }

    // Sin grupos configurados → modo directo: cualquiera con permiso puede revertir
    if (!groups || groups.length === 0) {
        return true;
    }

    // Verificar si el empleado está en mi scope (soy aprobador en algún nivel de su grupo)
    const cid = Number(currentUserId);
    return groups.some(group => {
        const isMyGroup = (group.levels || []).some(level =>
            (level.approvers || []).some(a => Number(a.id) === cid)
        );
        return isMyGroup && (group.employee_ids || []).some(id => Number(id) === Number(incidence.user_id));
    });
}

export function useApprovalHierarchy(approvalGroups, currentUserId) {

    // IDs de niveles donde el usuario actual es aprobador
    const myLevelIds = computed(() => {
        if (!approvalGroups.value || approvalGroups.value.length === 0) return new Set();
        const cid = Number(currentUserId.value);
        const ids = new Set();
        approvalGroups.value.forEach(group => {
            (group.levels || []).forEach(level => {
                if ((level.approvers || []).some(a => Number(a.id) === cid)) {
                    ids.add(level.id);
                }
            });
        });
        return ids;
    });

    // ¿El usuario actual es aprobador en algún nivel?
    const isCurrentUserApprover = computed(() => myLevelIds.value.size > 0);

    // IDs de empleados que el usuario actual debe aprobar (unión de todos sus grupos)
    const myEmployeeIds = computed(() => {
        if (!approvalGroups.value || approvalGroups.value.length === 0) return new Set();
        const cid = Number(currentUserId.value);
        const allIds = new Set();
        approvalGroups.value.forEach(group => {
            const isMyGroup = (group.levels || []).some(level =>
                (level.approvers || []).some(a => Number(a.id) === cid)
            );
            if (isMyGroup) {
                (group.employee_ids || []).forEach(id => allIds.add(Number(id)));
            }
        });
        return allIds;
    });

    /**
     * Chequeo rápido: ¿el usuario puede actuar sobre esta incidencia?
     * Usa las columnas desnormalizadas para O(1).
     * 
     * @param {Object} incidence - Debe tener extra_hour_status, current_approval_level_id, user_id
     * @returns {{ canAct: boolean, reason: string, isMyEmployee: boolean, alreadyDecided: boolean, status: string|null }}
     */
    function getActionPermission(incidence, groups = null) {
        return computeActionPermission(incidence, groups ?? (approvalGroups.value || []), currentUserId.value);
    }

    /**
     * Determina si el usuario actual puede revertir una decisión ya tomada
     * (o reparar un estado final huérfano).
     * Solo aplica cuando el estado es final (approved/rejected) y el usuario
     * es aprobador de algún nivel del grupo al que pertenece el empleado.
     * No exige que el usuario haya participado en la decisión: un aprobador
     * del grupo con estado final (legítimo o huérfano) siempre puede
     * reiniciar el flujo para corregir.
     * 
     * @param {Object} incidence - Debe tener extra_hour_status, user_id, approval_decisions
     * @returns {boolean}
     */
    function canRevertDecision(incidence, groups = null) {
        return computeCanRevert(incidence, groups ?? (approvalGroups.value || []), currentUserId.value);
    }

    return {
        isCurrentUserApprover,
        myLevelIds,
        myEmployeeIds,
        getActionPermission,
        canRevertDecision,
    };
}

