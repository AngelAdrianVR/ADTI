<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { format, addDays, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    payrolls: Array
});

// State
const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

// Computed: Filtrado por búsqueda
const filteredPayrolls = computed(() => {
    if (!search.value) return props.payrolls;
    const lowerSearch = search.value.toLowerCase();
    
    return props.payrolls.filter(p => 
        p.id.toString().includes(lowerSearch) ||
        // Buscar por año o mes
        p.start_date.includes(lowerSearch)
    );
});

// Computed: Paginación (CORRECCIÓN CLAVE)
const paginatedPayrolls = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredPayrolls.value.slice(start, end);
});

// Helpers
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM, yyyy', { locale: es });
};

const getEndPeriod = (start) => {
    if (!start) return '-';
    const end = addDays(parseISO(start), 13);
    return format(end, 'dd MMM, yyyy', { locale: es });
};

const handleRowClick = (row) => {
    router.visit(route('payrolls.show', row.id));
};

const handlePageChange = (val) => {
    currentPage.value = val;
};

// Crear nueva nómina (Opcional, si tienes la ruta)
const createPayroll = () => {
    // Lógica para crear nómina si existe
    // router.visit(route('payrolls.create'));
};
</script>

<template>
    <AppLayout title="Nóminas">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Historial de Nóminas</h1>
                        <p class="text-xs text-gray-500 mt-1">Gestión y consulta de periodos catorcenales.</p>
                    </div>
                    
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <!-- Buscador -->
                        <div class="relative w-full sm:w-64">
                            <input 
                                v-model="search" 
                                type="text" 
                                placeholder="Buscar por ID o fecha..." 
                                class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2] text-sm shadow-sm"
                            >
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Nóminas -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <el-table 
                        :data="paginatedPayrolls" 
                        @row-click="handleRowClick"
                        style="width: 100%"
                        class="cursor-pointer"
                        :row-class-name="'hover:bg-gray-50 transition-colors'"
                    >
                        <el-table-column label="ID" width="100" align="center">
                            <template #default="scope">
                                <span class="font-mono text-gray-500 font-bold">#{{ scope.row.id }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Catorcena" width="150" align="center">
                            <template #default="scope">
                                <span class="bg-blue-50 text-[#1676A2] py-1 px-3 rounded-full text-xs font-bold border border-blue-100">
                                    No. {{ scope.row.biweekly }}
                                </span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Periodo">
                            <template #default="scope">
                                <div class="flex items-center gap-2 text-gray-700">
                                    <i class="fa-regular fa-calendar text-gray-400"></i>
                                    <span>{{ formatDate(scope.row.start_date) }}</span>
                                    <i class="fa-solid fa-arrow-right text-xs text-gray-300"></i>
                                    <span>{{ getEndPeriod(scope.row.start_date) }}</span>
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column label="Estatus" width="150" align="center">
                            <template #default="scope">
                                <div v-if="scope.row.is_active" class="flex items-center justify-center gap-1 text-green-600 bg-green-50 px-2 py-1 rounded text-xs font-bold">
                                    <span class="relative flex h-2 w-2">
                                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                    Activa
                                </div>
                                <div v-else class="text-gray-400 bg-gray-100 px-2 py-1 rounded text-xs font-medium border border-gray-200">
                                    Cerrada
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column align="right" width="80">
                            <template #default>
                                <i class="fa-solid fa-chevron-right text-gray-300"></i>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- Paginación -->
                    <div class="px-4 py-3 border-t border-gray-100 flex justify-end bg-gray-50">
                        <el-pagination 
                            layout="prev, pager, next" 
                            :total="filteredPayrolls.length" 
                            :page-size="itemsPerPage"
                            @current-change="handlePageChange"
                            background
                        />
                    </div>
                </div>

            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
/* Ajustes finos para la tabla de Element Plus */
:deep(.el-table__row) {
    cursor: pointer;
}
:deep(.el-table th.el-table__cell) {
    background-color: #f9fafb;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
}
</style>