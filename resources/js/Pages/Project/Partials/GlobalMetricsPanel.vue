<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ElNotification } from 'element-plus';
import { Refresh, TrendCharts, Medal, Coin, Clock, InfoFilled } from '@element-plus/icons-vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';

// Registro local del componente ApexCharts para usarlo como <apexchart> en el template.
const apexchart = VueApexCharts;

const loading = ref(false);
const metrics = ref({
    projects_ranking: [],
    employees_ranking: [],
    employees_ranking_by_cost: [],
    total_extra_hours: 0,
    total_cost: 0,
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
const employeeOrder = ref('hours'); // 'hours' | 'cost'

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

const formatCurrency = (value) => currency.format(value || 0);
const formatHours = (value) => `${Number(value || 0).toFixed(2)}h`;

// Empleados según el orden seleccionado: mayor a menor monto pagado o mayor a menor horas
const orderedEmployees = computed(() => {
    const list = employeeOrder.value === 'cost'
        ? metrics.value.employees_ranking_by_cost
        : metrics.value.employees_ranking;
    return list || [];
});

// ─── Gráfica mensual de tiempo extra (ApexCharts) ───
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
        toolbar: { show: true, tools: { download: true }, export: { csv: { filename: 'tiempo-extra-mensual' } } },
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

        const response = await axios.get(route('projects.extra-time.global-metrics'), { params });
        metrics.value = response.data;
    } catch (error) {
        console.error(error);
        ElNotification.error('No se pudieron cargar las métricas globales.');
    } finally {
        loading.value = false;
    }
};

const clearFilters = () => {
    dateRange.value = null;
    loadMetrics();
};

onMounted(loadMetrics);
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 pt-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-gray-800">Métricas globales de tiempo extra</h2>
                <p class="text-xs text-gray-500 mt-0.5">Ranking de proyectos y empleados con mayor impacto por horas extra</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
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
        </div>

        <!-- KPIs globales -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 px-5 pt-4">
            <div class="bg-gray-50 rounded-xl border border-gray-100 p-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-lg bg-blue-50 text-[#1676A2] flex items-center justify-center shrink-0">
                    <el-icon class="text-2xl"><Clock /></el-icon>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Tiempo extra total aprobado</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ formatHours(metrics.total_extra_hours) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Incluye tiempo con y sin proyecto vinculado</p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-xl border border-gray-100 p-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                    <el-icon class="text-2xl"><Coin /></el-icon>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Monto total pagado por tiempo extra</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ formatCurrency(metrics.total_cost) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Incluye tiempo con y sin proyecto vinculado</p>
                </div>
            </div>
        </div>

        <!-- Nota informativa -->
        <div class="px-5 pt-3">
            <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-2.5 flex items-start gap-2 text-xs text-gray-700">
                <el-icon class="text-[#1676A2] mt-0.5 shrink-0"><InfoFilled /></el-icon>
                <p>
                    Los indicadores de <b>tiempo extra total aprobado</b> y <b>monto total pagado</b> consideran tanto el
                    tiempo extra <b>con proyecto vinculado</b> como el <b>sin proyecto vinculado</b> en el rango seleccionado.
                </p>
            </div>
        </div>

        <!-- Carga de tiempo extra mensual (gráfica de línea) -->
        <div class="px-5 pt-5">
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="mb-3">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-800">Carga de tiempo extra durante el año</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Horas extra mensuales por tipo de trabajo (solo días vinculados a proyectos).</p>
                </div>
                <div v-if="monthlySeries.length > 0">
                    <apexchart type="line" height="320" :options="chartOptions" :series="chartSeries" />
                </div>
                <div v-else class="flex flex-col items-center justify-center py-10 text-gray-400">
                    <i class="fa-solid fa-chart-line text-2xl mb-2 opacity-60"></i>
                    <p class="text-sm">Sin datos de tiempo extra vinculado a proyectos en el rango seleccionado.</p>
                </div>
            </div>
        </div>

        <!-- Desglose interno vs externo -->
        <div class="px-5 pt-5">
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <h3 class="text-sm font-bold uppercase tracking-wider text-gray-800 mb-1">Desglose interno vs externo</h3>
                <p class="text-xs text-gray-500 mb-4">Distribución del tiempo extra de días vinculados a proyectos según tipo de trabajo.</p>

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
                <div class="mt-4 h-2.5 w-full rounded-full overflow-hidden flex bg-gray-100">
                    <div class="h-full bg-teal-500" :style="{ width: workTypeBars.internal + '%' }"></div>
                    <div class="h-full bg-orange-500" :style="{ width: workTypeBars.external + '%' }"></div>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Total vinculado a proyectos: {{ formatHours(workType.total_hours) }} · {{ formatCurrency(workType.total_cost) }}
                </p>
            </div>
        </div>


        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-5">
            <!-- Ranking de proyectos -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-gray-700">
                    <el-icon class="text-[#1676A2]"><TrendCharts /></el-icon>
                    <h3 class="text-sm font-bold uppercase tracking-wider">Proyectos con mayor impacto financiero</h3>
                </div>
                <p class="text-xs text-gray-500 -mt-1">
                    Para ver más detalles de tiempo extra en un proyecto, da clic en él.
                </p>

                <el-table
                    v-loading="loading"
                    :data="metrics.projects_ranking"
                    style="width: 100%"
                    height="420"
                    empty-text="Sin datos de tiempo extra vinculado a proyectos en el rango seleccionado"
                    size="small"
                >
                    <el-table-column type="index" label="#" width="40" align="center" />
                    <el-table-column label="Proyecto" min-width="180">
                        <template #default="scope">
                            <div class="flex flex-col">
                                <Link
                                    :href="route('projects.show', scope.row.project.id)"
                                    class="text-sm font-semibold text-[#1676A2] hover:underline hover:text-[#0f5c80] transition-colors"
                                >
                                    {{ scope.row.project?.name }}
                                </Link>
                                <span class="text-xs text-gray-400">{{ scope.row.project?.client }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="Horas extra" width="100" align="right">
                        <template #default="scope">
                            <span class="font-bold text-gray-700">{{ formatHours(scope.row.total_extra_hours) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="Costo" width="120" align="right">
                        <template #default="scope">
                            <span class="font-bold text-green-600">{{ formatCurrency(scope.row.total_cost) }}</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <!-- Ranking de empleados -->
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-gray-700">
                        <el-icon class="text-orange-500"><Medal /></el-icon>
                        <h3 class="text-sm font-bold uppercase tracking-wider">Empleados con más horas extra</h3>
                    </div>
                    <el-segmented
                        v-model="employeeOrder"
                        :options="[
                            { label: 'Por horas', value: 'hours' },
                            { label: 'Por monto', value: 'cost' },
                        ]"
                        size="small"
                    />
                </div>

                <!-- Tabla con alto fijo: scroll interno en lugar de scroll de página -->
                <el-table
                    v-loading="loading"
                    :data="orderedEmployees"
                    style="width: 100%"
                    height="420"
                    empty-text="Sin datos en el rango seleccionado"
                    size="small"
                >
                    <el-table-column type="index" label="#" width="40" align="center" />
                    <el-table-column label="Empleado" min-width="180">
                        <template #default="scope">
                            <div class="flex items-center gap-2">
                                <el-avatar :size="26" :src="scope.row.user?.profile_photo_url" />
                                <span class="text-sm font-medium text-gray-700">{{ scope.row.user?.name }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="Horas extra" width="110" align="right">
                        <template #default="scope">
                            <span class="font-bold text-gray-700">{{ formatHours(scope.row.total_extra_hours) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="Monto pagado" width="130" align="right">
                        <template #default="scope">
                            <span class="font-bold text-green-600">{{ formatCurrency(scope.row.total_cost) }}</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>
    </div>
</template>