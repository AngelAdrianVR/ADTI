<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import IncidencesTable from '@/Components/MyComponents/Payroll/IncidencesTable.vue';
import { format, parseISO, addDays } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    payrolls: {
        type: Array,
        default: () => []
    }
});

const page = usePage();
const currentUser = page.props.auth.user;

// --- Estado para Scroll Infinito ---
const limit = ref(5); // Empezar mostrando 5 nóminas para carga rápida
const loadMoreTrigger = ref(null);

// --- Computed: Nóminas visibles ---
const visiblePayrolls = computed(() => {
    return props.payrolls.slice(0, limit.value);
});

// --- Helpers de Fecha ---
const formatDate = (dateString) => {
    return format(parseISO(dateString), 'dd MMM yyyy', { locale: es });
};

const getPeriodRange = (startDate) => {
    const start = parseISO(startDate);
    const end = addDays(start, 13);
    return `${format(start, 'dd MMM', { locale: es })} - ${format(end, 'dd MMM yyyy', { locale: es })}`;
};

// --- Construir objeto PayrollUser artificialmente ---
// El componente IncidencesTable espera un objeto { user, incidences }.
// Nosotros tenemos 'incidences' dentro de cada objeto 'payroll' de la prop.
const getPayrollUserObject = (payrollItem) => {
    return {
        user: currentUser, // Usamos el usuario autenticado
        incidences: payrollItem.incidences
    };
};

// --- Observer para cargar más ---
onMounted(() => {
    const observer = new IntersectionObserver((entries) => {
        const entry = entries[0];
        if (entry.isIntersecting && limit.value < props.payrolls.length) {
            limit.value += 5; // Cargar 5 más al llegar al fondo
        }
    }, { rootMargin: '100px' });

    if (loadMoreTrigger.value) {
        observer.observe(loadMoreTrigger.value);
    }
});
</script>

<template>
    <AppLayout title="Mis Nóminas">
        <Head title="Mis Nóminas" />

        <div class="py-12 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Encabezado -->
                <div class="mb-8 px-4 sm:px-0 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Mis Nóminas</h1>
                        <p class="text-sm text-gray-500 mt-1">
                            Consulta tu historial de asistencias, incidencias y tiempo extra.
                        </p>
                    </div>

                    <!-- Stats Resumen (Opcional) -->
                    <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-100 flex gap-6 text-sm">
                        <div>
                            <span class="block text-xs text-gray-400 uppercase font-bold">Total Nóminas</span>
                            <span class="font-bold text-indigo-600 text-lg">{{ payrolls.length }}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-400 uppercase font-bold">Última Actividad</span>
                            <span class="font-bold text-gray-700">
                                {{ payrolls.length ? formatDate(payrolls[0].start_date) : '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Lista de Nóminas -->
                <div v-if="payrolls.length > 0" class="space-y-6 px-4 sm:px-0">
                    
                    <div 
                        v-for="payroll in visiblePayrolls" 
                        :key="payroll.id" 
                        class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden transition-all hover:shadow-md"
                    >
                        <!-- Header de la Tarjeta de Nómina -->
                        <div class="bg-gray-50/50 px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="bg-[#0B3B51] text-white text-xs font-bold px-2 py-1 rounded-md">
                                    Catorcena {{ payroll.biweekly }}
                                </span>
                                <span class="text-sm font-semibold text-gray-700">
                                    {{ getPeriodRange(payroll.start_date) }}
                                </span>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <span v-if="payroll.is_active" class="flex items-center gap-1 text-[10px] uppercase font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full border border-green-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                    En curso
                                </span>
                                <span v-else class="text-[10px] uppercase font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full border border-gray-200">
                                    Cerrada
                                </span>
                            </div>
                        </div>

                        <!-- Componente de Tabla de Incidencias (Reutilizado) -->
                        <div class="p-2 sm:p-4">
                            <!-- 
                                IMPORTANTE: 
                                Pasamos :payroll="{ ...payroll, is_active: false }" 
                                para FORZAR que el componente hijo oculte los botones de edición,
                                cumpliendo el requerimiento de 'solo lectura'.
                            -->
                            <IncidencesTable 
                                :payrollUser="getPayrollUserObject(payroll)"
                                :payroll="{ ...payroll, is_active: false }" 
                            />
                        </div>
                    </div>

                    <!-- Trigger para Scroll Infinito -->
                    <div ref="loadMoreTrigger" class="h-10 flex items-center justify-center text-gray-400 text-xs">
                        <span v-if="limit < payrolls.length">
                            <i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Cargando historial antiguo...
                        </span>
                        <span v-else class="italic opacity-50">Has llegado al final del historial</span>
                    </div>

                </div>

                <!-- Estado Vacío -->
                <div v-else class="flex flex-col items-center justify-center py-16 bg-white rounded-xl border border-dashed border-gray-300 mx-4 sm:mx-0">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                        <i class="fa-regular fa-folder-open text-3xl text-gray-300"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Sin historial de nóminas</h3>
                    <p class="text-gray-500 text-sm mt-1 text-center max-w-sm">
                        Aún no tienes registros de asistencia en ninguna nómina procesada.
                    </p>
                </div>

            </div>
        </div>
    </AppLayout>
</template>