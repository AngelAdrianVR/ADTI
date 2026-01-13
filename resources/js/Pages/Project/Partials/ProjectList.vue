<script setup>
import { router } from '@inertiajs/vue3';
import { 
    VideoPlay, 
    CircleCheck, 
    Edit, 
    Delete 
} from '@element-plus/icons-vue';

const props = defineProps({
    projects: Array,
    activeEntry: Object,
});

const emit = defineEmits(['edit', 'delete']);

// --- Helpers Locales ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    // const options = { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
    const options = { day: 'numeric', month: 'long', year: 'numeric' };
    return new Intl.DateTimeFormat('es-MX', options).format(date).replace('p. m.', 'pm').replace('a. m.', 'am');
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

// --- Acciones ---
const goToProjectDetail = (row) => {
    router.get(route('projects.show', row.id));
};

const startWork = (project) => {
    router.post(route('projects.start', project.id), {}, { preserveScroll: true });
};

const stopWork = (project) => {
    router.post(route('projects.stop', project.id), {}, { preserveScroll: true });
};
</script>

<template>
    <el-table 
        :data="projects" 
        style="width: 100%" 
        stripe
        @row-click="goToProjectDetail"
        row-class-name="cursor-pointer hover:bg-gray-50 transition"
    >
        <el-table-column label="Proyecto / Cliente" min-width="200">
            <template #default="scope">
                <div class="font-bold text-gray-800">{{ scope.row.name }}</div>
                <div class="text-xs text-gray-500">{{ scope.row.client }}</div>
                <el-tag v-if="scope.row.status === 'finished'" size="small" type="info" effect="plain" class="mt-1">Terminado</el-tag>
            </template>
        </el-table-column>

        <el-table-column label="Cronograma" min-width="180">
            <template #default="scope">
                <div class="text-xs">
                    <p><span class="font-semibold">Inicio:</span> {{ formatDate(scope.row.start_date) }}</p>
                    <p class="text-gray-500 mt-1"><span class="font-semibold">Est. Fin:</span> {{ formatDate(scope.row.estimated_end_date) }}</p>
                </div>
            </template>
        </el-table-column>

        <el-table-column label="Horas" min-width="160">
            <template #default="scope">
                <div class="flex flex-col space-y-1">
                    <div class="flex justify-between text-xs text-gray-600">
                        <span>{{ scope.row.consumed_hours }}h usadas</span>
                        <span>{{ scope.row.budgeted_hours }}h pres.</span>
                    </div>
                    <el-progress 
                        :percentage="calculateProgress(scope.row)" 
                        :color="customColors" 
                        :format="() => ''" 
                        :stroke-width="8" 
                    />
                </div>
            </template>
        </el-table-column>

        <el-table-column label="Trabajando Ahora" min-width="120">
            <template #default="scope">
                <div v-if="scope.row.current_workers && scope.row.current_workers.length" class="flex -space-x-2 overflow-hidden">
                    <el-tooltip v-for="worker in scope.row.current_workers" :key="worker.id" :content="worker.name" placement="top">
                        <img class="inline-block h-8 w-8 rounded-full ring-2 ring-white cursor-pointer object-cover" :src="worker.profile_photo_url" :alt="worker.name" />
                    </el-tooltip>
                </div>
                <span v-else class="text-xs text-gray-400 italic">Nadie activo</span>
            </template>
        </el-table-column>

        <el-table-column fixed="right" label="Acciones" width="160" align="right">
            <template #default="scope">
                <div class="flex items-center justify-end space-x-2" @click.stop>
                    <el-tooltip v-if="scope.row.status === 'active'" :content="activeEntry?.project_id === scope.row.id ? 'Detener' : 'Iniciar Trabajo'">
                        <el-button v-if="activeEntry?.project_id === scope.row.id" type="danger" circle size="small" :icon="CircleCheck" @click="stopWork(scope.row)" />
                        <el-button v-else type="success" circle size="small" :icon="VideoPlay" @click="startWork(scope.row)" />
                    </el-tooltip>
                    <el-button v-if="$page.props.auth.user.permissions.includes('Editar proyectos')" type="primary" circle plain size="small" :icon="Edit" @click="$emit('edit', scope.row)" />
                    <el-button v-if="$page.props.auth.user.permissions.includes('Eliminar proyectos')" type="danger" circle plain size="small" :icon="Delete" @click="$emit('delete', scope.row)" />
                </div>
            </template>
        </el-table-column>
    </el-table>
</template>