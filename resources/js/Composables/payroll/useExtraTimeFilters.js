// ─── Composable: Filtros del panel de tiempo extra ───
import { ref, computed } from 'vue';

export function useExtraTimeFilters(payrollUsers) {
    const selectedDepartment = ref('');
    const selectedCommentFilter = ref('all');
    const selectedProject = ref('');

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

    // Proyectos disponibles (de incidencias vinculadas)
    const availableProjects = computed(() => {
        const projects = new Map();
        payrollUsers.value?.forEach(item => {
            item.incidences.forEach(inc => {
                if (inc.project) {
                    projects.set(inc.project.id, { id: inc.project.id, name: inc.project.name, client: inc.project.client });
                }
            });
        });
        return Array.from(projects.values()).sort((a, b) => a.name.localeCompare(b.name));
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

    // Helper: ¿pasa el filtro de proyecto?
    function passesProjectFilter(inc) {
        if (!selectedProject.value) return true;
        return inc.project?.id === selectedProject.value;
    }

    // Resetear filtros
    function resetFilters() {
        selectedDepartment.value = '';
        selectedCommentFilter.value = 'all';
        selectedProject.value = '';
    }

    // Texto descriptivo de filtros activos
    const activeFiltersLabel = computed(() => {
        const parts = [];
        if (selectedDepartment.value) parts.push(`depto: ${selectedDepartment.value}`);
        if (selectedCommentFilter.value !== 'all') {
            parts.push(selectedCommentFilter.value === 'with' ? 'con comentarios' : 'sin comentarios');
        }
        if (selectedProject.value) {
            const proj = availableProjects.value.find(p => p.id === selectedProject.value);
            if (proj) parts.push(`proyecto: ${proj.name}`);
        }
        return parts.length > 0 ? parts.join(', ') : null;
    });

    return {
        selectedDepartment,
        selectedCommentFilter,
        selectedProject,
        availableDepartments,
        availableProjects,
        hasComment,
        passesCommentFilter,
        passesDeptFilter,
        passesProjectFilter,
        resetFilters,
        activeFiltersLabel,
    };
}
