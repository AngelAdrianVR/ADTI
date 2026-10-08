<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElNotification } from 'element-plus';
import { Refresh, Clock, Coin, Calendar, ArrowRight, TrendCharts } from '@element-plus/icons-vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';

// Registro local del componente ApexCharts para usarlo como <apexchart> en el template.
const apexchart = VueApexCharts;

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
    monthly_series: [],
    work_type_breakdown: {
        internal_hours: 0,
        internal_cost: 0,
        external_hours: 0,
        external_cost: 0,
        total_hours: 0,
        total_cost: 0,
    },
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

// ─── Gráfica mensual de tiempo extra (interno vs externo) ───
const monthlySeries = computed(() => metrics.value.monthly_series || []);

const chartLabels = computed(() => monthlySeries.value.map((m) => {
    if (!m.month) return '';
    const [y, mo] = m.month.split('-').map(Number);
    const name = new Date(y, mo - 1, 1).toLocaleString('es-MX', { month: 'short' }).replace('.', '');
    const cap = `${name.charAt(0).toUpperCase()}${name.slice(1)}`;
    return `${cap} '${String(y).slice(2)}`;
}));

const chartSeries = computed(() => {
    const months = monthlySeries.value;
    return [
        { name: 'Trabajo interno', data: months.map(m => m.internal_hours) },
        { name: 'Trabajo externo', data: months.map(m => m.external_hours) },
        { name: 'Total', data: months.map(m => Math.round(((m.internal_hours || 0) + (m.external_hours || 0)) * 100) / 100) },
    ];
});

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 320,
        toolbar: { show: true, tools: { download: true }, export: { csv: { filename: 'tiempo-extra-mensual-proyecto' } } },
        zoom: { enabled: false },
        fontFamily: 'inherit',
        foreColor: '#64748b',
        animations: { enabled: true, easing: 'easeinout', speed: 500 },
    },
    colors: ['#1676A2', '#F97316', '#9CA3AF'],
    stroke: { curve: 'smooth', width: [3, 3, 2], dashArray: [0, 0, 6] },
    fill: { opacity: 1, type: 'solid' },
    dataLabels: { enabled: false },
    markers: { size: 3.5, strokeWidth: 0, hover: { size: 5 } },
    legend: { position: 'bottom', horizontalAlign: 'center', fontSize: '12px' },
    grid: { borderColor: '#e5e7eb', strokeDashArray: 4, padding: { left: 10, right: 10 } },
    xaxis: {
        categories: chartLabels.value,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { fontSize: '11px', fontWeight: 500 } },
        tooltip: { enabled: false },
    },
    yaxis: {
        labels: { formatter: (value) => `${Number(value).toFixed(1)}`, style: { fontSize: '11px' } },
        title: { text: 'Horas extra', style: { fontSize: '10px', fontWeight: 600, color: '#94a3b8' } },
    },
    tooltip: {
        shared: true,
        intersect: false,
        y: {
            formatter: (value) => (value === undefined || value === null ? '' : `${Number(value).toFixed(2)} h`),
        },
    },
    noData: { text: 'Sin datos para mostrar', align: 'center', verticalAlign: 'middle' },
}));

// ─── Desglose interno / externo ───
const workType = computed(() => metrics.value.work_type_breakdown || {});

const workTypeBars = computed(() => {
    const total = Number(workType.value.total_hours) || 0;
    if (total <= 0) return { internal: 0, external: 0 };
    const internal = ((Number(workType.value.internal_hours) || 0) / total) * 100;
    return {
        internal,
        external: Math.max(0, 100 - internal),
    };
});

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

        <!-- Carga mensual de tiempo extra (gráfica interno vs externo) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 pt-4">
                <h3 class="text-base font-bold text-gray-800">Carga de tiempo extra en el proyecto</h3>
                <p class="text-xs text-gray-500 mt-0.5">Horas extra mensuales por tipo de trabajo en este proyecto (días con tiempo extra aprobado).</p>
            </div>
            <div v-if="monthlySeries.length > 0" class="px-2 sm:px-5 pt-1 pb-2">
                <apexchart type="line" height="320" :options="chartOptions" :series="chartSeries" />
            </div>
            <div v-else class="flex flex-col items-center justify-center py-12 text-gray-400">
                <el-icon class="text-3xl mb-2 opacity-60"><TrendCharts /></el-icon>
                <p class="text-sm">Sin datos de tiempo extra aprobado para este proyecto en el rango seleccionado.</p>
            </div>
        </div>

        <!-- Desglose interno vs externo -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 pt-4">
                <h3 class="text-base font-bold text-gray-800">Desglose interno vs externo</h3>
                <p class="text-xs text-gray-500 mt-0.5">Distribución del tiempo extra aprobado de este proyecto según el tipo de trabajo del día vinculado.</p>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-teal-700">
                                <i class="fa-solid fa-building"></i>
                                <span class="font-bold text-sm">Trabajo interno</span>
                            </div>
                            <span class="text-[10px] font-bold uppercase text-teal-600 bg-white border border-teal-200 rounded-full px-2 py-0.5">{{ workTypeBars.internal.toFixed(1) }}%</span>
                        </div>
                        <p class="text-2xl font-bold text-teal-700 mt-2">{{ formatHours(workType.internal_hours) }}</p>
                        <p class="text-xs text-teal-600 mt-0.5">Costo: {{ formatCurrency(workType.internal_cost) }}</p>
                    </div>

                    <div class="rounded-xl border border-orange-200 bg-orange-50/60 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-orange-700">
                                <i class="fa-solid fa-earth-americas"></i>
                                <span class="font-bold text-sm">Trabajo externo</span>
                            </div>
                            <span class="text-[10px] font-bold uppercase text-orange-600 bg-white border border-orange-200 rounded-full px-2 py-0.5">{{ workTypeBars.external.toFixed(1) }}%</span>
                        </div>
                        <p class="text-2xl font-bold text-orange-600 mt-2">{{ formatHours(workType.external_hours) }}</p>
                        <p class="text-xs text-orange-600 mt-0.5">Costo: {{ formatCurrency(workType.external_cost) }}</p>
                    </div>
                </div>

                <!-- Barra proporcional -->
                <div class="h-2.5 w-full rounded-full overflow-hidden flex bg-gray-100">
                    <div class="h-full bg-teal-500" :style="{ width: workTypeBars.internal + '%' }"></div>
                    <div class="h-full bg-orange-500" :style="{ width: workTypeBars.external + '%' }"></div>
                </div>
                <p class="text-xs text-gray-400">
                    Total en este proyecto: {{ formatHours(workType.total_hours) }} · {{ formatCurrency(workType.total_cost) }}
                </p>
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