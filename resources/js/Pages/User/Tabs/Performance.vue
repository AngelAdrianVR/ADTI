<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { 
    Timer, 
    Calendar,
    Briefcase,
    List
} from '@element-plus/icons-vue';

const props = defineProps({
    user: Object
});

// State
const loading = ref(false);
const timeEntries = ref([]);
const currentRange = ref('today'); // 'today', 'week', 'month', 'custom'
const customDateRange = ref([]); // [Date, Date]

// --- Computed Stats ---
const totalTime = computed(() => {
    const totalSeconds = timeEntries.value.reduce((sum, entry) => sum + (entry.total_duration_seconds || 0), 0);
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    return `${h}h ${m}m`;
});

const projectsCount = computed(() => {
    const ids = new Set(timeEntries.value.map(e => e.project_id));
    return ids.size;
});

const tasksCount = computed(() => {
    const ids = new Set(timeEntries.value.map(e => e.task_id).filter(id => id));
    return ids.size;
});

// --- Methods ---
const fetchData = async () => {
    // Si es personalizado y no hay fechas, no hacemos nada
    if (currentRange.value === 'custom' && (!customDateRange.value || customDateRange.value.length !== 2)) {
        return;
    }

    loading.value = true;
    try {
        const params = { range: currentRange.value };
        
        if (currentRange.value === 'custom') {
            params.start_date = customDateRange.value[0];
            params.end_date = customDateRange.value[1];
        }

        const response = await axios.get(route('users.get-performance', props.user.id), {
            params: params
        });
        
        if (response.status === 200) {
            timeEntries.value = response.data.items;
        }
    } catch (error) {
        console.error("Error fetching performance:", error);
    } finally {
        loading.value = false;
    }
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM, HH:mm', { locale: es });
};

const formatDuration = (seconds) => {
    if (!seconds) return '0m';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
};

// Fetch initial data
onMounted(() => {
    fetchData();
});

// Watchers
watch(currentRange, (newVal) => {
    if (newVal !== 'custom') {
        fetchData();
    }
});

// Watch para cuando cambien las fechas custom
watch(customDateRange, (newVal) => {
    if (currentRange.value === 'custom' && newVal && newVal.length === 2) {
        fetchData();
    }
});
</script>

<template>
    <div class="px-2 pb-6">
        
        <!-- Header & Filtros -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                <el-icon class="mr-2 text-[#1676A2]"><Timer /></el-icon>
                Registro de Actividades
            </h3>
            
            <div class="flex flex-col sm:flex-row items-center gap-2 w-full md:w-auto">
                <el-radio-group v-model="currentRange" size="small">
                    <el-radio-button label="today">Hoy</el-radio-button>
                    <el-radio-button label="week">Semana</el-radio-button>
                    <el-radio-button label="month">Mes</el-radio-button>
                    <el-radio-button label="custom">Personalizado</el-radio-button>
                </el-radio-group>

                <!-- Date Picker condicional -->
                <div v-if="currentRange === 'custom'" class="w-full sm:w-64">
                    <el-date-picker
                        v-model="customDateRange"
                        type="daterange"
                        range-separator="a"
                        start-placeholder="Inicio"
                        end-placeholder="Fin"
                        size="small"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                        class="!w-full"
                    />
                </div>
            </div>
        </div>

        <!-- Cards Resumen -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-[#1676A2]">
                    <el-icon><Timer /></el-icon>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Tiempo Total</p>
                    <p class="text-xl font-bold text-gray-800">{{ totalTime }}</p>
                </div>
            </div>
            
            <div class="bg-green-50 border border-green-100 p-4 rounded-xl flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-green-600">
                    <el-icon><Briefcase /></el-icon>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Proyectos</p>
                    <p class="text-xl font-bold text-gray-800">{{ projectsCount }}</p>
                </div>
            </div>

            <div class="bg-orange-50 border border-orange-100 p-4 rounded-xl flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-orange-500">
                    <el-icon><List /></el-icon>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Tareas</p>
                    <p class="text-xl font-bold text-gray-800">{{ tasksCount }}</p>
                </div>
            </div>
        </div>

        <!-- Tabla de Registros -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" v-loading="loading">
            <el-table :data="timeEntries" style="width: 100%" stripe empty-text="Sin actividad registrada en este periodo">
                <el-table-column label="Fecha" width="140">
                    <template #default="scope">
                        <span class="text-gray-600 text-xs">{{ formatDateTime(scope.row.start_time) }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Proyecto" min-width="180">
                    <template #default="scope">
                        <span class="font-bold text-gray-700">{{ scope.row.project?.name || 'Desconocido' }}</span>
                        <p class="text-xs text-gray-400">{{ scope.row.project?.client }}</p>
                    </template>
                </el-table-column>

                <el-table-column label="Tarea / Actividad" min-width="200">
                    <template #default="scope">
                        <div v-if="scope.row.task">
                            <p class="text-sm text-gray-800">{{ scope.row.task.description }}</p>
                            <el-tag size="small" type="info" class="mt-1">{{ scope.row.task.department?.name }}</el-tag>
                        </div>
                        <span v-else class="text-xs text-gray-400 italic">Tiempo general del proyecto</span>
                    </template>
                </el-table-column>

                <el-table-column label="Duración" width="120" align="right">
                    <template #default="scope">
                        <span class="font-mono font-bold text-[#1676A2]">{{ formatDuration(scope.row.total_duration_seconds) }}</span>
                    </template>
                </el-table-column>
            </el-table>
        </div>

    </div>
</template>