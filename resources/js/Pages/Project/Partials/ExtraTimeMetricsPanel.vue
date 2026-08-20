<script setup>
import { ref, onMounted } from 'vue';
import { ElNotification } from 'element-plus';
import { Refresh, Clock, Coin, Calendar, ArrowRight } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
});

const loading = ref(false);
const metrics = ref({
    total_extra_hours: 0,
    total_cost: 0,
    employees: [],
    daily: {},
});
const dateRange = ref(null);
const selectedEmployee = ref(null); // { user, rows }
const showDetail = ref(false);

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

const formatCurrency = (value) => currency.format(value || 0);

const formatHours = (value) => {
    return `${Number(value || 0).toFixed(2)}h`;
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const [y, m, d] = dateString.split('-');
    return `${d}/${m}/${y}`;
};

const loadMetrics = async () => {
    loading.value = true;
    try {
        const params = {};
        if (dateRange.value && dateRange.value.length === 2) {
            params.start_date = dateRange.value[0];
            params.end_date = dateRange.value[1];
        }

        const response = await axios.get(route('projects.extra-time-metrics', props.projectId), { params });
        metrics.value = response.data;
    } catch (error) {
        console.error(error);
        ElNotification.error('No se pudieron cargar las métricas de tiempo extra.');
    } finally {
        loading.value = false;
    }
};

const openEmployeeDetail = (employee) => {
    // daily viene agrupado por user.id: { [id]: [...] }
    const rows = metrics.value.daily?.[employee.user.id] ?? [];
    selectedEmployee.value = {
        ...employee,
        rows: rows.sort((a, b) => a.date.localeCompare(b.date)),
    };
    showDetail.value = true;
};

const clearFilters = () => {
    dateRange.value = null;
    loadMetrics();
};

onMounted(loadMetrics);
</script>

<template>
    <div class="py-4 space-y-6">
        <!-- Filtro de rango de fechas -->
        <div class="flex flex-wrap items-center gap-3 bg-gray-50 p-3 rounded-lg border border-gray-100">
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <el-icon><Calendar /></el-icon>
                <span class="font-semibold">Rango de fechas:</span>
            </div>
            <el-date-picker
                v-model="dateRange"
                type="daterange"
                range-separator="→"
                start-placeholder="Fecha inicial"
                end-placeholder="Fecha final"
                format="DD/MM/YYYY"
                value-format="YYYY-MM-DD"
                size="small"
                class="!w-72"
            />
            <el-button type="primary" color="#1676A2" size="small" :loading="loading" @click="loadMetrics">
                <el-icon class="mr-1"><Refresh /></el-icon> Aplicar
            </el-button>
            <el-button v-if="dateRange" size="small" @click="clearFilters">Limpiar</el-button>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-lg bg-blue-50 text-[#1676A2] flex items-center justify-center shrink-0">
                    <el-icon class="text-2xl"><Clock /></el-icon>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Horas extras totales aprobadas</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ formatHours(metrics.total_extra_hours) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ metrics.employees.length }} empleado(s) involucrado(s)</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                    <el-icon class="text-2xl"><Coin /></el-icon>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Costo total invertido</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ formatCurrency(metrics.total_cost) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Por tiempo extra aprobado</p>
                </div>
            </div>
        </div>

        <!-- Desglose por empleado -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 pt-4">
                <h3 class="text-base font-bold text-gray-800">Desglose por empleado</h3>
                <p class="text-xs text-gray-500 mt-0.5">Ordenado por mayor costo / tiempo invertido. Haz clic en una fila para ver el detalle diario.</p>
            </div>

            <el-table
                v-loading="loading"
                :data="metrics.employees"
                style="width: 100%"
                empty-text="Sin tiempo extra aprobado en el rango seleccionado"
                class="mt-3"
                @row-click="openEmployeeDetail"
            >
                <el-table-column label="Empleado" min-width="220">
                    <template #default="scope">
                        <div class="flex items-center gap-3">
                            <el-avatar :size="34" :src="scope.row.user?.profile_photo_url" />
                            <span class="text-sm font-medium text-gray-700">{{ scope.row.user?.name }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Días con tiempo extra" width="160" align="center">
                    <template #default="scope">
                        <el-tag size="small" type="info" effect="plain">{{ scope.row.days }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Horas totales" width="130" align="right">
                    <template #default="scope">
                        <span class="font-bold text-gray-700">{{ formatHours(scope.row.total_extra_hours) }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Costo total" width="150" align="right">
                    <template #default="scope">
                        <span class="font-bold text-green-600">{{ formatCurrency(scope.row.total_cost) }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="" width="60" align="right">
                    <template #default>
                        <el-icon class="text-gray-400"><ArrowRight /></el-icon>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <!-- Modal detalle por empleado -->
        <el-dialog
            v-model="showDetail"
            :title="`Detalle diario — ${selectedEmployee?.user?.name ?? ''}`"
            width="640px"
        >
            <div v-if="selectedEmployee">
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 uppercase font-bold">Horas totales</p>
                        <p class="text-lg font-bold text-gray-800">{{ formatHours(selectedEmployee.total_extra_hours) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 uppercase font-bold">Costo total</p>
                        <p class="text-lg font-bold text-green-600">{{ formatCurrency(selectedEmployee.total_cost) }}</p>
                    </div>
                </div>

                <el-table :data="selectedEmployee.rows" style="width: 100%" empty-text="Sin registros">
                    <el-table-column label="Fecha" width="120">
                        <template #default="scope">
                            {{ formatDate(scope.row.date) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="Costo / hora" align="right" width="140">
                        <template #default="scope">
                            {{ formatCurrency(scope.row.cost_per_hour) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="Tiempo" align="right" width="120">
                        <template #default="scope">
                            {{ formatHours(scope.row.hours) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="Monto pagado" align="right" width="140">
                        <template #default="scope">
                            <span class="font-bold text-green-600">{{ formatCurrency(scope.row.amount) }}</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-dialog>
    </div>
</template>