// ─── Composable: Lógica de aprobación para IncidencesTable ───
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useApprovalHierarchy } from '@/Composables/payroll/useApprovalHierarchy.js';

export function useIncidencesApproval(approvalLevels) {
    const page = usePage();
    const authUserId = computed(() => page.props?.auth?.user?.id || null);
    const approvalLevelsRef = computed(() => approvalLevels.value || []);
    const hierarchy = useApprovalHierarchy(approvalLevelsRef, authUserId);

    // ¿Puede el usuario actual gestionar esta incidencia?
    function canManageIncidence(incidence) {
        if (!page.props?.auth?.user?.permissions?.includes('Aprobar tiempo extra')) return false;
        return hierarchy.getActionPermission(incidence).canAct;
    }

    // Detalle del permiso para mostrar razón
    function getIncidencePermission(incidence) {
        return hierarchy.getActionPermission(incidence);
    }

    // Resumen del pipeline de aprobación para un día
    function getDayApprovalSummary(day) {
        if (!approvalLevels.value || approvalLevels.value.length === 0) return null;

        // 🔑 Filtrar SOLO los niveles que corresponden al grupo de este empleado
        const relevantLevels = approvalLevels.value.filter(level => {
            if (!level.employee_ids || level.employee_ids.length === 0) return false;
            return level.employee_ids.map(Number).includes(Number(day.user_id));
        });

        if (relevantLevels.length === 0) return null;

        const decisions = day.approval_decisions || [];

        const levels = relevantLevels.map(level => {
            const levelDecisions = decisions.filter(d => d.level_id === level.id);
            const allApproved = levelDecisions.length > 0 && levelDecisions.every(d => d.status === 'approved');
            const hasRejection = levelDecisions.some(d => d.status === 'rejected');
            const hasAnyDecision = levelDecisions.length > 0;

            return {
                ...level,
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

    return { hierarchy, canManageIncidence, getIncidencePermission, getDayApprovalSummary, getApprovalStatusBadge };
}
