<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProjectList from './Partials/ProjectList.vue';
import ProjectCalendar from './Partials/ProjectCalendar.vue';
import { ElNotification } from "element-plus";
import { 
    Search, 
    Plus, 
    List,
    Calendar,
    Connection,
    User,
    VideoPlay,
    OfficeBuilding
} from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    projects: Array,
    activeEntry: Object,
    users: Array // Todos los usuarios (incluye info de su active_time_entry)
});

const page = usePage();

// --- Permisos ---
const canCreate = computed(() => page.props.auth.user?.permissions?.includes('Crear proyectos'));
const canEdit = computed(() => page.props.auth.user?.permissions?.includes('Editar proyectos'));
const canDelete = computed(() => page.props.auth.user?.permissions?.includes('Eliminar proyectos'));
const canManageTime = computed(() => page.props.auth.user?.permissions?.includes('Gestionar tiempo en tareas'));

// --- Estado ---
const search = ref('');
const activeTab = ref('active'); 
const viewMode = ref('list'); 

// --- Estado Modal Inicio Tarea (Usuario Normal) ---
const showTaskSelectionModal = ref(false);
const projectToStart = ref(null);
const taskForm = useForm({
    task_id: null,
});

// --- Estado Modal Asignación Administrativa (Admin) ---
const showAdminAssignModal = ref(false);
const adminAssignForm = useForm({
    user_id: null,
    project_id: null,
    task_id: null,
});

// --- Computed Counts ---
const totalActive = computed(() => props.projects.filter(p => p.status === 'active').length);
const totalFinished = computed(() => props.projects.filter(p => p.status === 'finished').length);
const totalAll = computed(() => props.projects.length);

const filteredProjects = computed(() => {
    let result = props.projects;
    if (viewMode.value === 'list' && activeTab.value !== 'all') {
        result = result.filter(p => p.status === activeTab.value);
    }
    if (search.value) {
        const q = search.value.toLowerCase();
        result = result.filter(p => 
            p.name.toLowerCase().includes(q) ||
            p.client.toLowerCase().includes(q) ||
            p.description?.toLowerCase().includes(q)
        );
    }
    return result;
});

// Usuarios libres (sin tarea activa) para el selector administrativo
const freeUsers = computed(() => {
    if (!props.users) return [];
    return props.users.filter(u => !u.active_time_entry);
});

// Tareas del proyecto seleccionado en el modal administrativo
const adminSelectedProjectTasks = computed(() => {
    if (!adminAssignForm.project_id) return [];
    const project = props.projects.find(p => p.id === adminAssignForm.project_id);
    return project ? project.tasks : [];
});

// --- Acciones Generales ---
const createProject = () => {
    router.visit(route('projects.create'));
};

const handleView = (project) => {
    router.visit(route('projects.show', project.id));
};

const handleEdit = (project) => {
    router.visit(route('projects.edit', project.id));
};

const handleDelete = (project) => {
    router.delete(route('projects.destroy', project.id), {
        onSuccess: () => ElNotification.success('Proyecto eliminado correctamente'),
        onError: () => ElNotification.error('No se pudo eliminar el proyecto')
    });
};

// --- Lógica Iniciar/Detener Trabajo (Usuario Normal) ---
const handleStartWork = (project) => {
    if (project.tasks && project.tasks.length > 0) {
        projectToStart.value = project;
        taskForm.reset();
        showTaskSelectionModal.value = true;
    } else {
        startWorkRequest(project.id, null);
    }
};

const confirmStartWithTask = () => {
    if (!taskForm.task_id) {
        ElNotification.warning('Por favor selecciona una tarea para continuar.');
        return;
    }
    startWorkRequest(projectToStart.value.id, taskForm.task_id);
    showTaskSelectionModal.value = false;
};

const startWorkRequest = async (projectId, taskId) => {
    try {
        const response = await axios.post(route('projects.start', projectId), {
            task_id: taskId
        });
        
        if (response.status === 200 || response.status === 302) {
             ElNotification.success({
                title: 'Trabajo iniciado',
                message: taskId ? 'Registrando tiempo en la tarea seleccionada.' : 'Registrando tiempo general en el proyecto.'
             });
             window.location.reload(); 
        }
    } catch (error) {
        console.error(error);
        ElNotification.error(error.response?.data?.message || 'Error al iniciar el trabajo.');
    }
};

const handleStopWork = async (project) => {
    try {
        await axios.post(route('projects.stop', project.id));
        ElNotification.success('Trabajo detenido correctamente');
        window.location.reload();
    } catch (error) {
        ElNotification.error('No se pudo detener el trabajo');
    }
};

// --- Lógica Asignación Administrativa ---
const openAdminAssignModal = () => {
    adminAssignForm.reset();
    showAdminAssignModal.value = true;
};

const submitAdminAssignment = () => {
    if (!adminAssignForm.user_id || !adminAssignForm.project_id) {
        ElNotification.warning('Usuario y Proyecto son obligatorios');
        return;
    }

    axios.post(route('projects.start', adminAssignForm.project_id), {
        user_id: adminAssignForm.user_id,
        task_id: adminAssignForm.task_id
    }).then(() => {
        ElNotification.success('Tarea iniciada correctamente para el usuario.');
        showAdminAssignModal.value = false;
        router.reload();
    }).catch(err => {
        ElNotification.error(err.response?.data?.message || 'Error al asignar tarea');
    });
};

// Resetear tarea si cambia el proyecto en admin modal
watch(() => adminAssignForm.project_id, () => {
    adminAssignForm.task_id = null;
});
</script>

<template>
    <AppLayout title="Proyectos">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header -->
                <div class="flex flex-col lg:flex-row justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Proyectos</h1>
                        <p class="text-xs text-gray-500 mt-1">Gestión de obras, presupuestos y tiempos.</p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                        
                        <!-- Toggle Vista -->
                        <div class="bg-white p-1 rounded-lg border border-gray-200 shadow-sm flex items-center">
                            <button @click="viewMode = 'list'" class="px-3 py-1.5 rounded-md text-sm font-medium transition-all flex items-center gap-2" :class="viewMode === 'list' ? 'bg-gray-100 text-[#1676A2]' : 'text-gray-500 hover:bg-gray-50'">
                                <el-icon><List /></el-icon> Lista
                            </button>
                            <div class="w-px h-4 bg-gray-200 mx-1"></div>
                            <button @click="viewMode = 'calendar'" class="px-3 py-1.5 rounded-md text-sm font-medium transition-all flex items-center gap-2" :class="viewMode === 'calendar' ? 'bg-gray-100 text-[#1676A2]' : 'text-gray-500 hover:bg-gray-50'">
                                <el-icon><Calendar /></el-icon> Calendario
                            </button>
                        </div>

                        <!-- Buscador -->
                        <div class="w-full sm:w-64">
                            <el-input v-model="search" placeholder="Buscar proyecto..." clearable>
                                <template #prefix><el-icon><Search /></el-icon></template>
                            </el-input>
                        </div>
                        
                        <!-- Botón Asignar Tarea (Solo Admin) -->
                        <el-button 
                            v-if="canManageTime" 
                            type="warning" 
                            plain
                            @click="openAdminAssignModal" 
                            class="!rounded-lg w-full sm:w-auto"
                        >
                            <el-icon class="mr-2"><VideoPlay /></el-icon> Asignar
                        </el-button>

                        <!-- Botón Crear -->
                        <el-button v-if="canCreate" type="primary" @click="createProject" color="#1676A2" class="!rounded-lg w-full sm:w-auto">
                            <el-icon class="mr-2"><Plus /></el-icon> Nuevo
                        </el-button>
                    </div>
                </div>

                <!-- CONTENIDO PRINCIPAL -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden min-h-[600px] animate-fade-in">
                    
                    <!-- Tabs -->
                     <el-tabs v-if="viewMode === 'list'" v-model="activeTab" class="px-6 pt-4 project-tabs">
                        <el-tab-pane :label="`En curso (${totalActive})`" name="active" />
                        <el-tab-pane :label="`Terminados (${totalFinished})`" name="finished" />
                        <el-tab-pane :label="`Todos (${totalAll})`" name="all" />
                    </el-tabs>

                    <!-- COMPONENTE: LISTA -->
                    <ProjectList 
                        v-if="viewMode === 'list'"
                        :projects="filteredProjects"
                        :active-entry="activeEntry"
                        :can-edit="canEdit"
                        :can-delete="canDelete"
                        :users="users"
                        @edit="handleEdit"
                        @delete="handleDelete"
                        @view="handleView"
                        @start="handleStartWork"
                        @stop="handleStopWork"
                    />

                    <!-- COMPONENTE: CALENDARIO -->
                    <ProjectCalendar 
                        v-else
                        :projects="filteredProjects"
                        :active-entry="activeEntry"
                        @view="handleView"
                        @start="handleStartWork"
                        @stop="handleStopWork"
                    />

                </div>
            </div>
        </main>

        <!-- MODAL: SELECCIÓN DE TAREA (USUARIO NORMAL) -->
        <el-dialog 
            v-model="showTaskSelectionModal" 
            title="Iniciar trabajo" 
            width="450px" 
            align-center 
            destroy-on-close
            class="!rounded-xl"
        >
            <div class="space-y-4">
                <div class="bg-blue-50 p-3 rounded-lg border border-blue-100 mb-4">
                    <p class="text-sm text-gray-700">
                        Estás por iniciar en: <span class="font-bold text-[#1676A2]">{{ projectToStart?.name }}</span>
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Selecciona la tarea específica para imputar las horas.</p>
                </div>
                
                <el-form label-position="top" @submit.prevent="confirmStartWithTask">
                    <el-form-item label="Tarea Asignada">
                        <el-select 
                            v-model="taskForm.task_id" 
                            placeholder="Selecciona una tarea" 
                            class="!w-full" 
                            filterable
                            size="large"
                        >
                            <template #prefix><el-icon><Connection /></el-icon></template>
                            <el-option 
                                v-for="task in projectToStart?.tasks" 
                                :key="task.id" 
                                :label="task.description" 
                                :value="task.id"
                                :disabled="!!task.completed_at"
                            >
                                <div class="flex justify-between items-center w-full" :class="{'opacity-50': task.completed_at}">
                                    <span>{{ task.description }}</span>
                                    <div class="flex items-center gap-2">
                                        <span v-if="task.completed_at" class="text-[10px] text-green-600 font-bold bg-green-100 px-1 rounded">FINALIZADA</span>
                                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ task.department?.name }}</span>
                                    </div>
                                </div>
                            </el-option>
                        </el-select>
                    </el-form-item>
                </el-form>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="showTaskSelectionModal = false">Cancelar</el-button>
                    <el-button type="primary" color="#1676A2" @click="confirmStartWithTask" :disabled="!taskForm.task_id">
                        Comenzar
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- MODAL: ASIGNACIÓN ADMINISTRATIVA (ADMIN) -->
        <el-dialog 
            v-model="showAdminAssignModal" 
            title="Asignar tarea a colaborador" 
            width="500px" 
            align-center 
            destroy-on-close
            class="!rounded-xl"
        >
            <div class="space-y-4">
                <div class="bg-orange-50 p-3 rounded-lg border border-orange-100 mb-4 text-sm text-gray-700">
                    <p class="flex items-center gap-2">
                        <el-icon class="text-orange-500"><User /></el-icon>
                        Solo se muestran usuarios que <b>no tienen tareas activas</b>.
                    </p>
                </div>

                <el-form label-position="top">
                    <!-- Selector de Usuario -->
                    <el-form-item label="1. Seleccionar colaborador disponible">
                        <el-select 
                            v-model="adminAssignForm.user_id" 
                            placeholder="Buscar usuario libre..." 
                            class="!w-full" 
                            filterable
                            size="large"
                        >
                            <template #prefix><el-icon><User /></el-icon></template>
                            <el-option 
                                v-for="user in freeUsers" 
                                :key="user.id" 
                                :label="user.name" 
                                :value="user.id" 
                            >
                                <div class="flex items-center gap-2">
                                    <el-avatar :size="20" :src="user.profile_photo_url" />
                                    <span>{{ user.name }}</span>
                                </div>
                            </el-option>
                            <template #empty>
                                <div class="p-3 text-center text-gray-400 text-sm">No hay usuarios libres en este momento.</div>
                            </template>
                        </el-select>
                    </el-form-item>

                    <!-- Selector de Proyecto -->
                    <el-form-item label="2. Seleccionar proyecto">
                        <el-select 
                            v-model="adminAssignForm.project_id" 
                            placeholder="Buscar proyecto..." 
                            class="!w-full" 
                            filterable
                            size="large"
                            :disabled="!adminAssignForm.user_id"
                        >
                            <template #prefix><el-icon><OfficeBuilding /></el-icon></template>
                            <el-option 
                                v-for="proj in projects.filter(p => p.status === 'active')" 
                                :key="proj.id" 
                                :label="proj.name" 
                                :value="proj.id" 
                            >
                                <div class="flex justify-between w-full">
                                    <span>{{ proj.name }}</span>
                                    <span class="text-xs text-gray-400">{{ proj.client }}</span>
                                </div>
                            </el-option>
                        </el-select>
                    </el-form-item>

                    <!-- Selector de Tarea -->
                    <el-form-item label="3. Seleccionar tarea (Opcional)">
                        <el-select 
                            v-model="adminAssignForm.task_id" 
                            placeholder="Selecciona tarea..." 
                            class="!w-full" 
                            filterable
                            size="large"
                            :disabled="!adminAssignForm.project_id"
                            no-data-text="Selecciona un proyecto primero"
                        >
                            <template #prefix><el-icon><Connection /></el-icon></template>
                            <el-option 
                                v-for="task in adminSelectedProjectTasks" 
                                :key="task.id" 
                                :label="task.description" 
                                :value="task.id"
                                :disabled="!!task.completed_at"
                            >
                                <div class="flex justify-between items-center w-full" :class="{'opacity-50': task.completed_at}">
                                    <span>{{ task.description }}</span>
                                    <div class="flex items-center gap-2">
                                        <span v-if="task.completed_at" class="text-[10px] text-green-600 font-bold bg-green-100 px-1 rounded">FINALIZADA</span>
                                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ task.department?.name }}</span>
                                    </div>
                                </div>
                            </el-option>
                        </el-select>
                    </el-form-item>
                </el-form>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="showAdminAssignModal = false">Cancelar</el-button>
                    <el-button 
                        type="warning" 
                        plain 
                        @click="submitAdminAssignment" 
                        :disabled="!adminAssignForm.user_id || !adminAssignForm.project_id"
                    >
                        <el-icon class="mr-1"><VideoPlay /></el-icon> Iniciar Tarea
                    </el-button>
                </div>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
/* Tabs Fix */
:deep(.el-tabs__nav-wrap::after) {
    background-color: #f3f4f6;
    height: 1px;
}
:deep(.el-tabs__item.is-active) {
    color: #1676A2;
}
:deep(.el-tabs__active-bar) {
    background-color: #1676A2;
}
</style>