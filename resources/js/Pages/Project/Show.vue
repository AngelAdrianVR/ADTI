<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { 
    Back, 
    EditPen, 
    User, 
    Timer, 
    Calendar,
    OfficeBuilding,
    ChatLineSquare,
    Right
} from '@element-plus/icons-vue';

const props = defineProps({
    project: Object,
});

const page = usePage();
const activeTab = ref('tasks');

// --- Computed ---
const canEdit = computed(() => page.props.auth.user?.permissions?.includes('Editar proyectos'));

const progressPercentage = computed(() => {
    if (!props.project.budgeted_hours || props.project.budgeted_hours == 0) return 0;
    const percent = (props.project.consumed_hours / props.project.budgeted_hours) * 100;
    return Math.min(100, Math.round(percent));
});

const progressColor = computed(() => {
    const p = progressPercentage.value;
    if (p >= 100) return '#F56C6C'; // Rojo
    if (p > 80) return '#E6A23C';  // Naranja
    return '#1676A2';             // Azul
});

// Obtener miembros únicos de todo el proyecto
const uniqueMembers = computed(() => {
    const members = [];
    const seen = new Set();
    
    props.project.time_entries?.forEach(entry => {
        if (entry.user && !seen.has(entry.user.id)) {
            seen.add(entry.user.id);
            members.push(entry.user);
        }
    });
    
    return members;
});

// --- Helpers de Tareas (Cálculos en Cliente) ---

// 1. Obtener horas consumidas por una tarea específica
const getTaskConsumedHours = (taskId) => {
    if (!props.project.time_entries) return 0;
    
    // Filtramos los registros de tiempo que pertenecen a esta tarea
    const taskEntries = props.project.time_entries.filter(entry => entry.task_id === taskId);
    
    // Sumamos la duración (en segundos)
    const totalSeconds = taskEntries.reduce((sum, entry) => sum + (entry.total_duration_seconds || 0), 0);
    
    // Convertimos a horas con 1 decimal
    return (totalSeconds / 3600).toFixed(1);
};

// 2. Calcular porcentaje de progreso por tarea
const getTaskProgress = (task) => {
    const consumed = parseFloat(getTaskConsumedHours(task.id));
    const budgeted = parseFloat(task.budgeted_hours || 0);
    
    if (budgeted === 0) return 0;
    const percent = (consumed / budgeted) * 100;
    return Math.min(100, Math.round(percent));
};

// 3. Determinar color de barra por tarea
const getTaskProgressColor = (percent) => {
    if (percent >= 100) return '#F56C6C'; 
    if (percent > 85) return '#E6A23C';
    return '#1676A2';
};

// 4. Obtener usuarios que trabajaron en una tarea específica
const getTaskUsers = (taskId) => {
    if (!props.project.time_entries) return [];
    
    const userIds = new Set();
    const users = [];
    
    props.project.time_entries.forEach(entry => {
        if (entry.task_id === taskId && entry.user) {
            if (!userIds.has(entry.user.id)) {
                userIds.add(entry.user.id);
                users.push(entry.user);
            }
        }
    });
    
    return users;
};

// --- Helpers Generales ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM, yyyy', { locale: es });
};

const formatDuration = (seconds) => {
    if (!seconds) return '0h 0m';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${h}h ${m}m`;
};

const formatTime = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'HH:mm', { locale: es });
};

// --- Actions ---
const editProject = () => {
    router.visit(route('projects.edit', props.project.id));
};
</script>

<template>
    <AppLayout :title="project.name">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <Link :href="route('projects.index')" class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-[#1676A2] hover:border-[#1676A2] transition-colors shadow-sm">
                            <el-icon><Back /></el-icon>
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 leading-tight">{{ project.name }}</h1>
                            <div class="flex items-center gap-2 text-sm text-gray-500">
                                <el-icon><OfficeBuilding /></el-icon>
                                <span>{{ project.client }}</span>
                                <span class="text-gray-300">|</span>
                                <el-tag v-if="project.status === 'active'" type="success" size="small" effect="dark" round>En Curso</el-tag>
                                <el-tag v-else type="info" size="small" effect="dark" round>Terminado</el-tag>
                            </div>
                        </div>
                    </div>

                    <el-button v-if="canEdit" type="primary" color="#1676A2" @click="editProject">
                        <el-icon class="mr-2"><EditPen /></el-icon> Editar
                    </el-button>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Card 1: Fechas -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
                        <div class="w-12 h-12 rounded-lg bg-blue-50 text-[#1676A2] flex items-center justify-center shrink-0">
                            <el-icon class="text-2xl"><Calendar /></el-icon>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Duración</p>
                            <p class="text-sm font-semibold text-gray-700 mt-1">
                                Del: {{ formatDate(project.start_date) }}
                            </p>
                            <p class="text-sm font-semibold text-gray-700">
                                Al: {{ project.estimated_end_date ? formatDate(project.estimated_end_date) : 'Indefinido' }}
                            </p>
                        </div>
                    </div>

                    <!-- Card 2: Presupuesto Total -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
                        <div class="w-12 h-12 rounded-lg bg-orange-50 text-orange-500 flex items-center justify-center shrink-0">
                            <el-icon class="text-2xl"><Timer /></el-icon>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Consumo Total</p>
                            <div class="flex justify-between items-end mb-1">
                                <span class="text-2xl font-bold text-gray-800">{{ project.consumed_hours }}h</span>
                                <span class="text-xs text-gray-500 mb-1">de {{ project.budgeted_hours }}h</span>
                            </div>
                            <el-progress 
                                :percentage="progressPercentage" 
                                :color="progressColor" 
                                :show-text="false" 
                                :stroke-width="6" 
                            />
                        </div>
                    </div>

                    <!-- Card 3: Equipo -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
                        <div class="w-12 h-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                            <el-icon class="text-2xl"><User /></el-icon>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-2">Participantes</p>
                            <div class="flex -space-x-2 overflow-hidden">
                                <template v-if="uniqueMembers.length">
                                    <el-avatar 
                                        v-for="user in uniqueMembers.slice(0, 5)" 
                                        :key="user.id" 
                                        :src="user.profile_photo_url" 
                                        :size="32" 
                                        class="ring-2 ring-white"
                                        :title="user.name"
                                    />
                                    <div v-if="uniqueMembers.length > 5" class="w-8 h-8 rounded-full bg-gray-100 ring-2 ring-white flex items-center justify-center text-xs text-gray-500 font-bold">
                                        +{{ uniqueMembers.length - 5 }}
                                    </div>
                                </template>
                                <span v-else class="text-sm text-gray-400 italic">Sin actividad</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content (Tabs) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden min-h-[500px]">
                    <el-tabs v-model="activeTab" class="px-6 pt-4 project-tabs">
                        
                        <!-- TAB 1: Descripción y Tareas -->
                        <el-tab-pane name="tasks" label="Detalle de Tareas">
                            <div class="py-4">
                                <div v-if="project.description" class="mb-8 bg-gray-50 p-4 rounded-lg border border-gray-100">
                                    <h3 class="text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                                        <el-icon><ChatLineSquare /></el-icon> Descripción del Proyecto
                                    </h3>
                                    <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ project.description }}</p>
                                </div>

                                <h3 class="text-lg font-bold text-gray-800 mb-4 px-2">Desglose de Tareas y Tiempos</h3>
                                
                                <el-table :data="project.tasks" style="width: 100%" stripe>
                                    
                                    <el-table-column prop="description" label="Actividad / Tarea" min-width="220" />
                                    
                                    <el-table-column prop="department.name" label="Departamento" width="160">
                                        <template #default="scope">
                                            <el-tag size="small" type="info" effect="plain">{{ scope.row.department?.name }}</el-tag>
                                        </template>
                                    </el-table-column>

                                    <!-- NUEVO: Columna de Equipo por Tarea -->
                                    <el-table-column label="Equipo" width="140">
                                        <template #default="scope">
                                            <div class="flex -space-x-2 overflow-hidden py-1 pl-2">
                                                <template v-if="getTaskUsers(scope.row.id).length > 0">
                                                    <el-avatar 
                                                        v-for="user in getTaskUsers(scope.row.id).slice(0, 3)" 
                                                        :key="user.id" 
                                                        :src="user.profile_photo_url" 
                                                        :size="24" 
                                                        class="ring-2 ring-white"
                                                        :title="user.name"
                                                    />
                                                    <div v-if="getTaskUsers(scope.row.id).length > 3" class="w-6 h-6 rounded-full bg-gray-100 ring-2 ring-white flex items-center justify-center text-[9px] font-bold text-gray-500">
                                                        +{{ getTaskUsers(scope.row.id).length - 3 }}
                                                    </div>
                                                </template>
                                                <span v-else class="text-xs text-gray-300 italic">-</span>
                                            </div>
                                        </template>
                                    </el-table-column>

                                    <!-- NUEVO: Columna de Horas Consumidas -->
                                    <el-table-column label="Progreso (Hrs)" width="220" align="right">
                                        <template #default="scope">
                                            <div class="flex flex-col items-end gap-1">
                                                <div class="flex items-center gap-2 w-full justify-end">
                                                    <el-progress 
                                                        :percentage="getTaskProgress(scope.row)" 
                                                        :color="getTaskProgressColor(getTaskProgress(scope.row))"
                                                        :show-text="false" 
                                                        :stroke-width="6"
                                                        class="w-24" 
                                                    />
                                                    <span class="text-xs font-bold" :style="{ color: getTaskProgressColor(getTaskProgress(scope.row)) }">
                                                        {{ getTaskProgress(scope.row) }}%
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500 font-mono">
                                                    <span :class="{'text-red-500 font-bold': parseFloat(getTaskConsumedHours(scope.row.id)) > parseFloat(scope.row.budgeted_hours)}">
                                                        {{ getTaskConsumedHours(scope.row.id) }}
                                                    </span> 
                                                    / {{ scope.row.budgeted_hours }} h
                                                </div>
                                            </div>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>
                        </el-tab-pane>

                        <!-- TAB 2: Registro de Tiempos -->
                        <el-tab-pane name="times" label="Historial Detallado">
                            <div class="py-4">
                                <el-table :data="project.time_entries" style="width: 100%" empty-text="No hay registros de tiempo aún">
                                    <el-table-column label="Colaborador" min-width="200">
                                        <template #default="scope">
                                            <div class="flex items-center gap-3">
                                                <el-avatar :size="30" :src="scope.row.user?.profile_photo_url" />
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-medium leading-none">{{ scope.row.user?.name }}</span>
                                                    <!-- Mostrar en qué tarea trabajó -->
                                                    <span v-if="scope.row.task" class="text-xs text-gray-400 mt-1">
                                                        {{ scope.row.task.description }}
                                                    </span>
                                                    <span v-else class="text-xs text-gray-400 mt-1 italic">General del proyecto</span>
                                                </div>
                                            </div>
                                        </template>
                                    </el-table-column>
                                    
                                    <el-table-column label="Fecha" width="150">
                                        <template #default="scope">
                                            {{ formatDate(scope.row.start_time) }}
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Horario" width="180">
                                        <template #default="scope">
                                            <div class="flex items-center gap-2 text-xs text-gray-600 font-mono">
                                                <span class="bg-gray-100 px-1.5 rounded">{{ formatTime(scope.row.start_time) }}</span>
                                                <el-icon><Right /></el-icon>
                                                <span v-if="scope.row.end_time" class="bg-gray-100 px-1.5 rounded">{{ formatTime(scope.row.end_time) }}</span>
                                                <span v-else class="text-green-600 font-bold animate-pulse">En curso</span>
                                            </div>
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Duración" width="120" align="right">
                                        <template #default="scope">
                                            <span class="font-bold text-gray-700">
                                                {{ formatDuration(scope.row.total_duration_seconds) }}
                                            </span>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>
                        </el-tab-pane>

                    </el-tabs>
                </div>

            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
:deep(.el-tabs__nav-wrap::after) {
    background-color: #f3f4f6;
    height: 1px;
}
:deep(.el-tabs__item) {
    font-size: 0.95rem;
    color: #6b7280;
}
:deep(.el-tabs__item.is-active) {
    color: #1676A2;
    font-weight: 600;
}
:deep(.el-tabs__active-bar) {
    background-color: #1676A2;
}
</style>