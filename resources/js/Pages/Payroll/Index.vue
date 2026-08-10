<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ExtraTimeManagementModal from './Partials/ExtraTimeManagementModal.vue';
import { format, addDays, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';

const props = defineProps({
    payrolls: Array,
    users: {
        type: Array,
        default: () => []
    }
});

// State
const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

// Modal de gestión de horas extra (abre la catorcena en curso sin entrar a ella)
const showExtraTimeModal = ref(false);
const openExtraTimeManager = () => {
    showExtraTimeModal.value = true;
};

// Computed: Filtrado por búsqueda
const filteredPayrolls = computed(() => {
    if (!search.value) return props.payrolls;
    const lowerSearch = search.value.toLowerCase();
    
    return props.payrolls.filter(p => 
        p.id.toString().includes(lowerSearch) ||
        // Buscar por año o mes
        p.start_date.includes(lowerSearch)
    );
});

// Computed: Paginación (CORRECCIÓN CLAVE)
const paginatedPayrolls = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredPayrolls.value.slice(start, end);
});

// Helpers
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM, yyyy', { locale: es });
};

const getEndPeriod = (start) => {
    if (!start) return '-';
    const end = addDays(parseISO(start), 13);
    return format(end, 'dd MMM, yyyy', { locale: es });
};

const handleRowClick = (row) => {
    router.visit(route('payrolls.show', row.id));
};

const handlePageChange = (val) => {
    currentPage.value = val;
};

// ─── MODAL: Generar Recibos por Rango ───
const showReceiptsModal = ref(false);
const dateRange = ref(null);
const userSearch = ref('');
const selectedUsers = ref([]);

// Computed: departamentos y usuarios agrupados
const groupedDepartments = computed(() => {
    const depts = {};
    props.users.forEach(u => {
        const dept = u.department || 'General';
        if (!depts[dept]) depts[dept] = [];
        depts[dept].push(u);
    });
    return Object.entries(depts)
        .map(([name, users]) => ({
            name,
            users: users.sort((a, b) => a.name.localeCompare(b.name)),
        }))
        .sort((a, b) => a.name.localeCompare(b.name));
});

// Filtro por búsqueda dentro del modal
const filteredUsers = computed(() => {
    if (!userSearch.value) return props.users;
    const term = userSearch.value.toLowerCase();
    return props.users.filter(u =>
        u.name.toLowerCase().includes(term) || (u.code || '').toLowerCase().includes(term)
    );
});

// Usuarios visibles de un departamento según búsqueda (para contadores y checkboxes maestros)
const getDeptVisibleUsers = (deptName) => {
    const deptUsers = groupedDepartments.value.find(d => d.name === deptName)?.users || [];
    if (!userSearch.value) return deptUsers;
    const term = userSearch.value.toLowerCase();
    return deptUsers.filter(u =>
        u.name.toLowerCase().includes(term) || (u.code || '').toLowerCase().includes(term)
    );
};

// Checar si un departamento está completamente seleccionado (de los visibles)
const isDeptSelected = (deptName) => {
    const visible = getDeptVisibleUsers(deptName);
    return visible.length > 0 && visible.every(u => selectedUsers.value.includes(u.id));
};

// Checar si un departamento está parcialmente seleccionado
const isDeptIndeterminate = (deptName) => {
    const visible = getDeptVisibleUsers(deptName);
    const count = visible.filter(u => selectedUsers.value.includes(u.id)).length;
    return count > 0 && count < visible.length;
};

// Atajo: seleccionar / deseleccionar todo el departamento
const toggleDept = (deptName) => {
    const visible = getDeptVisibleUsers(deptName);
    const allSelected = isDeptSelected(deptName);
    if (allSelected) {
        selectedUsers.value = selectedUsers.value.filter(id => !visible.some(u => u.id === id));
    } else {
        const ids = visible.map(u => u.id);
        selectedUsers.value = [...new Set([...selectedUsers.value, ...ids])];
    }
};

// Atajo global: seleccionar / limpiar todo (según búsqueda)
const toggleSelectAllFiltered = () => {
    const visibleIds = filteredUsers.value.map(u => u.id);
    const allSelected = visibleIds.every(id => selectedUsers.value.includes(id));
    if (allSelected) {
        selectedUsers.value = selectedUsers.value.filter(id => !visibleIds.includes(id));
    } else {
        selectedUsers.value = [...new Set([...selectedUsers.value, ...visibleIds])];
    }
};

const allFilteredSelected = computed(() => {
    return filteredUsers.value.length > 0 && filteredUsers.value.every(u => selectedUsers.value.includes(u.id));
});

// Abrir modal y resetear estado
const openReceiptsModal = () => {
    userSearch.value = '';
    dateRange.value = null;
    selectedUsers.value = [];
    showReceiptsModal.value = true;
};

// Generar recibos por rango
const generateRangeReceipts = () => {
    if (!dateRange.value || !dateRange.value[0] || !dateRange.value[1]) {
        ElMessage.warning('Selecciona un rango de fechas válido.');
        return;
    }
    if (selectedUsers.value.length === 0) {
        ElMessage.warning('Selecciona al menos un usuario.');
        return;
    }
    const [start, end] = dateRange.value;
    // Validar rango máximo de 31 días (equivalente al backend)
    const daysDiff = Math.round((parseISO(end) - parseISO(start)) / (1000 * 60 * 60 * 24)) + 1;
    if (daysDiff > 31) {
        ElMessage.warning('El rango máximo permitido es de 31 días.');
        return;
    }
    const url = route('payrolls.receipts-by-range', {
        start_date: start,
        end_date: end,
        user_ids: selectedUsers.value,
    });
    window.open(url, '_blank');
};
</script>

<template>
    <AppLayout title="Nóminas">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Historial de Nóminas</h1>
                        <p class="text-xs text-gray-500 mt-1">Gestión y consulta de periodos catorcenales.</p>
                    </div>
                    
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <!-- Botón: Gestionar horas extra (sin entrar a una catorcena) -->
                        <PrimaryButton 
                            v-if="$page.props.auth.user.permissions.includes('Aprobar tiempo extra')"
                            @click="openExtraTimeManager"
                            class="!bg-indigo-600 hover:!bg-indigo-700 whitespace-nowrap"
                        >
                            <i class="fa-solid fa-stopwatch mr-2"></i> Gestionar horas extra
                        </PrimaryButton>
                        <!-- Buscador -->
                        <div class="relative w-full sm:w-64">
                            <input 
                                v-model="search" 
                                type="text" 
                                placeholder="Buscar por ID o fecha..." 
                                class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2] text-sm shadow-sm"
                            >
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                        </div>
                        <!-- Botón Generar Recibos por Rango -->
                        <PrimaryButton 
                            v-if="$page.props.auth.user.permissions.includes('Ver pre-nominas')"
                            @click="openReceiptsModal"
                            class="!bg-teal-600 hover:!bg-teal-700 whitespace-nowrap"
                        >
                            <i class="fa-solid fa-file-signature mr-2"></i> 
                            Generar Recibos
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Tabla de Nóminas -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <el-table 
                        :data="paginatedPayrolls" 
                        @row-click="handleRowClick"
                        style="width: 100%"
                        class="cursor-pointer"
                        :row-class-name="'hover:bg-gray-50 transition-colors'"
                    >
                        <el-table-column label="ID" width="100" align="center">
                            <template #default="scope">
                                <span class="font-mono text-gray-500 font-bold">#{{ scope.row.id }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Catorcena" width="150" align="center">
                            <template #default="scope">
                                <span class="bg-blue-50 text-[#1676A2] py-1 px-3 rounded-full text-xs font-bold border border-blue-100">
                                    No. {{ scope.row.biweekly }}
                                </span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Periodo">
                            <template #default="scope">
                                <div class="flex items-center gap-2 text-gray-700">
                                    <i class="fa-regular fa-calendar text-gray-400"></i>
                                    <span>{{ formatDate(scope.row.start_date) }}</span>
                                    <i class="fa-solid fa-arrow-right text-xs text-gray-300"></i>
                                    <span>{{ getEndPeriod(scope.row.start_date) }}</span>
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column label="Estatus" width="120" align="center">
                            <template #default="scope">
                                <div v-if="scope.row.is_active" class="flex items-center justify-center gap-1 text-green-600 bg-green-50 px-2 py-1 rounded text-xs font-bold">
                                    <span class="relative flex h-2 w-2">
                                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                    Activa
                                </div>
                                <div v-else class="text-gray-400 bg-gray-100 px-2 py-1 rounded text-xs font-medium border border-gray-200">
                                    Cerrada
                                </div>
                            </template>
                        </el-table-column>

                        <!-- NUEVA COLUMNA: Configuración de horas extra -->
                        <el-table-column label="Config. H.E." width="170" align="center">
                            <template #default="scope">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Indicador de costos -->
                                    <el-tooltip :content="scope.row.extra_hour_costs_count > 0 ? 'Costos configurados' : 'Sin costos configurados'" placement="top">
                                        <span 
                                            class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] border"
                                            :class="scope.row.extra_hour_costs_count > 0 
                                                ? 'bg-green-50 text-green-600 border-green-200' 
                                                : 'bg-gray-50 text-gray-300 border-gray-200'"
                                        >
                                            <i class="fa-solid fa-dollar-sign"></i>
                                        </span>
                                    </el-tooltip>
                                    <!-- Indicador de grupos de aprobación -->
                                    <el-tooltip :content="scope.row.approval_groups_count > 0 ? 'Grupos de autorización configurados' : 'Sin grupos de autorización'" placement="top">
                                        <span 
                                            class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] border"
                                            :class="scope.row.approval_groups_count > 0 
                                                ? 'bg-indigo-50 text-indigo-600 border-indigo-200' 
                                                : 'bg-gray-50 text-gray-300 border-gray-200'"
                                        >
                                            <i class="fa-solid fa-users-gear"></i>
                                        </span>
                                    </el-tooltip>
                                    <!-- Badge resumen -->
                                    <span 
                                        v-if="scope.row.extra_hour_costs_count > 0 || scope.row.approval_groups_count > 0"
                                        class="text-[9px] font-bold px-1.5 py-0.5 rounded-full"
                                        :class="scope.row.extra_hour_costs_count > 0 && scope.row.approval_groups_count > 0 
                                            ? 'bg-green-100 text-green-700 border border-green-200' 
                                            : 'bg-amber-100 text-amber-700 border border-amber-200'"
                                    >
                                        {{ scope.row.extra_hour_costs_count > 0 && scope.row.approval_groups_count > 0 ? 'Completo' : 'Parcial' }}
                                    </span>
                                    <span 
                                        v-else
                                        class="text-[9px] text-gray-300 italic"
                                    >
                                        Sin config.
                                    </span>
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column align="right" width="120" fixed="right">
                            <template #default="scope">
                                <div class="flex items-center gap-1 justify-end">
                                    <el-tooltip content="Configurar horas extra" placement="top">
                                        <Link 
                                            :href="route('payrolls.extra-hours-config', scope.row.id)"
                                            @click.stop
                                            class="w-7 h-7 flex items-center justify-center rounded-full text-indigo-500 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                                        >
                                            <i class="fa-solid fa-gear text-xs"></i>
                                        </Link>
                                    </el-tooltip>
                                    <i class="fa-solid fa-chevron-right text-gray-300 ml-1"></i>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- Paginación -->
                    <div class="px-4 py-3 border-t border-gray-100 flex justify-end bg-gray-50">
                        <el-pagination 
                            layout="prev, pager, next" 
                            :total="filteredPayrolls.length" 
                            :page-size="itemsPerPage"
                            @current-change="handlePageChange"
                            background
                        />
                    </div>
                </div>

            </div>

            <!-- Modal: Generar Recibos por Rango -->
            <el-dialog
                v-model="showReceiptsModal"
                title="Generar Recibos por Rango de Fechas"
                width="680px"
                class="!rounded-xl"
            >
                <div class="space-y-5">
                    <!-- Rango de fechas -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            <i class="fa-regular fa-calendar mr-1 text-teal-600"></i>
                            Rango de fechas
                        </label>
                        <el-date-picker
                            v-model="dateRange"
                            type="daterange"
                            range-separator="al"
                            start-placeholder="Fecha inicial"
                            end-placeholder="Fecha final"
                            value-format="YYYY-MM-DD"
                            format="DD MMM YYYY"
                            class="w-full"
                            :clearable="true"
                        />
                        <p class="text-xs text-gray-400 mt-1">Máximo 31 días. Puede combinar dos catorcenas.</p>
                    </div>

                    <!-- Selector de usuarios -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fa-solid fa-users mr-1 text-teal-600"></i>
                                Colaboradores
                            </label>
                            <span class="text-xs font-bold bg-teal-50 text-teal-700 border border-teal-200 px-2 py-0.5 rounded-full">
                                {{ selectedUsers.length }} seleccionados
                            </span>
                        </div>

                        <!-- Búsqueda + atajos -->
                        <div class="flex items-center gap-2 mb-2">
                            <el-input
                                v-model="userSearch"
                                placeholder="Buscar por nombre o código..."
                                clearable
                                size="small"
                                class="flex-1"
                            >
                                <template #prefix><i class="fa-solid fa-magnifying-glass text-gray-400"></i></template>
                            </el-input>
                            <el-button size="small" @click="toggleSelectAllFiltered">
                                <i class="fa-solid" :class="allFilteredSelected ? 'fa-square-check text-teal-600' : 'fa-square'"></i>
                                {{ allFilteredSelected ? 'Quitar todos' : 'Seleccionar todos' }}
                            </el-button>
                        </div>

                        <!-- Listado agrupado por departamento -->
                        <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                            <div v-for="dept in groupedDepartments" :key="dept.name">
                                <!-- Header del departamento -->
                                <div class="flex items-center justify-between px-3 py-2 bg-gray-50 sticky top-0 z-10">
                                    <div class="flex items-center gap-2">
                                        <el-checkbox
                                            :model-value="isDeptSelected(dept.name)"
                                            :indeterminate="isDeptIndeterminate(dept.name)"
                                            @change="toggleDept(dept.name)"
                                        />
                                        <span class="font-semibold text-sm text-gray-700">{{ dept.name }}</span>
                                    </div>
                                    <span class="text-[10px] text-gray-500 font-bold bg-white border border-gray-200 px-1.5 py-0.5 rounded-full">
                                        {{ getDeptVisibleUsers(dept.name).filter(u => selectedUsers.includes(u.id)).length }}/{{ getDeptVisibleUsers(dept.name).length }}
                                    </span>
                                </div>
                                <!-- Usuarios del departamento -->
                                <div class="px-3 py-1.5">
                                    <label
                                        v-for="u in getDeptVisibleUsers(dept.name)"
                                        :key="u.id"
                                        class="flex items-center gap-2.5 py-1.5 px-1 rounded hover:bg-gray-50 cursor-pointer transition-colors"
                                    >
                                        <el-checkbox
                                            :model-value="selectedUsers.includes(u.id)"
                                            @change="
                                                selectedUsers.includes(u.id)
                                                    ? selectedUsers = selectedUsers.filter(id => id !== u.id)
                                                    : selectedUsers = [...selectedUsers, u.id]
                                            "
                                        />
                                        <span class="w-6 text-center text-[10px] font-mono text-gray-400">{{ u.id }}</span>
                                        <span class="text-sm text-gray-800 truncate">{{ u.name }}</span>
                                        <span v-if="u.code" class="text-[10px] text-gray-400 font-mono ml-auto">{{ u.code }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <div class="flex justify-end gap-2">
                        <el-button @click="showReceiptsModal = false">Cancelar</el-button>
                        <el-button
                            type="primary"
                            @click="generateRangeReceipts"
                            class="!bg-teal-600 !border-teal-600 hover:!bg-teal-700"
                        >
                            <i class="fa-solid fa-file-signature mr-2"></i>
                            Generar Recibos
                        </el-button>
                    </div>
                </template>
            </el-dialog>

            <!-- Modal: Gestión de tiempo extra (abre la catorcena en curso) -->
            <ExtraTimeManagementModal
                v-model="showExtraTimeModal"
                :payrollUsers="[]"
                :payrollId="null"
                :approvalGroups="[]"
                :employeeIds="null"
                :payrollStartDate="''"
            />
        </main>
    </AppLayout>
</template>

<style scoped>
/* Ajustes finos para la tabla de Element Plus */
:deep(.el-table__row) {
    cursor: pointer;
}
:deep(.el-table th.el-table__cell) {
    background-color: #f9fafb;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
}

/* Formato del selector de rango: capitalizar mes para mostrar "28 Jul 2026" */
:deep(.el-date-editor--daterange .el-range-input) {
    text-transform: capitalize;
}
</style>
