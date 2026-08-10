<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
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
// Obtener datos del usuario actual desde Inertia (API oficial)
const page = usePage();
const authUserId = computed(() => page.props?.auth?.user?.id || null);

// Datos ACTIVOS de la catorcena en pantalla (cambian al navegar entre catorcenas)
const localPayrollUsers = ref(props.payrollUsers);
const localPayrollId = ref(props.payrollId);
const localPayrollStartDate = ref(props.payrollStartDate);
const localApprovalGroups = ref(props.approvalGroups || []);

// Rango de fechas de la catorcena ACTIVA
const payrollDateRange = computed(() => {
    if (!localPayrollStartDate.value) return { start: '', end: '' };
    // Extraer solo la fecha (YYYY-MM-DD) sin importar el formato de entrada
    const dateStr = String(localPayrollStartDate.value).split('T')[0];
    const start = new Date(dateStr + 'T00:00:00');
    const end = new Date(start);
    end.setDate(end.getDate() + 13); // 14 días = start + 13
    return {
        start: dateStr,
        end: end.toISOString().split('T')[0],
    };
});

// ─── Composables (siempre basados en la catorcena ACTIVA) ───
const filters = useExtraTimeFilters(localPayrollUsers, payrollDateRange);
const hierarchy = useApprovalHierarchy(localApprovalGroups, authUserId);

// IDs de empleados en scope: null = sin jerarquía configurada (se ven todos)
const scopedEmployeeIds = computed(() => {
    if (!localApprovalGroups.value || localApprovalGroups.value.length === 0) return null;
    return hierarchy.myEmployeeIds.value;
});

const records = useExtraTimeRecords(localPayrollUsers, filters, scopedEmployeeIds);

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

// ¿Se deben mostrar los botones de acción masiva?
const canDoMassActions = computed(() => {
    if (!localApprovalGroups.value || localApprovalGroups.value.length === 0) return true;
    if (!hierarchy.isCurrentUserApprover.value) return false;
    return actionableCount.value > 0;
});

// ─── Refresco interno tras acciones (aprobar/rechazar/revertir) ───
// Recarga la catorcena ACTIVA para reflejar los cambios sin recargar la página.
// Se usa en modo independiente (index) y cuando se está viendo otra catorcena.
async function refreshActivePayroll() {
    if (localPayrollId.value == null) return;

    // En Show viendo la catorcena actual, el padre ya recarga (router.reload) y sincroniza props
    if (props.payrollId != null && Number(localPayrollId.value) === Number(props.payrollId)) {
        return;
    }

    const id = Number(localPayrollId.value);
    loadingPayrollData.value = true;
    records.isLoadingData.value = true;
    try {
        const { data } = await axios.get(route('payrolls.extra-time-data', id));
        applyPayrollData(
            data.payrollUsers || [],
            data.payroll?.id ?? id,
            data.payroll?.start_date ?? '',
            data.approvalGroups || []
        );
    } catch (e) {
        ElMessage.error('No se pudo actualizar la información de la catorcena');
        records.isLoadingData.value = false;
    } finally {
        loadingPayrollData.value = false;
    }
}

// Interceptar 'updated' para refrescar la catorcena activa y propagarlo al padre
function actionsEmit(event, ...args) {
    if (event === 'updated') {
        refreshActivePayroll();
    }
    emit(event, ...args);
}

const actions = useExtraTimeActions(
    localPayrollId,
    records.editableRecords,
    hierarchy,
    actionsEmit
);

// ─── Navegación entre catorcenas (lista ligera: solo id, biweekly y fecha) ───
const availableYears = ref([]);
const selectedYear = ref(null);
const yearPayrolls = ref([]);
const selectedPayrollId = ref(props.payrollId);
const loadingCatorcenas = ref(false);   // Carga ligera de la lista
const loadingPayrollData = ref(false);  // Carga completa al seleccionar catorcena
let catorcenasLoaded = false;

// ID de la catorcena "actual": la del padre (Show) o la catorcena en curso (Index)
const currentPayrollId = ref(props.payrollId != null ? Number(props.payrollId) : null);

const currentYearNum = () => new Date().getFullYear();
const yearFromDate = (dateStr) => {
    const d = String(dateStr || '').split('T')[0];
    return d && d.length >= 4 ? Number(d.substring(0, 4)) : null;
};

const isViewingOtherPayroll = computed(() => {
    if (currentPayrollId.value == null) return false;
    return Number(selectedPayrollId.value) !== currentPayrollId.value;
});

// Referencia al contenido del diálogo (para controlar el scroll)
const dialogContentRef = ref(null);

// Contenedor con scroll del diálogo (el overlay de Element Plus)
function getScrollContainer() {
    let el = dialogContentRef.value;
    while (el) {
        if (el.classList && el.classList.contains('el-overlay')) {
            return el;
        }
        el = el.parentElement;
    }
    return null;
}

// Llevar el scroll del diálogo al inicio (al cambiar de catorcena)
function scrollContentToTop() {
    const container = getScrollContainer();
    if (container) container.scrollTop = 0;
}

// Cargar años disponibles + catorcenas del año seleccionado (ligero)
// En modo independiente (desde el index, sin payrollId), auto-selecciona la catorcena en curso.
async function loadCatorcenas({ autoSelectCurrent = false } = {}) {
    loadingCatorcenas.value = true;
    try {
        const { data } = await axios.get(route('payrolls.catorcenas'), {
            params: { year: selectedYear.value || undefined },
        });
        availableYears.value = data.years || [];
        yearPayrolls.value = data.payrolls || [];

        // Modo independiente: identificar la catorcena en curso (activa o la más reciente)
        if (autoSelectCurrent && props.payrollId == null && data.current_payroll) {
            currentPayrollId.value = data.current_payroll.id;
            const curYear = yearFromDate(data.current_payroll.start_date);
            // Si la catorcena en curso está en otro año, cambiar de año y recargar la lista
            if (curYear && Number(curYear) !== Number(selectedYear.value)) {
                selectedYear.value = curYear;
                await loadCatorcenas(); // sin auto-select para evitar bucle
            }
        }

        // Si la catorcena activa no está en el año elegido, dejar sin selección
        if (!yearPayrolls.value.some(p => Number(p.id) === Number(selectedPayrollId.value))) {
            selectedPayrollId.value = null;
        }

        // Auto-seleccionar y cargar la catorcena en curso (solo modo independiente)
        if (autoSelectCurrent && props.payrollId == null && currentPayrollId.value != null) {
            const isInList = yearPayrolls.value.some(p => Number(p.id) === currentPayrollId.value);
            if (isInList) {
                selectedPayrollId.value = currentPayrollId.value;
                if (currentPayrollId.value !== Number(localPayrollId.value)) {
                    await handlePayrollChange(currentPayrollId.value);
                }
            }
        }
    } catch (e) {
        ElMessage.error('No se pudieron cargar las catorcenas');
        records.isLoadingData.value = false;
    } finally {
        loadingCatorcenas.value = false;
    }
}

// Aplicar datos completos a los composables
function applyPayrollData(users, payrollId, startDate, groups) {
    localPayrollUsers.value = users;
    localPayrollId.value = payrollId;
    localPayrollStartDate.value = startDate;
    localApprovalGroups.value = groups || [];
    records.clearEditableRecords();
    records.initializeEditableRecords();
    filters.resetFilters();
    records.isLoadingData.value = false;
}

// Al seleccionar una catorcena → cargar su información completa
async function handlePayrollChange(id) {
    if (!id) return;

    // Ya está mostrando esta catorcena
    if (Number(id) === Number(localPayrollId.value)) return;

    // Volver a la catorcena actual del padre (datos ya disponibles en props)
    if (props.payrollId != null && Number(id) === Number(props.payrollId)) {
        applyPayrollData(props.payrollUsers, props.payrollId, props.payrollStartDate, props.approvalGroups);
        nextTick(() => scrollContentToTop());
        return;
    }

    // Cargar la información completa de la catorcena seleccionada
    loadingPayrollData.value = true;
    records.isLoadingData.value = true;
    try {
        const { data } = await axios.get(route('payrolls.extra-time-data', id));
        applyPayrollData(
            data.payrollUsers || [],
            data.payroll?.id ?? id,
            data.payroll?.start_date ?? '',
            data.approvalGroups || []
        );
        nextTick(() => scrollContentToTop());
    } catch (e) {
        ElMessage.error('No se pudo cargar la información de la catorcena');
        selectedPayrollId.value = Number(localPayrollId.value);
        records.isLoadingData.value = false;
    } finally {
        loadingPayrollData.value = false;
    }
}

// Al cambiar de año → recargar solo la lista ligera
function handleYearChange() {
    selectedPayrollId.value = null;
    loadCatorcenas();
}

// ─── v-model ───
const isVisible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

// ─── Watchers ───
watch(isVisible, (newVal) => {
    if (newVal) {
        records.initializeEditableRecords();
        // En modo independiente (index): mostrar carga hasta que se cargue la catorcena en curso
        records.isLoadingData.value = props.payrollId == null && localPayrollId.value == null;

        // Cargar opciones de catorcenas (una sola vez por nómina)
        if (!catorcenasLoaded) {
            if (selectedYear.value == null) {
                selectedYear.value = yearFromDate(localPayrollStartDate.value) || currentYearNum();
            }
            loadCatorcenas({ autoSelectCurrent: true });
            catorcenasLoaded = true;
        } else if (props.payrollId == null && localPayrollId.value == null) {
            // Reintento: no se logró cargar la catorcena en curso en la primera apertura
            loadCatorcenas({ autoSelectCurrent: true });
        }
    } else {
        records.isLoadingData.value = true;
        filters.resetFilters();
    }
});

// Si el padre navega a otra nómina, reiniciar la navegación local
watch(() => props.payrollId, (newId, oldId) => {
    if (newId && newId !== oldId) {
        catorcenasLoaded = false;
        currentPayrollId.value = Number(newId);
        selectedPayrollId.value = newId;
        selectedYear.value = yearFromDate(props.payrollStartDate) || currentYearNum();
        applyPayrollData(props.payrollUsers, props.payrollId, props.payrollStartDate, props.approvalGroups);
        nextTick(() => scrollContentToTop());
        if (isVisible.value) {
            loadCatorcenas();
        }
    }
});

// Refresco de datos del padre (router.reload con preserveState)
watch(() => props.payrollUsers, () => {
    if (isVisible.value && !records.isLoadingData.value && !actions.isProcessing.value) {
        // Solo sincronizar si seguimos viendo la catorcena del padre
        if (Number(selectedPayrollId.value) === Number(props.payrollId)) {
            localPayrollUsers.value = props.payrollUsers;
            localApprovalGroups.value = props.approvalGroups || [];
            localPayrollStartDate.value = props.payrollStartDate;
            localPayrollId.value = props.payrollId;
            records.initializeEditableRecords();
            filters.resetFilters();
        }
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
        <!-- Navegación entre catorcenas (lista ligera por año) -->
        <div class="mb-4 flex flex-wrap items-center gap-3 bg-indigo-50/70 border border-indigo-100 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-indigo-500"></i>
                <span class="text-sm font-bold text-gray-700">Catorcena:</span>
            </div>

            <!-- Selector de año -->
            <el-select
                v-model="selectedYear"
                placeholder="Año"
                class="!w-28"
                :disabled="loadingPayrollData || actions.isProcessing.value"
                @change="handleYearChange"
            >
                <el-option v-for="y in availableYears" :key="y" :label="String(y)" :value="y" />
            </el-select>

            <!-- Selector de catorcena (solo id, biweekly y fecha; info completa al seleccionar) -->
            <el-select
                v-model="selectedPayrollId"
                placeholder="Seleccionar catorcena"
                class="!w-80"
                filterable
                :loading="loadingCatorcenas"
                :disabled="loadingPayrollData || actions.isProcessing.value"
                @change="handlePayrollChange"
            >
                <el-option
                    v-for="p in yearPayrolls"
                    :key="p.id"
                    :label="`Catorcena ${p.biweekly} — ${p.start_date.split('T')[0]}`"
                    :value="p.id"
                />
            </el-select>

            <!-- Indicadores de estado -->
            <span v-if="loadingPayrollData" class="flex items-center gap-2 text-xs text-indigo-600 font-semibold">
                <i class="fa-solid fa-circle-notch animate-spin"></i> Cargando catorcena...
            </span>
            <span v-else-if="isViewingOtherPayroll" class="text-[11px] bg-amber-100 text-amber-700 px-2 py-1 rounded-full font-bold border border-amber-200">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i> Viendo otra catorcena
            </span>
            <span v-else class="text-[11px] bg-green-100 text-green-700 px-2 py-1 rounded-full font-bold border border-green-200">
                <i class="fa-solid fa-check mr-1"></i> Catorcena actual
            </span>
        </div>

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

        <!-- Contenido + Overlay de carga (el contenido permanece montado para conservar el scroll) -->
        <div ref="dialogContentRef" class="relative">
            <!-- Contenido unificado (siempre montado) -->
            <div class="mb-4 animate-in fade-in duration-300"
                :class="{ 'opacity-40 pointer-events-none select-none': records.isLoadingData.value }">
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
                    :approvalGroups="localApprovalGroups"
                    :actionableCount="actionableCount"
                    @approve-single="actions.approveSingle"
                    @reject-single="actions.rejectSingle"
                    @revert-single="actions.revertSingle"
                    @approve-employee="actions.approveEmployee"
                    @reject-employee="actions.rejectEmployee"
                />
            </div>

            <!-- Overlay de carga (no desmonta el contenido → no pierde el scroll) -->
            <div v-if="records.isLoadingData.value"
                class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/70 backdrop-blur-[2px] rounded-lg min-h-[300px]">
                <i class="fa-solid fa-circle-notch animate-spin text-5xl text-indigo-500 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-700">Cargando registros...</h3>
                <p class="text-sm text-gray-500 mt-1">Preparando la información de tiempo extra</p>
            </div>
        </div>
    </el-dialog>
</template>
