<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProjectList from './Partials/ProjectList.vue';
import ProjectCalendar from './Partials/ProjectCalendar.vue';
import { 
    Clock, 
    CircleCheck, 
    Plus, 
    Calendar, 
    List 
} from '@element-plus/icons-vue';
import { ElNotification, ElMessageBox, ElMessage } from 'element-plus';

// --- Props ---
const props = defineProps({
    projects: Array,
    activeEntry: Object,
});

// --- Estado ---
const showModal = ref(false);
const isEditing = ref(false);
const filterStatus = ref('active'); 
const viewMode = ref('list'); // 'list' | 'calendar'

// --- Cronómetro (Global para el Banner) ---
const localDuration = ref(0);
let timerInterval = null;

// Cálculo limpio: Ahora menos Inicio
const calculateNetDuration = (entry) => {
    const start = new Date(entry.start_time);
    const now = new Date();
    const totalSeconds = (now - start) / 1000;
    return Math.max(0, Math.floor(totalSeconds));
};

watch(() => props.activeEntry, (newEntry) => {
    if (newEntry) {
        localDuration.value = calculateNetDuration(newEntry);
    } else {
        localDuration.value = 0;
    }
}, { immediate: true, deep: true });

onMounted(() => {
    timerInterval = setInterval(() => {
        if (props.activeEntry) {
            localDuration.value++;
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

const formatDuration = (totalSeconds) => {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = Math.floor(totalSeconds % 60);
    const pad = (n) => n.toString().padStart(2, '0');
    return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
};

const activeTimerDisplay = computed(() => formatDuration(localDuration.value));

// --- Filtros ---
const filteredProjects = computed(() => {
    if (filterStatus.value === 'all') return props.projects;
    return props.projects.filter(p => p.status === filterStatus.value);
});

// --- Formulario CRUD ---
const form = useForm({
    id: null,
    name: '',
    client: '',
    start_date: '',
    estimated_end_date: '',
    budgeted_hours: 0,
    status: 'active',
    description: '',
});

const openCreateModal = () => { isEditing.value = false; form.reset(); form.status = 'active'; showModal.value = true; };
const openEditModal = (project) => { isEditing.value = true; Object.assign(form, project); showModal.value = true; };

const submitForm = () => {
    if(isEditing.value) {
        form.put(route('projects.update', form.id), { onSuccess: () => { showModal.value = false; ElNotification({title: 'Éxito', type:'success'}); }});
    } else {
        form.post(route('projects.store'), { onSuccess: () => { showModal.value = false; ElNotification({title: 'Éxito', type:'success'}); }});
    }
};

const deleteProject = (project) => {
    ElMessageBox.confirm(`¿Eliminar "${project.name}"?`, 'Advertencia', { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' })
    .then(() => router.delete(route('projects.destroy', project.id), { onSuccess: () => ElMessage({ type: 'success', message: 'Eliminado' }) }));
};

// --- Acciones de Tiempo (Para el Banner) ---
const stopWork = (project) => router.post(route('projects.stop', project.id), {}, { preserveScroll: true, onSuccess: () => ElNotification({ title: 'Jornada terminada', type: 'info' }) });

</script>

<template>
    <AppLayout title="Proyectos">
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de proyectos</h2>
                    <div class="flex items-center space-x-4">
                        <el-radio-group v-model="viewMode" size="small">
                            <el-radio-button label="list"><el-icon class="mr-1"><List /></el-icon> Lista</el-radio-button>
                            <el-radio-button label="calendar"><el-icon class="mr-1"><Calendar /></el-icon> Calendario</el-radio-button>
                        </el-radio-group>
                        <el-button v-if="$page.props.auth.user.permissions.includes('Crear proyectos')" type="primary" :icon="Plus" @click="openCreateModal">Nuevo Proyecto</el-button>
                    </div>
                </div>

                <!-- Banner Flotante: Tarea Activa (Solo Start/Stop) -->
                <transition name="el-fade-in-linear">
                    <el-alert
                        v-if="activeEntry"
                        type="success"
                        :closable="false"
                        class="border shadow-sm border-green-200 bg-green-50"
                    >
                        <template #title>
                            <div class="flex flex-col md:flex-row items-center justify-between w-full gap-2">
                                <div class="flex items-center gap-6">
                                    <div class="flex flex-col">
                                        <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Tiempo en curso</span>
                                        <span class="text-2xl font-mono font-bold flex items-center text-green-700">
                                            <el-icon class="mr-2 animate-spin"><Clock /></el-icon>
                                            {{ activeTimerDisplay }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col justify-center ml-2 border-l pl-4 border-gray-200 h-full">
                                        <span class="text-xs text-gray-400">Proyecto:</span>
                                        <strong class="text-gray-700 text-sm truncate max-w-[200px]">{{ activeEntry.project?.name }}</strong>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <el-button size="default" type="danger" :icon="CircleCheck" @click.stop="stopWork(activeEntry.project)" round>
                                        Detener
                                    </el-button>
                                </div>
                            </div>
                        </template>
                    </el-alert>
                </transition>

                <!-- Contenedor Principal -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="mb-4 flex justify-between items-center">
                        <el-radio-group v-model="filterStatus" size="default">
                            <el-radio-button label="active">Activos</el-radio-button>
                            <el-radio-button label="finished">Terminados</el-radio-button>
                            <el-radio-button label="all">Todos</el-radio-button>
                        </el-radio-group>
                    </div>

                    <!-- VISTAS DINÁMICAS -->
                    <transition name="el-fade-in-linear" mode="out-in">
                        <ProjectList 
                            v-if="viewMode === 'list'" 
                            :projects="filteredProjects" 
                            :active-entry="activeEntry"
                            @edit="openEditModal"
                            @delete="deleteProject"
                        />
                        <ProjectCalendar 
                            v-else 
                            :projects="filteredProjects" 
                        />
                    </transition>
                </div>
            </div>
        </div>

        <!-- Modal CRUD -->
        <el-dialog v-model="showModal" :title="isEditing ? 'Editar Proyecto' : 'Nuevo Proyecto'" width="600px">
            <el-form :model="form" label-position="top">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Nombre del Proyecto" :error="form.errors.name"><el-input v-model="form.name" /></el-form-item>
                    <el-form-item label="Cliente" :error="form.errors.client"><el-input v-model="form.client" /></el-form-item>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Fecha Inicio" :error="form.errors.start_date"><el-date-picker v-model="form.start_date" type="date" format="DD/MM/YYYY" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
                    <el-form-item label="Fecha Estimada Fin" :error="form.errors.estimated_end_date"><el-date-picker v-model="form.estimated_end_date" type="date" format="DD/MM/YYYY" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item label="Horas Presupuestadas" :error="form.errors.budgeted_hours"><el-input-number v-model="form.budgeted_hours" :min="0" :precision="1" style="width: 100%" /></el-form-item>
                    <el-form-item v-if="isEditing" label="Estado" :error="form.errors.status"><el-select v-model="form.status" style="width: 100%"><el-option label="Activo" value="active" /><el-option label="Terminado" value="finished" /></el-select></el-form-item>
                </div>
                <el-form-item label="Descripción" :error="form.errors.description"><el-input v-model="form.description" type="textarea" :rows="3" /></el-form-item>
            </el-form>
            <template #footer>
                <div class="dialog-footer">
                    <el-button @click="showModal = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitForm" :loading="form.processing">{{ isEditing ? 'Actualizar' : 'Guardar' }}</el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>