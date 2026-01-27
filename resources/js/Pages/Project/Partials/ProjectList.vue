<script setup>
import { ref, computed } from 'vue';
import { 
    MoreFilled, 
    View, 
    Delete, 
    EditPen,
    VideoPlay,
    CircleCheck,
    OfficeBuilding,
    List,
} from '@element-plus/icons-vue';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { router, usePage } from '@inertiajs/vue3';
import { ElNotification } from "element-plus";
import axios from 'axios';

const props = defineProps({
    projects: Array,
    activeEntry: Object, 
    canEdit: Boolean,
    canDelete: Boolean,
});

const page = usePage();
const emit = defineEmits(['edit', 'delete', 'view', 'start', 'stop']);

// --- Permisos ---
const canToggleTask = computed(() => page.props.auth.user?.permissions?.includes('Terminar tareas de proyectos'));
const canManageTime = computed(() => page.props.auth.user?.permissions?.includes('Gestionar tiempo en tareas'));

// --- Estado ---
const currentPage = ref(1);
const itemsPerPage = ref(50);

// --- Computed ---
const paginatedProjects = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return props.projects.slice(start, end);
});

// --- Helpers ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM, yyyy', { locale: es });
};

const getProgress = (budgeted, consumed) => {
    const total = parseFloat(budgeted || 0);
    const current = parseFloat(consumed || 0);
    if (total === 0) return 0;
    const percent = (current / total) * 100;
    return Math.min(100, Math.round(percent));
};

const getProgressColor = (percent) => {
    if (percent >= 100) return '#F56C6C'; // Rojo
    if (percent > 80) return '#E6A23C';  // Naranja
    return '#1676A2';                    // Azul
};

const getTaskProgressColor = (task) => {
    if (task.completed_at) return '#13ce66'; 
    const p = getProgress(task.budgeted_hours, task.consumed_hours);
    return getProgressColor(p);
};

// Verificar si hay alguien trabajando en una tarea específica
const getActiveUsersInTask = (projectId, taskId) => {
    const project = props.projects.find(p => p.id === projectId);
    if (!project || !project.active_time_entries) return [];
    
    // Retorna las entries que coinciden con la tarea
    return project.active_time_entries.filter(entry => entry.task_id === taskId);
};

const handlePageChange = (val) => {
    currentPage.value = val;
};

// --- Acciones ---
const handleToggleTask = (task) => {
    router.put(route('tasks.toggle-status', task.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            const status = task.completed_at ? 'reactivada' : 'terminada';
            ElNotification({
                title: 'Estatus actualizado',
                message: `La tarea ha sido marcada como ${status}.`,
                type: 'success',
            });
        },
    });
};

const stopUserWork = (projectId, userId, userName) => {
    axios.post(route('projects.stop', projectId), {
        user_id: userId
    }).then(() => {
        ElNotification.success(`Se detuvo la tarea de ${userName}`);
        // Recargar para ver cambios
        router.reload(); 
    }).catch(err => {
        ElNotification.error(err.response?.data?.message || 'Error al detener la tarea');
    });
};
</script>

<template>
    <div class="px-2 pb-4">
        <el-table 
            :data="paginatedProjects" 
            @row-click="(row) => emit('view', row)"
            style="width: 100%"
            class="cursor-pointer"
            :row-class-name="'hover:bg-gray-50 transition-colors'"
            row-key="id"
        >
            <!-- Columna Expandible: Desglose de tareas -->
            <el-table-column type="expand">
                <template #default="props">
                    <div class="px-4 md:px-12 py-4 bg-gray-50/50 border-y border-gray-100">
                        <h4 class="text-xs font-bold text-gray-400 uppercase mb-3 tracking-wider flex items-center gap-2">
                            <el-icon><List /></el-icon> Desglose de tareas
                        </h4>
                        
                        <div v-if="props.row.tasks?.length" class="space-y-3">
                            <div 
                                v-for="task in props.row.tasks" 
                                :key="task.id"
                                class="relative rounded-lg border transition-all duration-300 overflow-hidden bg-white hover:shadow-sm"
                                :class="task.completed_at ? 'border-green-300 bg-green-50' : 'border-gray-200'"
                            >
                                <div class="relative p-3 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 z-10">
                                    
                                    <!-- Checkbox y Nombre -->
                                    <div class="flex items-center gap-3 flex-1">
                                        <div @click.stop v-if="canToggleTask">
                                            <el-checkbox 
                                                :model-value="!!task.completed_at" 
                                                @change="handleToggleTask(task)"
                                                size="large"
                                                class="!mr-0"
                                            />
                                        </div>
                                        <div v-else>
                                            <el-icon v-if="task.completed_at" class="text-green-500 text-lg"><CircleCheck /></el-icon>
                                            <el-icon v-else class="text-gray-300 text-lg"><CircleCheck /></el-icon>
                                        </div>

                                        <div class="flex flex-col">
                                            <span 
                                                class="text-sm font-medium transition-colors"
                                                :class="task.completed_at ? 'text-green-800 line-through decoration-green-800/30' : 'text-gray-700'"
                                            >
                                                {{ task.description }}
                                            </span>
                                            <div class="flex items-center gap-2 mt-1">
                                                <el-tag size="small" type="info" effect="plain" class="!text-[10px]">
                                                    {{ task.department?.name || 'General' }}
                                                </el-tag>
                                                <el-tag v-if="task.completed_at" size="small" type="success" effect="dark" class="!text-[10px]">
                                                    Terminada
                                                </el-tag>
                                            </div>
                                        </div>

                                        <!-- AVATARS EN LA TAREA ESPECÍFICA -->
                                        <!-- Mostramos quién trabaja en ESTA tarea -->
                                        <div class="flex -space-x-2 overflow-hidden ml-4">
                                            <div v-for="entry in getActiveUsersInTask(props.row.id, task.id)" :key="entry.id" @click.stop>
                                                <el-popconfirm
                                                    v-if="canManageTime"
                                                    title="¿Detener tarea?"
                                                    confirm-button-text="Sí"
                                                    cancel-button-text="No"
                                                    width="200"
                                                    @confirm="stopUserWork(props.row.id, entry.user.id, entry.user.name)"
                                                >
                                                    <template #reference>
                                                        <!-- Wrapper div para asegurar el click -->
                                                        <div class="inline-block cursor-pointer">
                                                            <el-tooltip :content="`Trabajando: ${entry.user.name}`" placement="top">
                                                                <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white relative group">
                                                                    <img 
                                                                        :src="entry.user.profile_photo_url" 
                                                                        :alt="entry.user.name"
                                                                        class="h-full w-full object-cover rounded-full"
                                                                    />
                                                                    <!-- Overlay rojo al hacer hover -->
                                                                    <div class="absolute inset-0 bg-red-500/20 rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                                        <div class="w-1.5 h-1.5 bg-red-600 rounded-full"></div>
                                                                    </div>
                                                                </div>
                                                            </el-tooltip>
                                                        </div>
                                                    </template>
                                                </el-popconfirm>
                                                
                                                <!-- Si no tiene permisos, solo ve el avatar -->
                                                <el-tooltip v-else :content="`Trabajando: ${entry.user.name}`" placement="top">
                                                     <img 
                                                        :src="entry.user.profile_photo_url" 
                                                        :alt="entry.user.name"
                                                        class="inline-block h-8 w-8 rounded-full ring-2 ring-white object-cover"
                                                    />
                                                </el-tooltip>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Métricas y Barra de Progreso -->
                                    <div class="flex flex-col items-end gap-1 min-w-[200px]">
                                        <div class="text-xs font-mono text-gray-600 flex justify-between w-full">
                                            <span>Progreso:</span>
                                            <span class="font-bold">
                                                {{ task.consumed_hours }} / {{ task.budgeted_hours }} h
                                            </span>
                                        </div>
                                        <el-progress 
                                            :percentage="task.completed_at ? 100 : getProgress(task.budgeted_hours, task.consumed_hours)" 
                                            :color="getTaskProgressColor(task)"
                                            :show-text="false" 
                                            :stroke-width="12"
                                            class="w-full"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400 italic py-2 text-center border border-dashed border-gray-200 rounded-lg">
                            No hay tareas registradas.
                        </p>
                    </div>
                </template>
            </el-table-column>

            <el-table-column label="Proyecto / Cliente" min-width="280" prop="name" sortable>
                <template #default="scope">
                    <div class="py-2">
                        <p class="font-bold text-gray-800 text-sm leading-tight mb-0.5">{{ scope.row.name }}</p>
                        <p v-if="scope.row.description" class="text-xs text-gray-400 italic mb-1 line-clamp-1">{{ scope.row.description }}</p>
                        <div class="flex items-center text-xs text-gray-500 font-medium bg-gray-100 w-fit px-2 py-0.5 rounded-full border border-gray-200">
                            <el-icon class="mr-1"><OfficeBuilding /></el-icon>
                            {{ scope.row.client }}
                        </div>
                    </div>
                </template>
            </el-table-column>

            <el-table-column label="Presupuesto" min-width="180" prop="consumed_hours" sortable>
                <template #default="scope">
                    <div class="pr-4">
                        <div class="flex justify-between text-[10px] mb-1 text-gray-500 font-mono">
                            <span>{{ scope.row.consumed_hours }}h</span>
                            <span>{{ scope.row.budgeted_hours }}h</span>
                        </div>
                        <el-progress 
                            :percentage="getProgress(scope.row.budgeted_hours, scope.row.consumed_hours)" 
                            :color="getProgressColor" 
                            :show-text="false" 
                            :stroke-width="8"
                        />
                    </div>
                </template>
            </el-table-column>

             <!-- NUEVA COLUMNA: EQUIPO ACTIVO -->
            <el-table-column label="Equipo activo" min-width="150">
                <template #default="scope">
                    <div v-if="scope.row.active_time_entries && scope.row.active_time_entries.length > 0" class="flex flex-wrap gap-1" @click.stop>
                        
                        <div v-for="entry in scope.row.active_time_entries" :key="entry.id">
                            <el-popconfirm
                                v-if="canManageTime"
                                title="¿Detener tarea?"
                                confirm-button-text="Sí"
                                cancel-button-text="No"
                                width="220"
                                @confirm="stopUserWork(scope.row.id, entry.user.id, entry.user.name)"
                            >
                                <template #reference>
                                    <!-- SOLUCIÓN AL BUG: Envolver el contenido en un div para capturar el click correctamente -->
                                    <div class="cursor-pointer inline-block">
                                        <el-tooltip effect="dark" placement="top">
                                            <template #content>
                                                <div class="text-xs">
                                                    <p class="font-bold">{{ entry.user.name }}</p>
                                                    <p class="opacity-80">Tarea: {{ entry.task ? entry.task.description : 'General' }}</p>
                                                    <p class="text-[10px] text-yellow-300 mt-1">Clic para detener</p>
                                                </div>
                                            </template>
                                            <div class="relative group">
                                                <el-avatar 
                                                    :size="32" 
                                                    :src="entry.user.profile_photo_url"
                                                    class="border-2 border-white shadow-sm transition-transform hover:scale-110"
                                                />
                                            </div>
                                        </el-tooltip>
                                    </div>
                                </template>
                            </el-popconfirm>

                            <!-- Versión solo lectura para usuarios sin permisos -->
                            <el-tooltip v-else effect="dark" placement="top">
                                <template #content>
                                    <div class="text-xs">
                                        <p class="font-bold">{{ entry.user.name }}</p>
                                        <p class="opacity-80">Tarea: {{ entry.task ? entry.task.description : 'General' }}</p>
                                    </div>
                                </template>
                                <el-avatar 
                                    :size="32" 
                                    :src="entry.user.profile_photo_url"
                                    class="border-2 border-white shadow-sm"
                                />
                            </el-tooltip>
                        </div>

                    </div>
                    <span v-else class="text-xs text-gray-300 italic">Inactivo</span>
                </template>
            </el-table-column>

            <el-table-column label="Fechas" min-width="170" prop="start_date" sortable>
                <template #default="scope">
                    <div class="text-xs text-gray-600 space-y-1">
                        <div class="flex items-center">
                            <span class="w-12 text-gray-400">Inicio:</span>
                            <span class="font-medium">{{ formatDate(scope.row.start_date) }}</span>
                        </div>
                        <div class="flex items-center" v-if="scope.row.estimated_end_date">
                            <span class="w-12 text-gray-400">Fin est:</span>
                            <span>{{ formatDate(scope.row.estimated_end_date) }}</span>
                        </div>
                    </div>
                </template>
            </el-table-column>

            <el-table-column fixed="right" label="Acciones" width="120" align="right">
                <template #default="scope">
                    <div class="flex items-center justify-end gap-2" @click.stop>
                        
                        <!-- Botón Play/Stop (Personal) -->
                        <el-tooltip 
                            v-if="scope.row.status === 'active'" 
                            :content="activeEntry?.project_id === scope.row.id ? 'Detener mi trabajo' : 'Iniciar mi trabajo'" 
                            placement="top"
                        >
                            <el-button 
                                v-if="activeEntry?.project_id === scope.row.id"
                                type="danger" circle size="small" @click="emit('stop', scope.row)"
                            >
                                <el-icon><CircleCheck /></el-icon>
                            </el-button>
                            <el-button 
                                v-else 
                                type="success" circle size="small" @click="emit('start', scope.row)"
                            >
                                <el-icon><VideoPlay /></el-icon>
                            </el-button>
                        </el-tooltip>

                        <el-dropdown trigger="click">
                            <el-button text circle size="small">
                                <el-icon class="rotate-90"><MoreFilled /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item @click="emit('view', scope.row)">
                                        <el-icon><View /></el-icon> Ver detalles
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="canEdit" @click="emit('edit', scope.row)">
                                        <el-icon><EditPen /></el-icon> Editar
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="canDelete" divided class="text-red-500" @click="emit('delete', scope.row)">
                                        <el-icon><Delete /></el-icon> Eliminar
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                </template>
            </el-table-column>
        </el-table>

        <!-- Paginación -->
        <div class="flex justify-end mt-4 px-2">
            <el-pagination 
                layout="prev, pager, next" 
                :total="projects.length" 
                :page-size="itemsPerPage"
                @current-change="handlePageChange"
                background
            />
        </div>
    </div>
</template>