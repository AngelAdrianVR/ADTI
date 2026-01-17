<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { format, addDays, parseISO, isValid } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    payroll: Object,
    payrollUsers: Array,
});

const templateView = ref(true);

const printScreen = () => {
    window.print();
};

// --- Helpers de Fecha ---
// Ajuste de zona horaria simple si las fechas vienen en UTC y se quieren mostrar localmente sin desfase
const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Intentar parsear la fecha de forma segura
    try {
        // Si ya es un objeto Date
        if (dateString instanceof Date) {
             return isValid(dateString) ? format(dateString, 'dd MMM, yyyy', { locale: es }) : '-';
        }
        
        // Si es string YYYY-MM-DD, le agregamos la hora para evitar desfases de zona horaria al crear el objeto Date
        // O usamos parseISO directamente
        const date = parseISO(dateString);
        if (!isValid(date)) return '-';
        
        return format(date, 'dd MMM, yyyy', { locale: es });
    } catch (e) {
        console.error("Error formateando fecha:", dateString, e);
        return '-';
    }
};

const formatDateToYear = (dateString) => {
    if (!dateString) return '-';
    try {
        const date = parseISO(dateString);
        return isValid(date) ? format(date, 'yyyy', { locale: es }) : '-';
    } catch (e) {
        return '-';
    }
};

const getEndPeriod = (start) => {
    if (!start) return '-';
    try {
        const date = parseISO(start);
        if (!isValid(date)) return '-';
        const end = addDays(date, 13); // 14 días total
        return format(end, 'dd MMM, yyyy', { locale: es });
    } catch (e) {
        return '-';
    }
};

// --- Lógica de Negocio ---

const getDaysToPay = (payrollUser) => {
    // Cuenta días que NO tienen una incidencia que descuente pago (Faltas, Permisos sin goce, Incapacidad sin goce)
    // Asume que si 'incidence' es null o string vacío, es un día pagable.
    // También Vacaciones, Festivos y Domingos suelen pagarse.
    
    // Lista de incidencias que NO pagan
    const unpaidIncidences = [
        'Falta injustificada', 
        'Permiso sin goce', 
        'Incapacidad' // Depende de la regla de negocio, a veces la paga el seguro
    ];

    return payrollUser.incidences.filter(day => {
        // Si no hay incidencia registrada, es día normal/pagable
        if (!day.incidence) return true;
        
        // Si hay incidencia, verificar si está en la lista de NO pagadas
        return !unpaidIncidences.includes(day.incidence);
    }).length;
};

const getDaysWithIncidence = (payrollUser) => {
    // Filtra incidencias relevantes para mostrar en el reporte
    const ignored = ['Descanso', 'Domingo', 'Día normal']; 
    
    return payrollUser.incidences.filter(i => 
        i.incidence && 
        !ignored.includes(i.incidence)
    );
};

const getDaysWithExtraTime = (payrollUser) => {
    return payrollUser.incidences.filter(i => i.extra_hours > 0 || i.extra_minutes > 0);
};

const formatExtraTime = (hours, minutes) => {
    if (!hours && !minutes) return '-';
    return `${hours || 0}h ${minutes || 0}m`;
};

// Ocultar botón después de imprimir (opcional, manejado por CSS @media print mejor)
const handleAfterPrint = () => {
    // Lógica post-impresión si fuera necesaria
};

onMounted(() => {
    window.addEventListener('afterprint', handleAfterPrint);
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 print:bg-white font-sans text-gray-800">
        <Head :title="`Pre-nómina Catorcena ${payroll.biweekly} - ${formatDateToYear(payroll.start_date)}`" />

        <!-- Header -->
        <header class="bg-white shadow-sm print:shadow-none print:border-b print:border-gray-200 py-6 px-8">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <ApplicationMark class="w-16 h-auto" />
                    <div>
                        <h1 class="text-2xl font-bold uppercase tracking-wide text-[#0B3B51]">Reporte de Pre-nómina</h1>
                        <p class="text-sm text-gray-500">
                            Catorcena {{ payroll.biweekly }} | 
                            <span class="font-medium text-gray-700">{{ formatDate(payroll.start_date) }}</span> al 
                            <span class="font-medium text-gray-700">{{ getEndPeriod(payroll.start_date) }}</span>
                        </p>
                    </div>
                </div>
                
                <!-- Botón de impresión (Oculto al imprimir) -->
                <div class="print:hidden">
                    <PrimaryButton @click="printScreen" class="!bg-[#0B3B51] hover:!bg-[#082a3a]">
                        <i class="fa-solid fa-print mr-2"></i> Imprimir / Guardar PDF
                    </PrimaryButton>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 print:p-0 print:w-full">
            
            <!-- Tabla -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden print:shadow-none print:border-none">
                <table class="w-full text-sm text-left">
                    <thead class="bg-[#0B3B51] text-white uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 font-semibold w-[5%] text-center">ID</th>
                            <th class="px-4 py-3 font-semibold w-[25%]">Colaborador</th>
                            <th class="px-4 py-3 font-semibold w-[10%] text-center">Días a Pagar</th>
                            <th class="px-4 py-3 font-semibold w-[40%]">Detalle de Incidencias</th>
                            <th class="px-4 py-3 font-semibold w-[20%]">Tiempo Extra</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 border-t border-gray-100">
                        <tr v-for="(item, index) in payrollUsers" :key="item.user.id" 
                            class="hover:bg-gray-50 transition-colors print:break-inside-avoid">
                            
                            <!-- ID -->
                            <td class="px-4 py-3 text-center text-gray-500 font-mono">
                                {{ item.user.id }}
                            </td>

                            <!-- Nombre -->
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-800">{{ item.user.name }}</div>
                                <div class="text-xs text-gray-500">{{ item.user.org_props?.department || 'General' }}</div>
                            </td>

                            <!-- Días -->
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-100">
                                    {{ getDaysToPay(item) }}
                                </span>
                            </td>

                            <!-- Incidencias -->
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <template v-if="getDaysWithIncidence(item).length > 0">
                                        <div v-for="(inc, idx) in getDaysWithIncidence(item)" :key="idx"
                                             class="text-xs px-2 py-1 rounded bg-amber-50 text-amber-700 border border-amber-100 flex items-center gap-1">
                                            <span class="font-semibold">{{ formatDate(inc.date).split(',')[0] }}:</span>
                                            <span>{{ inc.incidence }}</span>
                                        </div>
                                    </template>
                                    <span v-else class="text-gray-400 text-xs italic">Sin incidencias registradas</span>
                                </div>
                            </td>

                            <!-- Tiempo Extra -->
                            <td class="px-4 py-3">
                                <div class="space-y-1">
                                    <template v-if="getDaysWithExtraTime(item).length > 0">
                                        <div v-for="(extra, idx) in getDaysWithExtraTime(item)" :key="idx"
                                             class="text-xs flex justify-between items-center text-green-700">
                                            <span>{{ formatDate(extra.date).split(',')[0] }}:</span>
                                            <span class="font-mono font-bold bg-green-50 px-1 rounded">
                                                {{ formatExtraTime(extra.extra_hours, extra.extra_minutes) }}
                                            </span>
                                        </div>
                                    </template>
                                    <span v-else class="text-gray-400 text-xs italic">-</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer del Reporte -->
            <div class="mt-8 pt-8 border-t border-gray-200 text-center text-xs text-gray-400 print:mt-4">
                <p>Generado el {{ format(new Date(), "dd 'de' MMMM 'de' yyyy 'a las' HH:mm", { locale: es }) }}</p>
                <p class="mt-1 font-bold">ERP System</p>
            </div>

        </main>
    </div>
</template>

<style>
@media print {
    @page {
        margin: 0.5cm;
        size: landscape;
    }
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print\:hidden {
        display: none !important;
    }
    .print\:shadow-none {
        box-shadow: none !important;
    }
    .print\:border-none {
        border: none !important;
    }
}
</style>