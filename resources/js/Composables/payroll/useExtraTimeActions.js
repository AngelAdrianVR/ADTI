// ─── Composable: Acciones de tiempo extra (aprobar/rechazar/revertir) ───
import { ref, unref } from 'vue';
import axios from 'axios';
import { ElNotification, ElMessageBox } from 'element-plus';

export function useExtraTimeActions(payrollId, editableRecords, hierarchy, emit) {
    const isProcessing = ref(false);
    const processingRow = ref(null);
    const processingGroup = ref(null);
    const processingType = ref(null);
    const bulkProgress = ref(0);
    const bulkActionType = ref(null);

    // Ruta a usar: POST con parámetro de nómina (unref desenvuelve el Ref de Vue)
    function getApproveRoute() {
        return route('payrolls.extra-hours-decide', unref(payrollId));
    }

    // ─── Individual ───
    async function approveSingle(record) {
        const key = `${record.user.id}_${record.date}`;
        const data = editableRecords.value[key];

        processingRow.value = key;
        processingType.value = 'approve';
        try {
            await axios.post(getApproveRoute(), {
                payroll_user_id: record.incidence.id,
                status: 'approved',
                approved_extra_hours: data?.hours ?? record.incidence.extra_hours,
                approved_extra_minutes: data?.minutes ?? record.incidence.extra_minutes,
                comments: '',
            });

            // Actualizar localmente la incidencia sin necesidad de recargar la página
            if (!record.incidence.approval_decisions) {
                record.incidence.approval_decisions = [];
            }
            // Agregar/actualizar la decisión del usuario actual en los datos locales
            const currentUserId = window.__inertia_page?.props?.auth?.user?.id || null;
            const currentLevelId = record.incidence.current_approval_level_id || null;
            const existingIdx = record.incidence.approval_decisions.findIndex(
                d => d.approver?.id === currentUserId && d.level_id === currentLevelId
            );
            const newDecision = {
                id: Date.now(), // temporal, se sobrescribe en la recarga
                level_id: currentLevelId, // ¡Clave para que getActionPermission lo detecte!
                status: 'approved',
                approver: {
                    id: currentUserId,
                    name: window.__inertia_page?.props?.auth?.user?.name || '',
                    profile_photo_url: window.__inertia_page?.props?.auth?.user?.profile_photo_url || '',
                },
                decided_at: new Date().toISOString(),
            };
            if (existingIdx >= 0) {
                record.incidence.approval_decisions[existingIdx] = newDecision;
            } else {
                record.incidence.approval_decisions.push(newDecision);
            }

            // Solo marcar como resuelto (pasar a Historial) si es último nivel o rechazo.
            // Para niveles intermedios, se queda en Pendientes con la decisión visible.
            // El backend ya maneja esta lógica; aquí reflejamos lo mismo.
            // Como no sabemos si es último nivel desde aquí, NO seteamos approved_at.
            // La recarga (emit('updated')) sincronizará con el backend.

            ElNotification.success('Tiempo extra aprobado para ' + record.user.name.split(' ')[0]);

            // Recargar datos para reflejar cambios en IncidencesTable
            emit('updated');
        } catch (e) {
            const msg = e.response?.data?.error || 'Ocurrió un error al aprobar';
            ElNotification.error(msg);
        } finally {
            processingRow.value = null;
            processingType.value = null;
        }
    }

    async function rejectSingle(record) {
        const key = `${record.user.id}_${record.date}`;
        const data = editableRecords.value[key];

        processingRow.value = key;
        processingType.value = 'reject';
        try {
            await axios.post(getApproveRoute(), {
                payroll_user_id: record.incidence.id,
                status: 'rejected',
                comments: '',
            });

            // Actualizar localmente la decisión en el array approval_decisions
            if (!record.incidence.approval_decisions) {
                record.incidence.approval_decisions = [];
            }
            const currentUserId = window.__inertia_page?.props?.auth?.user?.id || null;
            const currentLevelId = record.incidence.current_approval_level_id || null;
            const existingIdx = record.incidence.approval_decisions.findIndex(
                d => d.approver?.id === currentUserId && d.level_id === currentLevelId
            );
            const newDecision = {
                id: Date.now(),
                level_id: currentLevelId,
                status: 'rejected',
                approver: {
                    id: currentUserId,
                    name: window.__inertia_page?.props?.auth?.user?.name || '',
                    profile_photo_url: window.__inertia_page?.props?.auth?.user?.profile_photo_url || '',
                },
                decided_at: new Date().toISOString(),
            };
            if (existingIdx >= 0) {
                record.incidence.approval_decisions[existingIdx] = newDecision;
            } else {
                record.incidence.approval_decisions.push(newDecision);
            }

            ElNotification.success('Tiempo extra rechazado para ' + record.user.name.split(' ')[0]);
            emit('updated');
        } catch (e) {
            const msg = e.response?.data?.error || 'Ocurrió un error al rechazar';
            ElNotification.error(msg);
        } finally {
            processingRow.value = null;
            processingType.value = null;
        }
    }

    async function revertSingle(record) {
        const key = `${record.user.id}_${record.date}`;
        processingRow.value = key;
        processingType.value = 'revert';
        try {
            await axios.delete(route('payrolls.extra-hours-revert'), {
                data: { payroll_user_id: record.incidence.id },
            });
            ElNotification.success('Resolución revertida');
            emit('updated');
        } catch (e) {
            const msg = e.response?.data?.error || e.response?.data?.message || 'Ocurrió un error al revertir';
            ElNotification.error(msg);
        } finally {
            processingRow.value = null;
            processingType.value = null;
        }
    }

    // ─── Por empleado ───
    async function approveEmployee(group) {
        // Filtrar solo los accionables (no decididos aún)
        const actionable = group.records.filter(r => {
            const perm = hierarchy.getActionPermission(r.incidence);
            return perm.canAct && !perm.alreadyDecided;
        });
        if (actionable.length === 0) {
            ElNotification.warning('No hay registros pendientes para este empleado.');
            return;
        }

        try {
            await ElMessageBox.confirm(
                `¿APROBAR los ${actionable.length} registros pendientes de ${group.user.name}? Una vez decidido no se podrá cambiar.`,
                'Aprobar todo del empleado',
                { confirmButtonText: 'Sí, aprobar todo', cancelButtonText: 'Cancelar', type: 'warning' }
            );

            isProcessing.value = true;
            processingGroup.value = group.user.id;
            bulkActionType.value = 'approve';

            await Promise.all(actionable.map(record => {
                const data = editableRecords.value[`${record.user.id}_${record.date}`];
                return axios.post(getApproveRoute(), {
                    payroll_user_id: record.incidence.id,
                    status: 'approved',
                    approved_extra_hours: data?.hours ?? record.incidence.extra_hours,
                    approved_extra_minutes: data?.minutes ?? record.incidence.extra_minutes,
                    comments: '',
                });
            }));

            ElNotification.success(`Tiempo extra aprobado para ${group.user.name.split(' ')[0]}`);
            emit('updated');
        } catch (e) {
            if (e !== 'cancel') ElNotification.error('Error al procesar al empleado');
        } finally {
            isProcessing.value = false;
            processingGroup.value = null;
            bulkActionType.value = null;
        }
    }

    async function rejectEmployee(group) {
        const actionable = group.records.filter(r => {
            const perm = hierarchy.getActionPermission(r.incidence);
            return perm.canAct && !perm.alreadyDecided;
        });
        if (actionable.length === 0) {
            ElNotification.warning('No hay registros pendientes para este empleado.');
            return;
        }

        try {
            await ElMessageBox.confirm(
                `¿RECHAZAR los ${actionable.length} registros pendientes de ${group.user.name}? Una vez decidido no se podrá cambiar.`,
                'Rechazar todo del empleado',
                { confirmButtonText: 'Sí, rechazar todo', cancelButtonText: 'Cancelar', type: 'error' }
            );

            isProcessing.value = true;
            processingGroup.value = group.user.id;
            bulkActionType.value = 'reject';

            await Promise.all(actionable.map(record => {
                const data = editableRecords.value[`${record.user.id}_${record.date}`];
                return axios.post(getApproveRoute(), {
                    payroll_user_id: record.incidence.id,
                    status: 'rejected',
                    comments: '',
                });
            }));

            ElNotification.success(`Tiempo extra rechazado para ${group.user.name.split(' ')[0]}`);
            emit('updated');
        } catch (e) {
            if (e !== 'cancel') ElNotification.error('Error al procesar al empleado');
        } finally {
            isProcessing.value = false;
            processingGroup.value = null;
            bulkActionType.value = null;
        }
    }

    // ─── Masivo ───
    async function approveAll(records) {
        if (records.length === 0) return;
        try {
            await ElMessageBox.confirm(
                `Se aprobarán los ${records.length} registros mostrados. ¿Continuar?`,
                'Aprobar todo',
                { confirmButtonText: 'Sí, aprobar todo', cancelButtonText: 'Cancelar', type: 'warning' }
            );

            isProcessing.value = true;
            bulkActionType.value = 'approve';
            bulkProgress.value = 0;

            const total = records.length;
            let completed = 0;
            const chunkSize = 5;

            for (let i = 0; i < total; i += chunkSize) {
                const chunk = records.slice(i, i + chunkSize);
                await Promise.all(chunk.map(record => {
                    const data = editableRecords.value[`${record.user.id}_${record.date}`];
                    return axios.post(getApproveRoute(), {
                        payroll_user_id: record.incidence.id,
                        status: 'approved',
                        approved_extra_hours: data?.hours ?? record.incidence.extra_hours,
                        approved_extra_minutes: data?.minutes ?? record.incidence.extra_minutes,
                        comments: data?.comments ?? '',
                    }).then(() => {
                        completed++;
                        bulkProgress.value = Math.round((completed / total) * 100);
                    });
                }));
            }

            ElNotification.success('Todo el tiempo extra fue aprobado');
            emit('updated');
        } catch (e) {
            if (e !== 'cancel') ElNotification.error('Error al procesar el lote');
        } finally {
            isProcessing.value = false;
            bulkActionType.value = null;
            bulkProgress.value = 0;
        }
    }

    async function rejectAll(records) {
        if (records.length === 0) return;
        try {
            await ElMessageBox.confirm(
                `Se rechazarán los ${records.length} registros mostrados. ¿Continuar?`,
                'Rechazar todo',
                { confirmButtonText: 'Sí, rechazar todo', cancelButtonText: 'Cancelar', type: 'error' }
            );

            isProcessing.value = true;
            bulkActionType.value = 'reject';
            bulkProgress.value = 0;

            const total = records.length;
            let completed = 0;
            const chunkSize = 5;

            for (let i = 0; i < total; i += chunkSize) {
                const chunk = records.slice(i, i + chunkSize);
                await Promise.all(chunk.map(record => {
                    const data = editableRecords.value[`${record.user.id}_${record.date}`];
                    return axios.post(getApproveRoute(), {
                        payroll_user_id: record.incidence.id,
                        status: 'rejected',
                        comments: data?.comments ?? '',
                    }).then(() => {
                        completed++;
                        bulkProgress.value = Math.round((completed / total) * 100);
                    });
                }));
            }

            ElNotification.success('Todo el tiempo extra fue rechazado');
            emit('updated');
        } catch (e) {
            if (e !== 'cancel') ElNotification.error('Error al procesar el lote');
        } finally {
            isProcessing.value = false;
            bulkActionType.value = null;
            bulkProgress.value = 0;
        }
    }

    return {
        isProcessing,
        processingRow,
        processingGroup,
        processingType,
        bulkProgress,
        bulkActionType,
        approveSingle,
        rejectSingle,
        revertSingle,
        approveEmployee,
        rejectEmployee,
        approveAll,
        rejectAll,
    };
}
