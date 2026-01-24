<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProjectList from './Partials/ProjectList.vue';
import ProjectCalendar from './Partials/ProjectCalendar.vue';
import { ElNotification } from "element-plus";
import { 
    Search, 
    Plus, 
    List,
    Calendar,
    Connection
} from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    projects: Array,
    activeEntry: Object
});

const page = usePage();

// --- Permisos ---
const canCreate = computed(() => page.props.auth.user?.permissions?.includes('Crear proyectos'));
const canEdit = computed(() => page.props.auth.user?.permissions?.includes('Editar proyectos'));
const canDelete = computed(() => page.props.auth.user?.permissions?.includes('Eliminar proyectos'));

// --- Estado ---
const search = ref('');
const activeTab = ref('active'); // 'active' | 'finished' | 'all'
const viewMode = ref('list'); // 'list' | 'calendar'

// --- Estado Modal Inicio Tarea ---
const showTaskSelectionModal = ref(false);
const projectToStart = ref(null);
const taskForm = useForm({
    task_id: null,
});

// --- Computed Counts ---
const totalActive = computed(() => props.projects.filter(p => p.status === 'active').length);
const totalFinished = computed(() => props.projects.filter(p => p.status === 'finished').length);
const totalAll = computed(() => props.projects.length);

// --- Computed Filter ---
const filteredProjects = computed(() => {
    let result = props.projects;

    // Filtros de Tab (Solo aplica a la lista, el calendario muestra todo o filtra internamente)
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

// --- Lógica Iniciar/Detener Trabajo ---

const handleStartWork = (project) => {
    // 1. Verificamos si el proyecto tiene tareas
    if (project.tasks && project.tasks.length > 0) {
        // Si tiene tareas, abrimos modal para que el usuario seleccione
        projectToStart.value = project;
        taskForm.reset();
        showTaskSelectionModal.value = true;
    } else {
        // Si NO tiene tareas, iniciamos directamente (task_id = null)
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
             // Recarga completa para asegurar que el Timer Global (AppLayout) se actualice
             window.location.reload(); 
        }
    } catch (error) {
        console.error(error);
        ElNotification.error('Error al iniciar el trabajo. Intenta de nuevo.');
    }
};

const handleStopWork = async (project) => {
    try {
        await axios.post(route('projects.stop', project.id));
        ElNotification.success('Trabajo detenido correctamente');
        // Recarga completa para limpiar el Timer Global
        window.location.reload();
    } catch (error) {
        ElNotification.error('No se pudo detener el trabajo');
    }
};
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

                        <!-- Botón Crear -->
                        <el-button v-if="canCreate" type="primary" @click="createProject" color="#1676A2" class="!rounded-lg w-full sm:w-auto">
                            <el-icon class="mr-2"><Plus /></el-icon> Nuevo
                        </el-button>
                    </div>
                </div>

                <!-- CONTENIDO PRINCIPAL -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden min-h-[600px] animate-fade-in">
                    
                    <!-- Tabs (Solo visible en modo lista) -->
                    <el-tabs v-if="viewMode === 'list'" v-model="activeTab" class="px-6 pt-4 project-tabs">
                        <el-tab-pane :label="`En Curso (${totalActive})`" name="active" />
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

        <!-- MODAL: SELECCIÓN DE TAREA -->
        <el-dialog 
            v-model="showTaskSelectionModal" 
            title="Iniciar Trabajo" 
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
                            placeholder="Selecciona una tarea de la lista" 
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
                            >
                                <div class="flex justify-between items-center w-full">
                                    <span>{{ task.description }}</span>
                                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ task.department?.name }}</span>
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