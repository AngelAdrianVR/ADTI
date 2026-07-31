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
    function getActionPermission(incidence) {
        // Sin grupos configurados → modo directo
        if (!approvalGroups.value || approvalGroups.value.length === 0) {
            return { canAct: true, reason: '', currentLevel: null, isMyEmployee: true, alreadyDecided: false };
        }

        // Verificar si el empleado está en mi scope
        const isMyEmployee = myEmployeeIds.value.has(Number(incidence.user_id));
        if (!isMyEmployee) {
            return { canAct: false, reason: 'Fuera de tu grupo', currentLevel: null, isMyEmployee: false, alreadyDecided: false };
        }

        const status = incidence.extra_hour_status || incidence.extra_hour_status;

        // Ya resuelto (approved/rejected)
        if (status === 'approved' || status === 'rejected') {
            const decisions = incidence.approval_decisions || [];
            const myDecision = decisions.find(d => Number(d.approver?.id) === Number(currentUserId.value));
            return {
                canAct: false,
                reason: status === 'approved' ? 'Ya fue aprobado' : 'Ya fue rechazado',
                currentLevel: null,
                isMyEmployee: true,
                alreadyDecided: !!myDecision,
                myDecision: myDecision || null,
            };
        }

        // Pendiente: verificar si soy aprobador del nivel actual
        const currentLevelId = incidence.current_approval_level_id;
        
        // Sin nivel específico → modo directo (cualquiera del grupo puede)
        if (!currentLevelId) {
            return { canAct: true, reason: 'Pendiente de decisión', currentLevel: null, isMyEmployee: true, alreadyDecided: false };
        }

        // Verificar si estoy en el nivel actual
        if (myLevelIds.value.has(Number(currentLevelId))) {
            // Verificar que no haya decidido ya en este nivel
            const decisions = incidence.approval_decisions || [];
            const myDecision = decisions.find(d =>
                Number(d.approver?.id) === Number(currentUserId.value) &&
                Number(d.level_id) === Number(currentLevelId)
            );
            if (myDecision) {
                return {
                    canAct: false,
                    reason: myDecision.status === 'approved' ? 'Has aprobado este tiempo extra' : 'Has rechazado este tiempo extra',
                    currentLevel: null,
                    isMyEmployee: true,
                    alreadyDecided: true,
                    myDecision,
                };
            }
            // ¿Otro aprobador del mismo nivel ya decidió?
            const hasOtherDecision = decisions.some(d =>
                Number(d.level_id) === Number(currentLevelId) &&
                Number(d.approver?.id) !== Number(currentUserId.value)
            );
            if (hasOtherDecision) {
                return {
                    canAct: false,
                    reason: 'Otro aprobador de tu nivel ya decidió',
                    currentLevel: null,
                    isMyEmployee: true,
                    alreadyDecided: false,
                };
            }
            return { canAct: true, reason: 'Es tu turno de revisar', currentLevel: null, isMyEmployee: true, alreadyDecided: false };
        }

        // No soy aprobador del nivel actual
        return { canAct: false, reason: 'Esperando decisión de otro nivel', currentLevel: null, isMyEmployee: true, alreadyDecided: false };
    }

    /**
     * Determina si el usuario actual puede revertir una decisión ya tomada.
     * Solo aplica cuando el estado es final (approved/rejected) y el usuario
     * participó en la decisión.
     * 
     * @param {Object} incidence - Debe tener extra_hour_status, user_id, approval_decisions
     * @returns {boolean}
     */
    function canRevertDecision(incidence) {
        const status = incidence.extra_hour_status || 'none';
        
        // Solo se puede revertir si está en estado final
        if (status !== 'approved' && status !== 'rejected') {
            // Fallback legacy: si no hay status jerárquico, verificar approved_at
            if (!incidence.approved_at) return false;
        }

        // Sin grupos configurados → modo directo: cualquiera con permiso puede revertir
        if (!approvalGroups.value || approvalGroups.value.length === 0) {
            return true;
        }

        // Verificar si el empleado está en mi scope
        if (!myEmployeeIds.value.has(Number(incidence.user_id))) {
            return false;
        }

        // Verificar si el usuario actual participó en alguna decisión
        const decisions = incidence.approval_decisions || [];
        const myDecision = decisions.find(d => Number(d.approver?.id) === Number(currentUserId.value));
        
        // Si el usuario fue uno de los aprobadores, puede revertir
        if (myDecision) return true;

        // Si el usuario es aprobador en algún nivel del grupo, puede revertir
        if (myLevelIds.value.size > 0) {
            // Verificar que el usuario está en algún nivel de aprobación para este empleado
            const decisionsLevelIds = new Set(decisions.map(d => Number(d.level_id)));
            for (const lid of myLevelIds.value) {
                if (decisionsLevelIds.has(lid)) return true;
            }
        }

        return false;
    }

    return {
        isCurrentUserApprover,
        myLevelIds,
        myEmployeeIds,
        getActionPermission,
        canRevertDecision,
    };
}

