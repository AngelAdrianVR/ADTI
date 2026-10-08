<script setup>
import { ref } from 'vue';
import { parseISO, startOfDay, endOfDay, isWithinInterval } from 'date-fns';
import { 
    Calendar, 
    ArrowRight,
    VideoPlay,
    CircleCheck,
    OfficeBuilding
} from '@element-plus/icons-vue';

const props = defineProps({
    projects: Array,
    activeEntry: Object,
});

// Emitimos 'start' y 'stop' para que Index.vue maneje la lógica (modal de tareas, etc)
const emit = defineEmits(['view', 'start', 'stop']);

const calendarDate = ref(new Date());
const showSummary = ref(false);
const selectedProject = ref(null);

// --- Helpers ---
const getProgress = (project) => {
    if (!project.budgeted_hours || project.budgeted_hours == 0) return 0;
    const percent = (project.consumed_hours / project.budgeted_hours) * 100;
    return Math.min(100, Math.round(percent));
};

const getProgressColor = (percent) => {
    if (percent >= 100) return '#F56C6C'; 
    if (percent > 80) return '#E6A23C';  
    return '#1676A2';                    
};

const addDaysToDate = (date, days) => {
    var result = new Date(date);
    result.setDate(result.getDate() + days);
    return result;
}

const getProjectsForDate = (dateString) => {
    const date = parseISO(dateString);
    const checkDate = startOfDay(addDaysToDate(date, 0)); 

    return props.projects.filter(p => {
        if (!p.start_date) return false;
        
        const start = startOfDay(parseISO(p.start_date));
        const end = p.estimated_end_date ? endOfDay(parseISO(p.estimated_end_date)) : endOfDay(new Date());
        
        return isWithinInterval(checkDate, { start, end });
    });
};

const handleProjectClick = (project) => {
    selectedProject.value = project;
    showSummary.value = true;
};
</script>

<template>
    <div class="p-4">
        <!-- Calendario -->
        <el-calendar v-model="calendarDate">
            <template #date-cell="{ data }">
                <div class="h-full flex flex-col hover:bg-blue-50 transition-colors rounded p-1" @click.stop>
                    <p class="text-xs font-bold text-gray-500 mb-1" :class="{ 'text-[#1676A2]': data.isSelected }">
                        {{ data.day.split('-').slice(2).join('') }}
                    </p>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar space-y-1">
                        <div 
                            v-for="proj in getProjectsForDate(data.day)" 
                            :key="proj.id"
                            @click="handleProjectClick(proj)"
                            class="text-[10px] px-1.5 py-0.5 rounded text-white truncate cursor-pointer shadow-sm hover:opacity-80 hover:scale-[1.02] transition-transform"
                            :style="{ backgroundColor: getProgressColor(getProgress(proj)) }"
                            :title="`${proj.name}`"
                        >
                            {{ proj.name }}
                        </div>
                    </div>
                </div>
            </template>
        </el-calendar>

        <!-- Modal Resumen Rápido -->
        <el-dialog
            v-model="showSummary"
            :title="selectedProject?.name"
            width="400px"
            destroy-on-close
            align-center
            class="!rounded-xl"
        >
            <template #header>
                <div class="flex items-center gap-2 text-gray-800 font-bold text-lg">
                    <el-icon class="text-[#1676A2]"><OfficeBuilding /></el-icon>
                    <span class="truncate">{{ selectedProject?.name }}</span>
                </div>
            </template>

            <div v-if="selectedProject" class="space-y-5">
                <div class="flex justify-between items-center text-sm text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <span class="text-xs font-bold text-gray-400 uppercase">Cliente</span>
                    <span class="font-medium">{{ selectedProject.client }}</span>
                </div>
                
                <!-- Progreso -->
                <div>
                    <div class="flex justify-between text-xs mb-1 text-gray-500">
                        <span>Horas Consumidas</span>
                        <span class="font-mono font-bold">{{ selectedProject.consumed_hours }} / {{ selectedProject.budgeted_hours }} h</span>
                    </div>
                    <el-progress 
                        :percentage="getProgress(selectedProject)" 
                        :color="getProgressColor" 
                        :stroke-width="8" 
                        :show-text="false" 
                    />
                </div>

                <!-- Botones de Acción (Iniciar / Detener) -->
                <div class="pt-2" v-if="selectedProject.status === 'active'">
                    <!-- Si este es el proyecto activo, mostrar botón ROJO para detener -->
                    <el-button 
                        v-if="activeEntry?.project_id === selectedProject.id"
                        type="danger" 
                        class="!w-full"
                        @click="emit('stop', selectedProject); showSummary = false;"
                    >
                        <el-icon class="mr-2"><CircleCheck /></el-icon> Detener Trabajo
                    </el-button>
                    
                    <!-- Si no, mostrar botón VERDE para iniciar -->
                    <el-button 
                        v-else
                        type="success" 
                        class="!w-full"
                        @click="emit('start', selectedProject); showSummary = false;"
                    >
                        <el-icon class="mr-2"><VideoPlay /></el-icon> Iniciar Trabajo
                    </el-button>
                </div>
                <div v-else class="text-center text-xs text-gray-400 bg-gray-50 py-2 rounded">
                    Proyecto Terminado
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end pt-2">
                    <el-button type="primary" link @click="emit('view', selectedProject)">
                        Ver Detalles Completos <el-icon class="ml-1"><ArrowRight /></el-icon>
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
/* Scrollbar fina */
.custom-scrollbar::-webkit-scrollbar {
    width: 2px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}
/* Calendar Tweaks */
:deep(.el-calendar-table .el-calendar-day) {
    height: 90px;
    padding: 4px;
}
:deep(.el-calendar__header) {
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}
:deep(.el-calendar__title) {
    color: #1676A2;
    font-weight: bold;
}
:deep(.el-calendar-table td.is-selected) {
    background-color: #f0f9ff;
}
:deep(.el-calendar-table td.is-today) {
    color: #1676A2;
}
</style>