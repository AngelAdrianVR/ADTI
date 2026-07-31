// ─── Composable: Lógica de aprobación para IncidencesTable ───
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useApprovalHierarchy } from '@/Composables/payroll/useApprovalHierarchy.js';

export function useIncidencesApproval(approvalGroups) {
    const page = usePage();
    const authUserId = computed(() => page.props?.auth?.user?.id || null);
    const approvalGroupsRef = computed(() => approvalGroups.value || []);
    const hierarchy = useApprovalHierarchy(approvalGroupsRef, authUserId);

    // ¿Puede el usuario actual gestionar esta incidencia?
    function canManageIncidence(incidence) {
        if (!page.props?.auth?.user?.permissions?.includes('Aprobar tiempo extra')) return false;
        return hierarchy.getActionPermission(incidence).canAct;
    }

    // ¿Puede el usuario actual revertir una decisión de esta incidencia?
    function canRevertIncidence(incidence) {
        if (!page.props?.auth?.user?.permissions?.includes('Aprobar tiempo extra')) return false;
        return hierarchy.canRevertDecision(incidence);
    }

    // Detalle del permiso para mostrar razón
    function getIncidencePermission(incidence) {
        return hierarchy.getActionPermission(incidence);
    }

    // Resumen del pipeline de aprobación para un día
    function getDayApprovalSummary(day) {
        if (!approvalGroups.value || approvalGroups.value.length === 0) return null;

        // Encontrar el grupo al que pertenece este empleado
        const group = approvalGroups.value.find(g =>
            (g.employee_ids || []).map(Number).includes(Number(day.user_id))
        );
        if (!group || !group.levels || group.levels.length === 0) return null;

        const decisions = day.approval_decisions || [];

        const levels = group.levels.map(level => {
            const levelDecisions = decisions.filter(d => d.level_id === level.id);
            const allApproved = levelDecisions.length > 0 && levelDecisions.every(d => d.status === 'approved');
            const hasRejection = levelDecisions.some(d => d.status === 'rejected');
            const hasAnyDecision = levelDecisions.length > 0;

            return {
                id: level.id,
                level: level.level,
                name: level.name,
                approvers: level.approvers || [],
                decisions: levelDecisions,
                status: hasRejection ? 'rejected' : (allApproved ? 'approved' : (hasAnyDecision ? 'pending' : 'waiting')),
            };
        });

        const firstPendingLevel = levels.find(l => l.status === 'pending' || l.status === 'waiting');
        const globalRejected = levels.some(l => l.status === 'rejected');
        const allLevelsApproved = levels.every(l => l.status === 'approved');

        return { levels, globalStatus: globalRejected ? 'rejected' : (allLevelsApproved ? 'approved' : 'pending'), firstPendingLevel };
    }

    // Badge de estado visual
    function getApprovalStatusBadge(status) {
        switch (status) {
            case 'approved': return { bg: 'bg-green-100', text: 'text-green-700', border: 'border-green-300', icon: 'fa-check-circle', label: 'Aprobado' };
            case 'rejected': return { bg: 'bg-red-100', text: 'text-red-700', border: 'border-red-300', icon: 'fa-xmark-circle', label: 'Rechazado' };
            case 'pending': return { bg: 'bg-blue-100', text: 'text-blue-700', border: 'border-blue-300', icon: 'fa-clock', label: 'En proceso' };
            case 'waiting': return { bg: 'bg-gray-100', text: 'text-gray-500', border: 'border-gray-300', icon: 'fa-circle', label: 'En espera' };
            default: return { bg: 'bg-gray-100', text: 'text-gray-400', border: 'border-gray-200', icon: 'fa-circle', label: '—' };
        }
    }

    return { hierarchy, canManageIncidence, canRevertIncidence, getIncidencePermission, getDayApprovalSummary, getApprovalStatusBadge };
}
