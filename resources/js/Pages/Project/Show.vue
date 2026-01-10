<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Clock, User, ArrowLeft, Timer, Calendar } from '@element-plus/icons-vue';

// --- Props ---
const props = defineProps({
    project: Object,
    members: Array, // Array estructurado desde el controlador
});

// --- Computed & Helpers ---

// Porcentaje de progreso
const progressPercentage = computed(() => {
    if (!props.project.budgeted_hours || props.project.budgeted_hours == 0) return 0;
    const percent = (props.project.real_consumed_hours / props.project.budgeted_hours) * 100;
    return Math.min(100, Math.round(percent));
});

// Colores de barra de progreso
const customColors = [
    { color: '#67c23a', percentage: 70 },
    { color: '#e6a23c', percentage: 90 },
    { color: '#f56c6c', percentage: 100 },
];

// Formateador de Duración (Segundos -> HH:MM:SS)
const formatDuration = (seconds) => {
    if (!seconds) return '00:00:00';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    const pad = (n) => n.toString().padStart(2, '0');
    return `${pad(h)}:${pad(m)}:${pad(s)}`;
};

// Formateador de Horas Decimales (para totales grandes)
const formatDecimalHours = (seconds) => {
    return (seconds / 3600).toFixed(2) + ' h';
};

// Formateador de Fechas (Solo fecha completa)
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    
    const options = { 
        day: 'numeric', 
        month: 'long', 
        year: 'numeric' 
    };
    
    return new Intl.DateTimeFormat('es-MX', options).format(date);
};

// NUEVO: Formateador estricto para la HORA (evita el error del split)
const formatTime = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleTimeString('es-MX', { 
        hour: '2-digit', 
        minute: '2-digit', 
        hour12: true 
    }).replace('p. m.', 'pm').replace('a. m.', 'am');
};

</script>

<template>
    <AppLayout :title="`Proyecto: ${project.name}`">
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Botón Regresar -->
                <div class="flex items-center">
                    <Link :href="route('projects.index')" class="text-gray-500 hover:text-gray-700 flex items-center transition">
                        <el-icon class="mr-1"><ArrowLeft /></el-icon>
                        Volver al listado
                    </Link>
                </div>

                <!-- Tarjeta Principal: Detalles del Proyecto -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 border-t-4 border-indigo-500">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">{{ project.name }}</h1>
                            <p class="text-gray-500 text-sm mt-1">Cliente: <span class="font-semibold text-gray-700">{{ project.client }}</span></p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <el-tag :type="project.status === 'active' ? 'success' : 'info'" effect="dark" size="large">
                                {{ project.status === 'active' ? 'En Progreso' : 'Terminado' }}
                            </el-tag>
                        </div>
                    </div>

                    <!-- Estadísticas Rápidas -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <!-- Fecha Inicio -->
                        <div class="bg-gray-50 p-4 rounded-lg flex items-center space-x-4">
                            <div class="bg-blue-100 p-3 rounded-full text-blue-600">
                                <el-icon size="20"><Calendar /></el-icon>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold">Fecha Inicio</p>
                                <p class="text-sm font-semibold text-gray-800">{{ formatDate(project.start_date) }}</p>
                            </div>
                        </div>

                        <!-- Fecha Fin Estimada -->
                        <div class="bg-gray-50 p-4 rounded-lg flex items-center space-x-4">
                            <div class="bg-purple-100 p-3 rounded-full text-purple-600">
                                <el-icon size="20"><Calendar /></el-icon>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold">Fecha Fin Est.</p>
                                <p class="text-sm font-semibold text-gray-800">{{ formatDate(project.estimated_end_date) }}</p>
                            </div>
                        </div>

                        <!-- Horas Presupuestadas -->
                        <div class="bg-gray-50 p-4 rounded-lg flex items-center space-x-4">
                            <div class="bg-green-100 p-3 rounded-full text-green-600">
                                <el-icon size="20"><Clock /></el-icon>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold">Presupuestado</p>
                                <p class="text-sm font-semibold text-gray-800">{{ project.budgeted_hours }} Horas</p>
                            </div>
                        </div>

                        <!-- Horas Reales -->
                        <div class="bg-gray-50 p-4 rounded-lg flex items-center space-x-4 border border-indigo-100">
                            <div class="bg-indigo-100 p-3 rounded-full text-indigo-600">
                                <el-icon size="20"><Timer /></el-icon>
                            </div>
                            <div>
                                <p class="text-xs text-indigo-500 uppercase font-bold">Total Acumulado</p>
                                <p class="text-lg font-bold text-indigo-700">{{ formatDuration(project.real_total_seconds) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de Progreso -->
                    <div class="mb-2">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-semibold text-gray-600">Consumo de Presupuesto</span>
                            <span class="text-gray-500">{{ project.real_consumed_hours }} / {{ project.budgeted_hours }} h</span>
                        </div>
                        <el-progress 
                            :text-inside="true" 
                            :stroke-width="20" 
                            :percentage="progressPercentage" 
                            :color="customColors"
                            class="mb-2"
                        />
                        <p v-if="project.description" class="text-sm text-gray-500 mt-4 bg-gray-50 p-3 rounded border border-gray-100 italic">
                            "{{ project.description }}"
                        </p>
                    </div>
                </div>

                <!-- Desglose por Usuarios (Tabla Expandible) -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <el-icon class="mr-2"><User /></el-icon>
                        Colaboradores y Tiempos
                    </h3>

                    <el-table :data="members" style="width: 100%" stripe>
                        
                        <!-- Columna: Usuario -->
                        <el-table-column label="Colaborador" min-width="200">
                            <template #default="scope">
                                <div class="flex items-center space-x-3">
                                    <img :src="scope.row.user.profile_photo_url" class="h-10 w-10 rounded-full object-cover border border-gray-200" />
                                    <div>
                                        <p class="font-semibold text-gray-800">{{ scope.row.user.name }}</p>
                                        <p class="text-xs text-gray-500">{{ scope.row.user.email }}</p>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Columna: Total Horas -->
                        <el-table-column label="Total Trabajado" width="180">
                            <template #default="scope">
                                <div class="font-bold text-gray-700">
                                    {{ formatDuration(scope.row.total_seconds) }}
                                </div>
                                <div class="text-xs text-gray-400">
                                    ({{ formatDecimalHours(scope.row.total_seconds) }})
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Expansión: Desglose Diario -->
                        <el-table-column type="expand">
                            <template #default="props">
                                <div class="p-4 bg-gray-50 rounded-lg ml-10">
                                    <p class="text-xs font-bold text-gray-500 uppercase mb-3">Historial Diario - {{ props.row.user.name }}</p>
                                    
                                    <!-- Tabla Anidada: Días -->
                                    <el-table :data="Object.values(props.row.daily_breakdown)" border size="small">
                                        <el-table-column label="Fecha">
                                            <template #default="dayScope">
                                                <span class="font-semibold">{{ formatDate(dayScope.row.date) }}</span>
                                            </template>
                                        </el-table-column>
                                        
                                        <el-table-column label="Tiempo Trabajado">
                                            <template #default="dayScope">
                                                <span class="text-green-600 font-bold">{{ formatDuration(dayScope.row.total_time) }}</span>
                                            </template>
                                        </el-table-column>
                                        
                                        <el-table-column label="Tiempo en Pausa">
                                            <template #default="dayScope">
                                                <span class="text-orange-500">{{ formatDuration(dayScope.row.total_pause) }}</span>
                                            </template>
                                        </el-table-column>

                                        <!-- Detalle fino de sesiones (Tooltip o Popover) -->
                                        <el-table-column label="Sesiones" width="100" align="center">
                                            <template #default="dayScope">
                                                <el-popover placement="left" :width="300" trigger="hover">
                                                    <template #reference>
                                                        <el-tag size="small" class="cursor-pointer">{{ dayScope.row.entries.length }} registros</el-tag>
                                                    </template>
                                                    <div>
                                                        <!-- Cabecera con la fecha completa -->
                                                        <h4 class="font-bold text-xs mb-2 text-gray-600">Detalle de sesiones ({{ formatDate(dayScope.row.date) }})</h4>
                                                        <ul class="space-y-2">
                                                            <li v-for="entry in dayScope.row.entries" :key="entry.id" class="text-xs border-b pb-1 last:border-0">
                                                                <div class="flex justify-between">
                                                                    <span>Inicio:</span>
                                                                    <!-- AQUI SE CORRIGE: Usamos formatTime directo -->
                                                                    <span class="font-mono text-gray-700">{{ formatTime(entry.start_time) }}</span>
                                                                </div>
                                                                <div class="flex justify-between text-gray-500">
                                                                    <span>Fin:</span>
                                                                    <span v-if="entry.end_time" class="font-mono">{{ formatTime(entry.end_time) }}</span>
                                                                    <span v-else class="text-green-500 font-bold">En curso...</span>
                                                                </div>
                                                                <div class="flex justify-between mt-1 text-gray-700 font-semibold">
                                                                    <span>Duración:</span>
                                                                    <span>{{ formatDuration(entry.calculated_duration) }}</span>
                                                                </div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </el-popover>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

            </div>
        </div>
    </AppLayout>
</template>