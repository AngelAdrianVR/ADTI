<script setup>
import { ref, computed, watch, toRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useExtraTimeFilters } from '@/Composables/payroll/useExtraTimeFilters.js';
import { useExtraTimeRecords } from '@/Composables/payroll/useExtraTimeRecords.js';
import { useExtraTimeActions } from '@/Composables/payroll/useExtraTimeActions.js';
import { useApprovalHierarchy } from '@/Composables/payroll/useApprovalHierarchy.js';
import ExtraTimeUnifiedView from '@/Components/MyComponents/Payroll/ExtraTimeUnifiedView.vue';

const props = defineProps({
    modelValue: Boolean,
    payrollUsers: Array,
    payrollId: Number,
    approvalGroups: { type: Array, default: () => [] },
    employeeIds: { type: Object, default: null }, // Set de IDs de empleados del aprobador
    payrollStartDate: { type: String, default: '' }, // Fecha inicio de la catorcena (ISO YYYY-MM-DD)
});

const emit = defineEmits(['update:modelValue', 'updated']);

// ─── Estado base ───
const payrollUsersRef = toRef(props, 'payrollUsers');

// Obtener datos del usuario actual desde Inertia (API oficial)
const page = usePage();
const authUserId = computed(() => page.props?.auth?.user?.id || null);

// approvalLevels como computed para asegurar reactividad
const approvalGroupsRef = computed(() => props.approvalGroups || []);

// ─── Rango de fechas de la catorcena ───
const payrollDateRange = computed(() => {
    if (!props.payrollStartDate) return { start: '', end: '' };
    // Extraer solo la fecha (YYYY-MM-DD) sin importar el formato de entrada
    const dateStr = String(props.payrollStartDate).split('T')[0];
    const start = new Date(dateStr + 'T00:00:00');
    const end = new Date(start);
    end.setDate(end.getDate() + 13); // 14 días = start + 13
    return {
        start: dateStr,
        end: end.toISOString().split('T')[0],
    };
});

// ─── Composables ───
const filters = useExtraTimeFilters(payrollUsersRef, payrollDateRange);
const hierarchy = useApprovalHierarchy(approvalGroupsRef, authUserId);

// ¿Se deben mostrar los botones de acción masiva?
const canDoMassActions = computed(() => {
    if (!approvalGroupsRef.value || approvalGroupsRef.value.length === 0) return true;
    if (!hierarchy.isCurrentUserApprover.value) return false;
    return actionableCount.value > 0;
});

const records = useExtraTimeRecords(payrollUsersRef, filters, toRef(props, 'employeeIds'));

// Contar cuántos registros son accionables por el usuario actual
const actionableCount = computed(() => {
    if (!hierarchy.isCurrentUserApprover.value) return 0;
    let count = 0;
    records.unifiedRecords.value.forEach(record => {
        const perm = hierarchy.getActionPermission(record.incidence);
        if (perm.canAct && perm.isMyEmployee) count++;
    });
    return count;
});

const actions = useExtraTimeActions(
    toRef(props, 'payrollId'),
    records.editableRecords,
    hierarchy,
    emit
);

// ─── v-model ───
const isVisible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

// ─── Watchers ───
watch(isVisible, (newVal) => {
    if (newVal) {
        records.initializeEditableRecords();
        records.isLoadingData.value = false;
    } else {
        records.isLoadingData.value = true;
        filters.resetFilters();
    }
});

watch(payrollUsersRef, () => {
    if (isVisible.value && !records.isLoadingData.value && !actions.isProcessing.value) {
        records.initializeEditableRecords();
    }
}, { deep: true });
</script>

<template>
    <el-dialog
        v-model="isVisible"
        title="Panel de control de tiempo extra"
        width="90%"
        class="!rounded-xl max-w-7xl mx-auto"
        destroy-on-close
        :close-on-click-modal="!actions.isProcessing.value"
        :close-on-press-escape="!actions.isProcessing.value"
        :show-close="!actions.isProcessing.value"
    >
        <!-- Filtros -->
        <div class="mb-4 flex flex-wrap items-center justify-between bg-gray-50 p-3 rounded-lg border border-gray-200 gap-3">
            <div class="flex items-center gap-3 flex-wrap">
                <i class="fa-solid fa-filter text-gray-400"></i>
                <span class="text-sm font-semibold text-gray-700">Filtrar tabla por:</span>
                <el-select v-model="filters.selectedDepartment.value" placeholder="Todos los departamentos" clearable
                    class="!w-44" :disabled="actions.isProcessing.value">
                    <el-option v-for="dept in filters.availableDepartments.value" :key="dept" :label="dept" :value="dept" />
                </el-select>
                <span class="text-gray-300">|</span>
                <el-select v-model="filters.selectedCommentFilter.value" placeholder="Comentarios"
                    class="!w-40" :disabled="actions.isProcessing.value">
                    <el-option label="Todos" value="all" />
                    <el-option label="Con comentarios" value="with" />
                    <el-option label="Sin comentarios" value="without" />
                </el-select>
                <span class="text-gray-300">|</span>
                <el-select v-model="filters.selectedProject.value" placeholder="Todos los proyectos" clearable
                    class="!w-48" :disabled="actions.isProcessing.value">
                    <el-option v-for="proj in filters.availableProjects.value" :key="proj.id" :label="proj.name" :value="proj.id" />
                </el-select>
                <span class="text-gray-300">|</span>
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-gray-500">Fechas:</span>
                    <el-date-picker
                        v-model="filters.dateFrom.value"
                        type="date"
                        placeholder="Desde"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        size="small"
                        class="!w-32"
                        :disabled="actions.isProcessing.value"
                        clearable
                    />
                    <span class="text-gray-400 text-xs">—</span>
                    <el-date-picker
                        v-model="filters.dateTo.value"
                        type="date"
                        placeholder="Hasta"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        size="small"
                        class="!w-32"
                        :disabled="actions.isProcessing.value"
                        clearable
                    />
                </div>
            </div>

            <!-- Info de jerarquía -->
            <div v-if="hierarchy.isCurrentUserApprover.value" class="flex items-center gap-2 text-xs">
                <span class="bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-bold border border-indigo-200">
                    <i class="fa-solid fa-sitemap mr-1"></i>
                    Aprobador
                </span>
            </div>
            <div v-else class="flex items-center gap-2 text-xs">
                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full font-bold border border-gray-200">
                    <i class="fa-solid fa-eye mr-1"></i> Vista general (sin jerarquía)
                </span>
            </div>
        </div>

        <!-- Carga diferida -->
        <div v-if="records.isLoadingData.value" class="py-20 flex flex-col items-center justify-center min-h-[300px]">
            <i class="fa-solid fa-circle-notch animate-spin text-5xl text-indigo-500 mb-4"></i>
            <h3 class="text-lg font-bold text-gray-700">Cargando registros...</h3>
            <p class="text-sm text-gray-500 mt-1">Preparando la información de tiempo extra</p>
        </div>

        <!-- Contenido unificado -->
        <div v-else class="mb-4 animate-in fade-in duration-300">
            <ExtraTimeUnifiedView
                :groups="records.groupedUnifiedRecords.value"
                :totalRecords="records.unifiedRecords.value.length"
                :editableRecords="records.editableRecords.value"
                :isProcessing="actions.isProcessing.value"
                :processingRow="actions.processingRow.value"
                :processingGroup="actions.processingGroup.value"
                :processingType="actions.processingType.value"
                :activeFiltersLabel="filters.activeFiltersLabel.value"
                :hierarchy="hierarchy"
                :approvalGroups="approvalGroupsRef.value"
                :actionableCount="actionableCount"
                @approve-single="actions.approveSingle"
                @reject-single="actions.rejectSingle"
                @revert-single="actions.revertSingle"
                @approve-employee="actions.approveEmployee"
                @reject-employee="actions.rejectEmployee"
            />
        </div>
    </el-dialog>
</template>
