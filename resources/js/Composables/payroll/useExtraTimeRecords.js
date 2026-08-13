// ─── Composable: Registros de tiempo extra (pendientes/resueltos) ───
import { ref, computed } from 'vue';

/**
 * @param {Ref<Array>} payrollUsers - Lista de usuarios con incidencias
 * @param {Object} filters - Objeto con filtros (useExtraTimeFilters)
 * @param {Ref<Set|Array>} employeeIds - (Opcional) IDs de empleados a incluir. Si se omite, se muestran todos.
 */
export function useExtraTimeRecords(payrollUsers, filters, employeeIds = null) {
    // Estado de carga diferida
    const isLoadingData = ref(true);

    // Registros editables (horas/minutos/comentarios por celda)
    const editableRecords = ref({});

    // Helper: ¿el empleado está en el scope del aprobador?
    function isInScope(userId) {
        if (!employeeIds || !employeeIds.value) return true;
        const ids = employeeIds.value;
        // Compatible con Set y Array
        if (ids instanceof Set) return ids.has(Number(userId));
        return ids.includes(Number(userId));
    }

    // Inicializar registros editables (incluye ya decididos para permitir cambios)
    function initializeEditableRecords() {
        payrollUsers.value?.forEach(item => {
            if (!isInScope(item.user.id)) return;
            item.incidences.forEach(inc => {
                if (inc.extra_hours > 0 || inc.extra_minutes > 0) {
                    const dateStr = inc.date.split('T')[0];
                    const key = `${item.user.id}_${dateStr}`;
                    if (!editableRecords.value[key]) {
                        // Si ya fue aprobado, usar los valores aprobados como base
                        const useApproved = inc.approved_at && (inc.approved_extra_hours > 0 || inc.approved_extra_minutes > 0);
                        if (useApproved) {
                            editableRecords.value[key] = {
                                hours: (inc.approved_extra_hours || 0),
                                minutes: (inc.approved_extra_minutes || 0),
                                comments: inc.comment?.comments || '',
                            };
                        } else {
                            // Si está pendiente, usar el "acuerdo" perseguido de
                            // niveles anteriores (el ajuste que hizo el nivel que aprobó)
                            // para que los siguientes niveles lo vean pre-cargado.
                            editableRecords.value[key] = {
                                hours: (inc.proposed_extra_hours ?? inc.extra_hours) || 0,
                                minutes: (inc.proposed_extra_minutes ?? inc.extra_minutes) || 0,
                                comments: inc.comment?.comments || '',
                            };
                        }
                    }
                }
            });
        });
    }

    // Limpiar registros editables
    function clearEditableRecords() {
        editableRecords.value = {};
    }

    // ─── Registros pendientes (planos, filtrados) ───
    const pendingRecords = computed(() => {
        const records = [];
        payrollUsers.value?.forEach(item => {
            if (!isInScope(item.user.id)) return;
            if (!filters.passesDeptFilter(item.user)) return;

            item.incidences.forEach(inc => {
                if (!inc.approved_at && (inc.extra_hours > 0 || inc.extra_minutes > 0)) {
                    if (!filters.passesCommentFilter(inc)) return;
                    if (!filters.passesDateFilter(inc)) return;

                    records.push({
                        user: item.user,
                        incidence: inc,
                        date: inc.date.split('T')[0],
                        requestedStr: `${inc.extra_hours || 0}h ${inc.extra_minutes || 0}m`
                    });
                }
            });
        });
        return records.sort((a, b) => new Date(a.date) - new Date(b.date));
    });

    // ─── Registros resueltos (planos, filtrados) ───
    const resolvedRecords = computed(() => {
        const records = [];
        payrollUsers.value?.forEach(item => {
            if (!isInScope(item.user.id)) return;
            if (!filters.passesDeptFilter(item.user)) return;

            item.incidences.forEach(inc => {
                if (inc.approved_at && (inc.extra_hours > 0 || inc.extra_minutes > 0 || inc.approved_extra_hours > 0)) {
                    if (!filters.passesCommentFilter(inc)) return;
                    if (!filters.passesDateFilter(inc)) return;

                    records.push({
                        user: item.user,
                        incidence: inc,
                        date: inc.date.split('T')[0],
                        requestedStr: `${inc.extra_hours || 0}h ${inc.extra_minutes || 0}m`,
                        approvedStr: `${inc.approved_extra_hours || 0}h ${inc.approved_extra_minutes || 0}m`,
                        isRejected: inc.approved_extra_hours === 0 && inc.approved_extra_minutes === 0,
                        commentText: inc.comment?.comments || 'Sin comentarios'
                    });
                }
            });
        });
        return records.sort((a, b) => new Date(b.date) - new Date(a.date));
    });

    // ─── Agrupación por empleado (pendientes) ───
    const groupedPendingRecords = computed(() => {
        const groups = {};
        pendingRecords.value.forEach(record => {
            if (!groups[record.user.id]) {
                groups[record.user.id] = {
                    user: record.user,
                    records: [],
                    totalPendingMinutes: 0,
                };
            }
            groups[record.user.id].records.push(record);
            groups[record.user.id].totalPendingMinutes +=
                (record.incidence.extra_hours || 0) * 60 + (record.incidence.extra_minutes || 0);
        });
        return Object.values(groups).sort((a, b) => a.user.name.localeCompare(b.user.name));
    });

    // ─── Agrupación por empleado (resueltos) ───
    const groupedResolvedRecords = computed(() => {
        const groups = {};
        resolvedRecords.value.forEach(record => {
            if (!groups[record.user.id]) {
                groups[record.user.id] = {
                    user: record.user,
                    records: [],
                    totalApprovedMinutes: 0,
                };
            }
            groups[record.user.id].records.push(record);
            groups[record.user.id].totalApprovedMinutes +=
                (record.incidence.approved_extra_hours || 0) * 60 + (record.incidence.approved_extra_minutes || 0);
        });
        return Object.values(groups).sort((a, b) => a.user.name.localeCompare(b.user.name));
    });

    // Helper: valor efectivo del "acuerdo" de tiempo extra (propuesto por niveles anteriores si existe)
    const effectiveHours = (inc) => (inc.proposed_extra_hours ?? inc.extra_hours) || 0;
    const effectiveMinutes = (inc) => (inc.proposed_extra_minutes ?? inc.extra_minutes) || 0;
    const effectiveStr = (inc) => `${effectiveHours(inc)}h ${effectiveMinutes(inc)}m`;

    // ─── VISTA UNIFICADA: Todos los registros con tiempo extra ───
    const unifiedRecords = computed(() => {
        const records = [];
        payrollUsers.value?.forEach(item => {
            if (!isInScope(item.user.id)) return;
            if (!filters.passesDeptFilter(item.user)) return;

            item.incidences.forEach(inc => {
                if (!(inc.extra_hours > 0 || inc.extra_minutes > 0)) return;
                if (!filters.passesCommentFilter(inc)) return;
                if (!filters.passesProjectFilter(inc)) return;
                if (!filters.passesDateFilter(inc)) return;

                records.push({
                    user: item.user,
                    incidence: inc,
                    date: inc.date.split('T')[0],
                    // Mostrar el acuerdo ajustado (si lo hay) como valor principal
                    requestedStr: effectiveStr(inc),
                    originalStr: `${inc.extra_hours || 0}h ${inc.extra_minutes || 0}m`,
                });
            });
        });
        return records.sort((a, b) => new Date(a.date) - new Date(b.date));
    });

    // ─── Agrupación unificada por empleado ───
    const groupedUnifiedRecords = computed(() => {
        const groups = {};
        unifiedRecords.value.forEach(record => {
            if (!groups[record.user.id]) {
                groups[record.user.id] = {
                    user: record.user,
                    records: [],
                    totalMinutes: 0,
                };
            }
            groups[record.user.id].records.push(record);
            groups[record.user.id].totalMinutes +=
                effectiveHours(record.incidence) * 60 + effectiveMinutes(record.incidence);
        });
        return Object.values(groups).sort((a, b) => a.user.name.localeCompare(b.user.name));
    });

    return {
        isLoadingData,
        editableRecords,
        initializeEditableRecords,
        clearEditableRecords,
        pendingRecords,
        resolvedRecords,
        groupedPendingRecords,
        groupedResolvedRecords,
        unifiedRecords,
        groupedUnifiedRecords,
    };
}
