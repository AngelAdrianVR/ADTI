<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    Clock, 
    VideoPause, 
    VideoPlay, 
    CircleCheck, 
    Plus, 
    Delete, 
    Edit, 
    Calendar, 
    List 
} from '@element-plus/icons-vue';
import { ElNotification, ElMessageBox, ElMessage } from 'element-plus';

// --- Props ---
const props = defineProps({
    projects: Array,
    activeEntry: Object, // Tarea activa del usuario actual
});

// --- Estado ---
const showModal = ref(false);
const isEditing = ref(false);
const filterStatus = ref('active'); 
const viewMode = ref('list'); 

// --- Lógica del Cronómetro ---
const localDuration = ref(0); // Tiempo TRABAJADO neto (se congela al pausar)
const pauseDuration = ref(0); // Tiempo de PAUSA actual (corre solo al pausar)
let timerInterval = null;

// Función Robusta para calcular el tiempo neto en el cliente
// Esto evita problemas si el servidor envía 0 o hay lag
const calculateNetDuration = (entry) => {
    const start = new Date(entry.start_time);
    const now = new Date();
    
    // 1. Tiempo total transcurrido desde el inicio (bruto)
    let totalSeconds = (now - start) / 1000;
    
    // 2. Restar el total de pausas pasadas (ya guardadas en DB)
    totalSeconds -= (entry.total_pause_seconds || 0);
    
    // 3. Si está PAUSADO actualmente, restar también el tiempo de la pausa actual
    // (Porque 'totalSeconds' sigue avanzando con el reloj, hay que descontar ese avance)
    if (entry.is_paused && entry.last_pause_start) {
        const pauseStart = new Date(entry.last_pause_start);
        const currentPause = (now - pauseStart) / 1000;
        totalSeconds -= currentPause;
    }
    
    return Math.max(0, Math.floor(totalSeconds));
};

// Watcher actualizado
watch(() => props.activeEntry, (newEntry) => {
    if (newEntry) {
        // Calculamos localmente para asegurar precisión visual y evitar que se resetee a 0
        localDuration.value = calculateNetDuration(newEntry);

        // Calcular tiempo de pausa actual (si aplica)
        if (newEntry.is_paused && newEntry.last_pause_start) {
            const start = new Date(newEntry.last_pause_start);
            const now = new Date();
            pauseDuration.value = Math.max(0, Math.floor((now - start) / 1000));
        } else {
            pauseDuration.value = 0;
        }
    } else {
        localDuration.value = 0;
        pauseDuration.value = 0;
    }
}, { immediate: true, deep: true });

// Loop del cronómetro (corre cada segundo)
onMounted(() => {
    timerInterval = setInterval(() => {
        if (props.activeEntry) {
            if (props.activeEntry.is_paused) {
                // Si está PAUSADO: Aumenta solo el contador de pausa
                pauseDuration.value++;
                // localDuration NO cambia, se mantiene "congelado" con el valor calculado
            } else {
                // Si está TRABAJANDO: Aumenta el tiempo trabajado
                localDuration.value++;
            }
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// Formateadores
const activeTimerDisplay = computed(() => formatDuration(localDuration.value));
const pauseTimerDisplay = computed(() => formatDuration(pauseDuration.value));

// --- Formulario ---
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

// --- Computed ---
const filteredProjects = computed(() => {
    if (filterStatus.value === 'all') return props.projects;
    return props.projects.filter(p => p.status === filterStatus.value);
});

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

// --- Helpers de Formato ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const options = { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
    let formatted = new Intl.DateTimeFormat('es-MX', options).format(date);
    return formatted.replace('p. m.', 'pm').replace('a. m.', 'am');
};

const formatDuration = (totalSeconds) => {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = Math.floor(totalSeconds % 60);
    const pad = (n) => n.toString().padStart(2, '0');
    return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
};

// --- Helpers para Calendario ---
const getProjectsForDate = (date) => {
    const checkDate = new Date(date);
    checkDate.setHours(0, 0, 0, 0);
    return filteredProjects.value.filter(project => {
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
const goToProjectDetail = (row, column, event) => router.get(route('projects.show', row.id));

const startWork = (project) => {
    router.post(route('projects.start', project.id), {}, {
        preserveScroll: true,
        onSuccess: () => ElNotification({ title: 'Trabajo iniciado', message: `Iniciaste: ${project.name}`, type: 'success' })
    });
};

const togglePause = (project) => {
    // Al hacer post, Inertia actualiza 'activeEntry'. El watcher detectará el cambio
    // de 'is_paused' y ajustará los contadores automáticamente.
    router.post(route('projects.pause', project.id), {}, { preserveScroll: true });
};

const stopWork = (project) => {
    router.post(route('projects.stop', project.id), {}, {
        preserveScroll: true,
        onSuccess: () => ElNotification({ title: 'Jornada terminada', type: 'info' })
    });
};

const openCreateModal = () => { isEditing.value = false; form.reset(); form.status = 'active'; showModal.value = true; };
const openEditModal = (project) => { isEditing.value = true; Object.assign(form, project); showModal.value = true; };

const submitForm = () => {
    const action = isEditing.value ? form.put(route('projects.update', form.id)) : form.post(route('projects.store'));
    // Nota: manejo simplificado, Inertia maneja callbacks onSuccess dentro de la llamada si se desea
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
</script>

<template>
    <AppLayout title="Proyectos">
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de Proyectos</h2>
                    <div class="flex items-center space-x-4">
                        <el-radio-group v-model="viewMode" size="small">
                            <el-radio-button label="list"><el-icon class="mr-1"><List /></el-icon> Lista</el-radio-button>
                            <el-radio-button label="calendar"><el-icon class="mr-1"><Calendar /></el-icon> Calendario</el-radio-button>
                        </el-radio-group>
                        <el-button type="primary" :icon="Plus" @click="openCreateModal">Nuevo Proyecto</el-button>
                    </div>
                </div>

                <!-- Banner Flotante: Tarea Activa -->
                <el-alert
                    v-if="activeEntry"
                    :type="activeEntry.is_paused ? 'warning' : 'success'"
                    :closable="false"
                    class="border shadow-sm transition-all duration-300"
                    :class="activeEntry.is_paused ? 'border-orange-300 bg-orange-50' : 'border-green-200'"
                >
                    <template #title>
                        <div class="flex flex-col md:flex-row items-center justify-between w-full gap-2">
                            
                            <!-- Área de Información y Contadores -->
                            <div class="flex items-center gap-6">
                                <!-- Contador TRABAJO (Verde/Normal) -->
                                <div class="flex flex-col">
                                    <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Tiempo Trabajado</span>
                                    <span class="text-2xl font-mono font-bold flex items-center" :class="activeEntry.is_paused ? 'text-gray-400' : 'text-green-700'">
                                        <el-icon class="mr-2" :class="{'animate-spin': !activeEntry.is_paused}"><Clock /></el-icon>
                                        {{ activeTimerDisplay }}
                                    </span>
                                </div>

                                <!-- Separador vertical si está pausado -->
                                <div v-if="activeEntry.is_paused" class="h-8 w-px bg-gray-300 hidden md:block"></div>

                                <!-- Contador PAUSA (Naranja) - Solo visible si pausado -->
                                <div v-if="activeEntry.is_paused" class="flex flex-col animate-pulse">
                                    <span class="text-xs uppercase font-bold text-orange-500 tracking-wider">En Pausa</span>
                                    <span class="text-2xl font-mono font-bold text-orange-600 flex items-center">
                                        <el-icon class="mr-2"><VideoPause /></el-icon>
                                        {{ pauseTimerDisplay }}
                                    </span>
                                </div>

                                <div class="flex flex-col justify-center ml-2 border-l pl-4 border-gray-200 h-full">
                                    <span class="text-xs text-gray-400">Proyecto:</span>
                                    <strong class="text-gray-700 text-sm truncate max-w-[200px]">{{ activeEntry.project?.name }}</strong>
                                </div>
                            </div>
                            
                            <!-- Botones de Acción -->
                            <div class="flex gap-2">
                                <el-button 
                                    size="default" 
                                    :type="activeEntry.is_paused ? 'success' : 'warning'"
                                    :icon="activeEntry.is_paused ? VideoPlay : VideoPause"
                                    @click.stop="togglePause(activeEntry.project)"
                                    round
                                >
                                    {{ activeEntry.is_paused ? 'Reanudar Trabajo' : 'Pausar' }}
                                </el-button>
                                
                                <el-button 
                                    size="default" 
                                    type="danger" 
                                    :icon="CircleCheck"
                                    @click.stop="stopWork(activeEntry.project)"
                                    round
                                    plain
                                >
                                    Terminar
                                </el-button>
                            </div>
                        </div>
                    </template>
                </el-alert>

                <!-- Resto de la vista (Tabla/Calendario) -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="mb-4 flex justify-between items-center">
                        <el-radio-group v-model="filterStatus" size="default">
                            <el-radio-button label="active">Activos</el-radio-button>
                            <el-radio-button label="finished">Terminados</el-radio-button>
                            <el-radio-button label="all">Todos</el-radio-button>
                        </el-radio-group>
                    </div>

                    <div v-if="viewMode === 'list'">
                        <el-table :data="filteredProjects" style="width: 100%" stripe @row-click="goToProjectDetail" row-class-name="cursor-pointer hover:bg-gray-50 transition">
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
                                        <el-progress :percentage="calculateProgress(scope.row)" :color="customColors" :format="() => ''" :stroke-width="8" />
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Trabajando Ahora" min-width="120">
                                <template #default="scope">
                                    <div v-if="scope.row.current_workers?.length" class="flex -space-x-2 overflow-hidden">
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
                                        <el-button type="primary" circle plain size="small" :icon="Edit" @click="openEditModal(scope.row)" />
                                        <el-button type="danger" circle plain size="small" :icon="Delete" @click="deleteProject(scope.row)" />
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <div v-else>
                        <el-calendar>
                            <template #date-cell="{ data }">
                                <div class="h-full w-full overflow-hidden">
                                    <p class="text-xs font-bold mb-1" :class="data.isSelected ? 'text-blue-500' : 'text-gray-700'">{{ data.day.split('-').slice(2).join('-') }}</p>
                                    <div class="space-y-1 overflow-y-auto max-h-[70px]">
                                        <div v-for="proj in getProjectsForDate(data.date)" :key="proj.id" class="text-[10px] bg-blue-100 text-blue-700 px-1 rounded truncate cursor-pointer hover:bg-blue-200" @click.stop="goToProjectDetail(proj)" :title="proj.name">
                                            {{ proj.name }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </el-calendar>
                    </div>
                </div>
            </div>
        </div>

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