// ─── Composable: Filtros del panel de tiempo extra ───
import { ref, computed } from 'vue';

export function useExtraTimeFilters(payrollUsers) {
    const selectedDepartment = ref('');
    const selectedCommentFilter = ref('all');

    // Departamentos disponibles
    const availableDepartments = computed(() => {
        const depts = new Set();
        payrollUsers.value?.forEach(item => {
            if (item.user.org_props?.department) {
                depts.add(item.user.org_props.department);
            }
        });
        return Array.from(depts).sort();
    });

    // Helper: ¿tiene comentario una incidencia?
    function hasComment(inc) {
        return inc.comment?.comments && inc.comment.comments.trim().length > 0;
    }

    // Helper: ¿pasa el filtro de comentarios?
    function passesCommentFilter(inc) {
        if (selectedCommentFilter.value === 'all') return true;
        const has = hasComment(inc);
        if (selectedCommentFilter.value === 'with') return has;
        if (selectedCommentFilter.value === 'without') return !has;
        return true;
    }

    // Helper: ¿pasa el filtro de departamento?
    function passesDeptFilter(user) {
        if (!selectedDepartment.value) return true;
        return user.org_props?.department === selectedDepartment.value;
    }

    // Resetear filtros
    function resetFilters() {
        selectedDepartment.value = '';
        selectedCommentFilter.value = 'all';
    }

    // Texto descriptivo de filtros activos
    const activeFiltersLabel = computed(() => {
        const parts = [];
        if (selectedDepartment.value) parts.push(`depto: ${selectedDepartment.value}`);
        if (selectedCommentFilter.value !== 'all') {
            parts.push(selectedCommentFilter.value === 'with' ? 'con comentarios' : 'sin comentarios');
        }
        return parts.length > 0 ? parts.join(', ') : null;
    });

    return {
        selectedDepartment,
        selectedCommentFilter,
        availableDepartments,
        hasComment,
        passesCommentFilter,
        passesDeptFilter,
        resetFilters,
        activeFiltersLabel,
    };
}
