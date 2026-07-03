// ─── Composable: Jerarquía de aprobación por niveles/grupos ───
import { computed } from 'vue';

/**
 * Determina si el usuario actual puede actuar sobre una incidencia
 * basándose en la jerarquía de grupos y niveles de aprobación.
 *
 * @param {Object} options
 * @param {Ref|Array} options.approvalLevels - Niveles planos (vienen del backend)
 * @param {Ref|Number} options.currentUserId - ID del usuario logueado
 */
export function useApprovalHierarchy(approvalLevels, currentUserId) {

    // Determinar si el usuario actual es aprobador en algún nivel
    const isCurrentUserApprover = computed(() => {
        if (!approvalLevels.value || approvalLevels.value.length === 0) return false;
        const cid = Number(currentUserId.value);
        return approvalLevels.value.some(level =>
            level.approvers?.some(a => Number(a.id) === cid)
        );
    });

    // Encontrar el primer nivel donde el usuario actual es aprobador
    const currentUserLevel = computed(() => {
        if (!approvalLevels.value) return null;
        const cid = Number(currentUserId.value);
        return approvalLevels.value.find(level =>
            level.approvers?.some(a => Number(a.id) === cid)
        ) || null;
    });

    // IDs de empleados que el usuario actual debe aprobar (UNIÓN de todos sus grupos)
    const myEmployeeIds = computed(() => {
        if (!approvalLevels.value || approvalLevels.value.length === 0) return new Set();
        const cid = Number(currentUserId.value);
        const allIds = new Set();
        approvalLevels.value.forEach(level => {
            if (level.approvers?.some(a => Number(a.id) === cid)) {
                (level.employee_ids || []).forEach(id => allIds.add(Number(id)));
            }
        });
        return allIds;
    });

    /**
     * Para una incidencia dada, determina si el usuario actual puede actuar.
     * Retorna { canAct: bool, reason: string, currentLevel: object|null }
     */
    function getActionPermission(incidence) {
        // Si no hay niveles configurados, cualquiera con permiso puede actuar
        if (!approvalLevels.value || approvalLevels.value.length === 0) {
            return { canAct: true, reason: '', currentLevel: null, isMyEmployee: true };
        }

        // ¿El empleado está en mi grupo?
        const incidenceUserId = Number(incidence.user_id);
        if (!myEmployeeIds.value.has(incidenceUserId)) {
            return { canAct: false, reason: 'Este empleado no está en tu grupo de autorización', currentLevel: null, isMyEmployee: false };
        }

        // Buscar decisiones existentes para esta incidencia
        const decisions = incidence.approval_decisions || [];
        const decisionsByLevel = {};
        decisions.forEach(d => {
            if (!decisionsByLevel[d.level_id]) decisionsByLevel[d.level_id] = [];
            decisionsByLevel[d.level_id].push(d);
        });

        // Encontrar el nivel más bajo que no está completamente aprobado
        const sortedLevels = [...approvalLevels.value].sort((a, b) => a.level - b.level);

        for (const level of sortedLevels) {
            const levelDecisions = decisionsByLevel[level.id] || [];
            const approverIds = (level.approvers || []).map(a => Number(a.id));
            const currentId = Number(currentUserId.value);
            const approvedCount = levelDecisions.filter(d => d.status === 'approved').length;
            const hasRejection = levelDecisions.some(d => d.status === 'rejected');

            if (hasRejection) {
                // Ya fue rechazado en este nivel (por este aprobador u otro)
                const myRejection = levelDecisions.find(d => Number(d.approver?.id) === currentId && d.status === 'rejected');
                if (myRejection) {
                    // Yo lo rechacé → decisión firme, no reversible
                    return {
                        canAct: false,
                        reason: 'Has rechazado este tiempo extra',
                        currentLevel: level,
                        isMyEmployee: true,
                        alreadyDecided: true,
                        myDecision: myRejection,
                        previousDecisions: getPreviousDecisions(level, decisionsByLevel),
                    };
                }
                if (approverIds.includes(currentId)) {
                    // Soy aprobador de este nivel pero otro rechazó → puedo decidir aún
                    return {
                        canAct: true,
                        reason: 'Otro aprobador rechazó, aún puedes decidir',
                        currentLevel: level,
                        isMyEmployee: true,
                        alreadyDecided: false,
                        myDecision: null,
                        previousDecisions: getPreviousDecisions(level, decisionsByLevel),
                    };
                }
                // No soy aprobador de este nivel → bloqueado
                return { 
                    canAct: false, 
                    reason: 'Rechazado en el nivel ' + (level.name || level.level), 
                    currentLevel: null, 
                    isMyEmployee: true,
                    alreadyDecided: false,
                };
            }

            if (approvedCount < approverIds.length) {
                // Este nivel está pendiente (no todos han aprobado)
                if (approverIds.includes(currentId)) {
                    const myApproval = levelDecisions.find(d => Number(d.approver?.id) === currentId && d.status === 'approved');
                    if (myApproval) {
                        // Ya aprobé → decisión firme, no reversible
                        return {
                            canAct: false,
                            reason: 'Has aprobado este tiempo extra',
                            currentLevel: level,
                            isMyEmployee: true,
                            previousDecisions: getPreviousDecisions(level, decisionsByLevel),
                            alreadyDecided: true,
                            myDecision: myApproval,
                        };
                    }
                    // Aún no he decidido → es mi turno
                    return {
                        canAct: true,
                        reason: 'Es tu turno de revisar',
                        currentLevel: level,
                        isMyEmployee: true,
                        previousDecisions: getPreviousDecisions(level, decisionsByLevel),
                        alreadyDecided: false,
                        myDecision: null,
                    };
                }
                return {
                    canAct: false,
                    reason: `Esperando a los aprobadores del nivel ${level.level} (${level.name || 'Nivel ' + level.level})`,
                    currentLevel: level,
                    isMyEmployee: true,
                    pendingApprovers: approverIds.filter(id => !levelDecisions.some(d => d.approver?.id === id && d.status === 'approved')),
                };
            }
        }

        // Todos los niveles aprobados
        const myDecision = decisions.find(d => Number(d.approver?.id) === Number(currentUserId.value));
        return { 
            canAct: false, 
            reason: myDecision 
                ? (myDecision.status === 'approved' ? 'Has aprobado este tiempo extra' : 'Has rechazado este tiempo extra')
                : 'Todos los niveles completaron la aprobación', 
            currentLevel: null, 
            isMyEmployee: true,
            alreadyDecided: !!myDecision,
            myDecision: myDecision || null,
        };
    }

    function getPreviousDecisions(currentLevel, decisionsByLevel) {
        const prev = {};
        for (const [levelId, decs] of Object.entries(decisionsByLevel)) {
            const level = approvalLevels.value.find(l => l.id === parseInt(levelId));
            if (level && level.level < currentLevel.level) {
                prev[level.level] = {
                    name: level.name || `Nivel ${level.level}`,
                    decisions: decs.map(d => ({
                        approverName: d.approver?.name || '?',
                        status: d.status,
                    })),
                };
            }
        }
        return prev;
    }

    return {
        isCurrentUserApprover,
        currentUserLevel,
        myEmployeeIds,
        getActionPermission,
    };
}
