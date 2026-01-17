<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Calendar, Clock, ArrowRight } from '@element-plus/icons-vue';

const props = defineProps({
    projects: Array,
});

// --- Estado local para modal de resumen ---
const showSummary = ref(false);
const selectedProject = ref(null);

// --- Helpers ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
};

const calculateProgress = (project) => {
    if (!project.budgeted_hours || project.budgeted_hours == 0) return 0;
    const percent = (project.consumed_hours / project.budgeted_hours) * 100;
    return Math.min(100, Math.round(percent));
};

const customColors = [
    { color: '#67c23a', percentage: 60 },
    { color: '#e6a23c', percentage: 80 },
    { color: '#f56c6c', percentage: 100 },
];

const getProjectsForDate = (date) => {
    const checkDate = new Date(date);
    checkDate.setHours(0, 0, 0, 0);

    return props.projects.filter(project => {
        const start = new Date(project.start_date);
        start.setHours(0, 0, 0, 0);
        
        if (project.estimated_end_date) {
            const end = new Date(project.estimated_end_date);
            end.setHours(0, 0, 0, 0);
            return checkDate >= start && checkDate <= end;
        } else {
            return checkDate.getTime() === start.getTime();
        }
    });
};

// --- Acciones ---
const openSummary = (project) => {
    selectedProject.value = project;
    showSummary.value = true;
};
</script>

<template>
    <div>
        <el-calendar>
            <template #date-cell="{ data }">
                <div class="h-full w-full overflow-hidden flex flex-col">
                    <p class="text-xs font-bold mb-1" :class="data.isSelected ? 'text-cyan-500' : 'text-green-700'">
                        {{ data.day.split('-').slice(2).join('-') }}
                    </p>
                    <!-- Lista de Proyectos -->
                    <div class="space-y-1 overflow-y-auto flex-1 custom-scrollbar">
                        <div 
                            v-for="proj in getProjectsForDate(data.date)" 
                            :key="proj.id"
                            class="text-[10px] px-1 rounded truncate cursor-pointer transition-all border-l-2 shadow-sm"
                            :class="proj.status === 'active' ? 'bg-blue-50 text-cyan-700 border-cyan-400 hover:bg-cyan-100' : 'bg-green-100 text-green-600 border-green-400 hover:bg-green-200'"
                            @click.stop="openSummary(proj)"
                            :title="proj.name"
                        >
                            {{ proj.name }}
                        </div>
                    </div>
                </div>
            </template>
        </el-calendar>

        <!-- Modal de Vista Rápida -->
        <el-dialog
            v-model="showSummary"
            title="Resumen del Proyecto"
            width="400px"
            destroy-on-close
            align-center
        >
            <div v-if="selectedProject" class="space-y-4">
                <!-- Header Proyecto -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 leading-tight">{{ selectedProject.name }}</h3>
                    <p class="text-sm text-gray-500">{{ selectedProject.client }}</p>
                    <el-tag size="small" :type="selectedProject.status === 'active' ? 'success' : 'info'" class="mt-2">
                        {{ selectedProject.status === 'active' ? 'Activo' : 'Terminado' }}
                    </el-tag>
                </div>

                <!-- Fechas -->
                <div class="grid grid-cols-2 gap-2 bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold">Inicio</p>
                        <p class="text-xs font-semibold">{{ formatDate(selectedProject.start_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold">Fin Estimado</p>
                        <p class="text-xs font-semibold">{{ formatDate(selectedProject.estimated_end_date) }}</p>
                    </div>
                </div>

                <!-- Progreso de Horas -->
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-600 font-medium">Horas Consumidas</span>
                        <span class="text-gray-800 font-bold">{{ selectedProject.consumed_hours }} / {{ selectedProject.budgeted_hours }} h</span>
                    </div>
                    <el-progress 
                        :percentage="calculateProgress(selectedProject)" 
                        :color="customColors"
                        :stroke-width="12"
                        class="mt-1"
                    />
                </div>

                <!-- Descripción corta -->
                <div v-if="selectedProject.description" class="text-sm text-gray-600 bg-yellow-50 p-3 rounded border border-yellow-100 italic">
                    "{{ selectedProject.description }}"
                </div>

                <!-- Footer / Acción -->
                <div class="pt-2 text-right">
                    <Link :href="route('projects.show', selectedProject.id)">
                        <el-button type="primary" plain class="w-full">
                            Ver Detalles Completos <el-icon class="ml-2"><ArrowRight /></el-icon>
                        </el-button>
                    </Link>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 2px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 20px;
}
</style>