<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardPanel1 from '@/Components/MyComponents/Dashboard/DashboardPanel1.vue';
import { 
    Briefcase, 
    Timer, 
    Box, 
    TrendCharts, 
    ArrowRight 
} from '@element-plus/icons-vue';

// Props recibidos del controlador
const props = defineProps({
    categories: Array,
    total_products: Number,
    active_projects_count: Number,
    total_hours_consumed: Number,
    active_projects: Array,
});

// Formatear fecha corta
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-MX', { 
        day: '2-digit', 
        month: 'short' 
    });
};

// Calcular progreso simple para la barra (seguridad por si faltan datos)
const calculateProgress = (project) => {
    if (!project.budgeted_hours || project.budgeted_hours == 0) return 0;
    // CORRECCIÓN: Usar 'consumed_hours' que viene del append en el controlador
    const p = (project.consumed_hours / project.budgeted_hours) * 100;
    return Math.min(100, Math.round(p));
};

// Determinar color de estado del progreso
const getProgressStatus = (percentage) => {
    if (percentage >= 100) return 'exception'; // Rojo si se pasa
    if (percentage > 85) return 'warning';     // Amarillo si está cerca
    return 'success';                          // Verde normal
};
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Panel de Control</h1>
                    <span class="text-sm text-gray-500 bg-white px-3 py-1 rounded-full shadow-sm border border-gray-200">
                        {{ new Date().toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                    </span>
                </div>

                <!-- Sección 1: Tarjetas de Métricas (Stats) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    
                    <!-- Card: Proyectos Activos -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center transition hover:shadow-md">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-lg mr-4">
                            <el-icon :size="24"><Briefcase /></el-icon>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Proyectos Activos</p>
                            <p class="text-2xl font-bold text-gray-800">{{ active_projects_count }}</p>
                        </div>
                    </div>

                    <!-- Card: Horas Consumidas -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center transition hover:shadow-md">
                        <div class="p-3 bg-indigo-50 text-indigo-600 rounded-lg mr-4">
                            <el-icon :size="24"><Timer /></el-icon>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Horas Registradas</p>
                            <p class="text-2xl font-bold text-gray-800">{{ total_hours_consumed }} h</p>
                        </div>
                    </div>

                    <!-- Card: Total Productos -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center transition hover:shadow-md">
                        <div class="p-3 bg-purple-50 text-purple-600 rounded-lg mr-4">
                            <el-icon :size="24"><Box /></el-icon>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Productos</p>
                            <p class="text-2xl font-bold text-gray-800">{{ total_products }}</p>
                        </div>
                    </div>

                    <!-- Card: Categorías (Ejemplo de métrica derivada) -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center transition hover:shadow-md">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-lg mr-4">
                            <el-icon :size="24"><TrendCharts /></el-icon>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Categorías</p>
                            <p class="text-2xl font-bold text-gray-800">{{ categories.length }}</p>
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Contenido Principal (Grid 2 columnas desiguales en LG) -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Columna Izquierda (Ancha): Proyectos Recientes -->
                    <div class="lg:col-span-2 space-y-8">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <h3 class="font-bold text-gray-700 flex items-center">
                                    <el-icon class="mr-2 text-blue-500"><Briefcase /></el-icon>
                                    Proyectos en Curso
                                </h3>
                                <Link :href="route('projects.index')" class="text-xs text-blue-600 font-semibold hover:text-blue-800 flex items-center">
                                    Ver todos <el-icon class="ml-1"><ArrowRight /></el-icon>
                                </Link>
                            </div>
                            
                            <div v-if="active_projects.length > 0">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left">
                                        <thead class="text-xs text-gray-500 uppercase bg-gray-50/50 border-b border-gray-100">
                                            <tr>
                                                <th class="px-6 py-3 font-medium">Proyecto / Cliente</th>
                                                <th class="px-6 py-3 font-medium">Fecha Inicio</th>
                                                <th class="px-6 py-3 font-medium">Progreso (Horas)</th>
                                                <th class="px-6 py-3 font-medium text-right">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="project in active_projects" :key="project.id" class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 font-medium text-gray-900">
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-gray-700">{{ project.name }}</span>
                                                        <span class="text-xs text-gray-400">{{ project.client }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-gray-500">
                                                    {{ formatDate(project.start_date) }}
                                                </td>
                                                <td class="px-6 py-4 w-1/3">
                                                    <!-- CORRECCIÓN: Usar 'consumed_hours' -->
                                                    <div class="flex items-center justify-between text-xs mb-1 text-gray-500">
                                                        <span>{{ project.consumed_hours }}h</span>
                                                        <span>{{ project.budgeted_hours }}h</span>
                                                    </div>
                                                    <el-progress 
                                                        :percentage="calculateProgress(project)" 
                                                        :status="getProgressStatus(calculateProgress(project))" 
                                                        :stroke-width="6" 
                                                        :show-text="false"
                                                    />
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <Link :href="route('projects.show', project.id)" class="text-indigo-600 hover:text-indigo-900 font-medium text-xs">
                                                        Detalles
                                                    </Link>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div v-else class="p-8 text-center text-gray-400">
                                No hay proyectos activos en este momento.
                            </div>
                        </div>

                        <!-- Panel existente (Productos) integrado -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden p-6">
                            <h3 class="font-bold text-gray-700 mb-4 flex items-center">
                                <el-icon class="mr-2 text-purple-500"><Box /></el-icon>
                                Estadísticas de Catálogo
                            </h3>
                            <!-- Componente original pasado por props -->
                            <DashboardPanel1 :categories="categories" :total_products="total_products" />
                        </div>
                    </div>

                    <!-- Columna Derecha (Estrecha): Accesos Rápidos / Resumen -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- Tarjeta Informativa / Acceso Rápido -->
                        <div class="bg-gradient-to-br from-indigo-600 to-purple-700 rounded-xl p-6 text-white shadow-lg">
                            <h3 class="font-bold text-lg mb-2">Gestionar Proyectos</h3>
                            <p class="text-indigo-100 text-sm mb-4">Crea nuevos proyectos y asigna presupuestos desde aquí.</p>
                            <Link :href="route('projects.index')" class="inline-block bg-white text-indigo-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-50 transition shadow-sm">
                                + Nuevo proyecto
                            </Link>
                        </div>

                        <!-- Lista simple de categorías (Resumen rápido) -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                            <h4 class="text-gray-500 text-xs font-bold uppercase mb-4 tracking-wider">Resumen Categorías</h4>
                            <ul class="space-y-3">
                                <li v-for="cat in categories.slice(0, 5)" :key="cat.id" class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">{{ cat.name }}</span>
                                    <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs font-semibold">
                                        {{ cat.subcategories?.reduce((acc, sub) => acc + sub.products.length, 0) || 0 }} prods
                                    </span>
                                </li>
                            </ul>
                            <div v-if="categories.length > 5" class="mt-4 pt-3 border-t border-gray-100 text-center">
                                <span class="text-xs text-gray-400 italic">y {{ categories.length - 5 }} más...</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>