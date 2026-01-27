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
    Clock,
    List,
    User,
    Calendar
} from '@element-plus/icons-vue';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { router, usePage, useForm } from '@inertiajs/vue3';
import { ElNotification } from "element-plus";
import DialogModal from '@/Components/DialogModal.vue'; 
import axios from 'axios';

const props = defineProps({
    projects: Array,
    activeEntry: Object, 
    canEdit: Boolean,
    canDelete: Boolean,
    users: Array 
});

const page = usePage();
const emit = defineEmits(['edit', 'delete', 'view', 'start', 'stop']);

// --- Permisos ---
const canToggleTask = computed(() => page.props.auth.user?.permissions?.includes('Terminar tareas de proyectos'));
const canManageTime = computed(() => page.props.auth.user?.permissions?.includes('Gestionar tiempo en tareas'));

// --- Estado ---
const currentPage = ref(1);
const itemsPerPage = ref(50);
const showAdminTimeModal = ref(false);
const adminProject = ref(null);
const isSubmitting = ref(false); // Estado de carga manual

const adminForm = useForm({
    user_id: null,
    project_id: null,
    task_id: null,
    action: 'start', 
    duration: 1, 
    date: new Date().toISOString().split('T')[0],
});

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

// Color específico para tareas
const getTaskProgressColor = (task) => {
    if (task.completed_at) return '#13ce66'; // Verde fuerte (Success Element Plus)
    const p = getProgress(task.budgeted_hours, task.consumed_hours);
    return getProgressColor(p);
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

const openTimeManager = (project) => {
    adminProject.value = project;
    adminForm.reset();
    adminForm.project_id = project.id; 
    adminForm.action = 'start';
    showAdminTimeModal.value = true;
};

const submitAdminTime = () => {
    if (!adminForm.user_id) {
        ElNotification.warning('Selecciona un usuario');
        return;
    }

    isSubmitting.value = true;

    if (adminForm.action === 'add') {
        adminForm.post(route('projects.add-time-entry'), {
            onSuccess: () => {
                showAdminTimeModal.value = false;
                ElNotification.success('Tiempo agregado correctamente');
                router.reload();
            },
            onFinish: () => {
                isSubmitting.value = false;
            }
        });
    } else {
        const url = adminForm.action === 'stop' 
            ? route('projects.stop', adminProject.value.id)
            : route('projects.start', adminProject.value.id);

        axios.post(url, {
            user_id: adminForm.user_id,
            task_id: adminForm.task_id
        }).then(() => {
            ElNotification.success(`Acción ejecutada correctamente.`);
            showAdminTimeModal.value = false;
            router.reload(); 
        }).catch(err => {
            console.error(err);
            ElNotification.error(err.response?.data?.message || 'Error al ejecutar acción');
        }).finally(() => {
            isSubmitting.value = false;
        });
    }
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
            <!-- Columna Expandible: Detalle de Tareas -->
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
                                            <div class="flex items-center gap-2">
                                                <el-tag size="small" type="info" effect="plain" class="!text-[10px]">
                                                    {{ task.department?.name || 'General' }}
                                                </el-tag>
                                                <el-tag v-if="task.completed_at" size="small" type="success" effect="dark" class="!text-[10px]">
                                                    TERMINADA
                                                </el-tag>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Métricas y Barra de Progreso -->
                                    <div class="flex flex-col items-end gap-1 min-w-[200px]">
                                        <div class="text-xs font-mono text-gray-600 flex justify-between w-full">
                                            <span>Progreso:</span>
                                            <span class="font-bold">
                                                <!-- Ahora usa task.consumed_hours del modelo -->
                                                {{ task.consumed_hours }} / {{ task.budgeted_hours }} h
                                            </span>
                                        </div>
                                        <!-- Barra de progreso ajustada -->
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

            <el-table-column fixed="right" label="Acciones" width="160" align="right">
                <template #default="scope">
                    <div class="flex items-center justify-end gap-2" @click.stop>
                        
                        <!-- Botón Admin Time -->
                        <el-tooltip v-if="canManageTime" content="Gestionar tiempo (Admin)" placement="top">
                            <el-button type="warning" circle plain size="small" @click="openTimeManager(scope.row)">
                                <el-icon><Clock /></el-icon>
                            </el-button>
                        </el-tooltip>

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

        <!-- MODAL ADMINISTRACIÓN DE TIEMPO -->
        <!-- Se sustituyó DialogModal por el-dialog para consistencia de UI -->
        <el-dialog
            v-model="showAdminTimeModal"
            title="Gestión de tiempo administrativa"
            width="500px"
            destroy-on-close
            align-center
        >
            <div v-if="adminProject" class="space-y-4">
                <div class="bg-orange-50 p-3 rounded-lg border border-orange-100 text-sm text-gray-600 flex items-center gap-3">
                    <el-icon class="text-orange-500 text-xl"><OfficeBuilding /></el-icon>
                    <div>
                        <p class="text-xs uppercase text-gray-400 font-bold">Proyecto</p>
                        <p class="font-bold text-gray-800">{{ adminProject.name }}</p>
                    </div>
                </div>

                <el-form label-position="top">
                    <el-form-item label="Seleccionar colaborador">
                        <el-select v-model="adminForm.user_id" placeholder="Buscar usuario" class="!w-full" filterable>
                            <template #prefix><el-icon><User /></el-icon></template>
                            <el-option 
                                v-for="user in users" 
                                :key="user.id" 
                                :label="user.name" 
                                :value="user.id" 
                            />
                        </el-select>
                        <div v-if="adminForm.errors.user_id" class="text-red-500 text-xs mt-1">{{ adminForm.errors.user_id }}</div>
                    </el-form-item>

                    <el-form-item label="Tarea (Opcional)">
                        <el-select v-model="adminForm.task_id" placeholder="General del proyecto" class="!w-full" clearable filterable>
                            <template #prefix><el-icon><List /></el-icon></template>
                            <el-option 
                                v-for="task in adminProject.tasks" 
                                :key="task.id" 
                                :label="task.description" 
                                :value="task.id" 
                            />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Acción a realizar">
                        <el-radio-group v-model="adminForm.action">
                            <el-radio-button label="start" class="flex-1">Iniciar timer</el-radio-button>
                            <el-radio-button label="stop" class="flex-1">Detener timer</el-radio-button>
                            <el-radio-button label="add" class="flex-1">Agregar horas</el-radio-button>
                        </el-radio-group>
                    </el-form-item>

                    <div v-if="adminForm.action === 'add'" class="animate-fade-in bg-gray-50 p-4 rounded-lg border border-gray-100">
                        <div class="grid grid-cols-2 gap-4">
                            <el-form-item label="Horas a agregar">
                                <el-input-number v-model="adminForm.duration" :min="0.1" :step="0.5" class="!w-full" controls-position="right" />
                            </el-form-item>
                            <el-form-item label="Fecha">
                                <el-date-picker 
                                    v-model="adminForm.date" 
                                    type="date" 
                                    placeholder="Selecciona fecha" 
                                    class="!w-full"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                    :clearable="false"
                                />
                            </el-form-item>
                        </div>
                    </div>
                </el-form>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="showAdminTimeModal = false" :disabled="isSubmitting || adminForm.processing">Cancelar</el-button>
                    <el-button type="primary" @click="submitAdminTime" :loading="isSubmitting || adminForm.processing" color="#1676A2">
                        Ejecutar acción
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>