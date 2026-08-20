<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ElNotification } from 'element-plus';
import { Refresh, TrendCharts, Medal, Coin, Clock, InfoFilled } from '@element-plus/icons-vue';
import axios from 'axios';

const loading = ref(false);
const metrics = ref({
    projects_ranking: [],
    employees_ranking: [],
    employees_ranking_by_cost: [],
    total_extra_hours: 0,
    total_cost: 0,
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