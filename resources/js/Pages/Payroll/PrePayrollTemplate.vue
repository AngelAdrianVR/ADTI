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
const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        if (dateString instanceof Date) {
             return isValid(dateString) ? format(dateString, 'dd MMM, yyyy', { locale: es }) : '-';
        }
        const date = parseISO(dateString);
        if (!isValid(date)) return '-';
        return format(date, 'dd MMM, yyyy', { locale: es });
    } catch (e) {
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

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    try {
        const date = parseISO(dateString);
        return isValid(date) ? format(date, "dd MMM yyyy, HH:mm 'hrs'", { locale: es }) : '-';
    } catch (e) {
        return '-';
    }
};

const getEndPeriod = (start) => {
    if (!start) return '-';
    try {
        const date = parseISO(start);
        if (!isValid(date)) return '-';
        const end = addDays(date, 13);
        return format(end, 'dd MMM, yyyy', { locale: es });
    } catch (e) {
        return '-';
    }
};

// --- Lógica de Negocio (Helpers) ---

const getDaysToPay = (payrollUser) => {
    const unpaidIncidences = [
        'Falta injustificada', 
        'Permiso sin goce', 
        'Incapacidad'
    ];

    return payrollUser.incidences.filter(day => {
        if (!day.incidence) return true;
        return !unpaidIncidences.includes(day.incidence);
    }).length;
};

// Consolidado total de Horas Extras aprobadas en toda la catorcena
const getTotalExtraTime = (payrollUser) => {
    let totalMinutes = 0;
    
    payrollUser.incidences.forEach(day => {
        if (day.approved_at && (day.approved_extra_hours > 0 || day.approved_extra_minutes > 0)) {
            totalMinutes += (day.approved_extra_hours || 0) * 60 + (day.approved_extra_minutes || 0);
        }
    });
    
    if (totalMinutes === 0) return null;
    
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return `${h}h ${m}m`;
};

const formatExtraTime = (hours, minutes) => {
    if (!hours && !minutes) return '-';
    return `${hours || 0}h ${minutes || 0}m`;
};

const hasRealIncidence = (inc) => {
    if (!inc || !inc.incidence) return false;
    const ignored = ['Descanso', 'Domingo', 'Día normal'];
    return !ignored.includes(inc.incidence);
};

const hasExtraTime = (inc) => {
    if (!inc) return false;
    return inc.approved_at && (inc.approved_extra_hours > 0 || inc.approved_extra_minutes > 0);
};

// --- ORDENAMIENTO CRONOLÓGICO (Agrupado por Día) ---
const groupedByDate = computed(() => {
    if (!props.payroll || !props.payroll.start_date) return [];

    const startDate = parseISO(props.payroll.start_date);
    const days = [];

    // Iteramos los 14 días de la catorcena
    for (let i = 0; i < 14; i++) {
        const currentDate = addDays(startDate, i);
        const dateStr = format(currentDate, 'yyyy-MM-dd'); // Fecha estandarizada para comparación

        const usersOnThisDate = [];

        // Buscamos a los usuarios que tuvieron algo relevante en ESTA fecha
        props.payrollUsers.forEach(userItem => {
            const incidence = userItem.incidences?.find(inc => {
                if (!inc.date) return false;
                return inc.date.startsWith(dateStr) || inc.date.split('T')[0] === dateStr;
            });

            if (incidence) {
                const isRealIncidence = hasRealIncidence(incidence);
                const isExtraTime = hasExtraTime(incidence);
                const isComment = incidence.comment && incidence.comment.comments;

                // Solo agregamos al usuario en este día si tiene una incidencia, tiempo extra u observación
                if (isRealIncidence || isExtraTime || isComment) {
                    usersOnThisDate.push({
                        fullUserItem: userItem, // Para calcular consolidados (Días a pagar y Total TE)
                        user: userItem.user,
                        incidence: incidence
                    });
                }
            }
        });

        // Agregamos el día a la lista global, incluso si no hubo incidencias, 
        // para mantener el orden cronológico estricto
        days.push({
            dateObj: currentDate,
            dateLabel: format(currentDate, "EEEE, dd 'de' MMMM 'de' yyyy", { locale: es }),
            records: usersOnThisDate
        });
    }

    return days;
});

// Comentarios Generales (No atados a un día en específico)
const generalComments = computed(() => {
    const comments = [];
    props.payrollUsers.forEach(userItem => {
        if (userItem.comments && userItem.comments.comments) {
            comments.push({
                user: userItem.user,
                text: userItem.comments.comments
            });
        }
    });
    return comments;
});

// Ocultar botón después de imprimir
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
                
                <!-- Botón de impresión -->
                <div class="print:hidden">
                    <PrimaryButton @click="printScreen" class="!bg-[#0B3B51] hover:!bg-[#082a3a]">
                        <i class="fa-solid fa-print mr-2"></i> Imprimir / Guardar PDF
                    </PrimaryButton>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 print:p-0 print:w-full print:max-w-none">
            
            <!-- Tabla Agrupada Cronológicamente -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden print:shadow-none print:border-none">
                <table class="w-full text-sm text-left">
                    <thead class="bg-[#0B3B51] text-white uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="px-3 py-3 font-semibold w-[5%] text-center">ID</th>
                            <th class="px-3 py-3 font-semibold w-[25%]">Colaborador y Totales</th>
                            <th class="px-3 py-3 font-semibold w-[20%]">Incidencia del Día</th>
                            <th class="px-3 py-3 font-semibold w-[25%]">Tiempo Extra (Aprobado)</th>
                            <th class="px-3 py-3 font-semibold w-[25%]">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 border-t border-gray-100 text-xs">
                        
                        <!-- Iteración de cada Día de la Catorcena -->
                        <template v-for="(day, index) in groupedByDate" :key="index">
                            
                            <!-- Header del Día -->
                            <tr class="bg-indigo-50/80 border-y border-indigo-100 print:bg-gray-100 print:border-gray-300">
                                <td colspan="5" class="px-4 py-2 font-bold text-[#0B3B51] uppercase text-[11px] tracking-wider">
                                    <i class="fa-regular fa-calendar-days mr-2"></i>
                                    <span class="capitalize">{{ day.dateLabel }}</span>
                                </td>
                            </tr>

                            <!-- Estado Vacío (Si nadie tuvo incidencias ese día) -->
                            <tr v-if="day.records.length === 0">
                                <td colspan="5" class="px-4 py-3 text-center text-gray-400 italic text-[11px] bg-white border-b border-gray-50">
                                    Sin registros de incidencias o tiempo extra en este día.
                                </td>
                            </tr>

                            <!-- Registros de los Usuarios que tuvieron algo en ese día -->
                            <tr v-for="record in day.records" :key="record.user.id" 
                                class="hover:bg-gray-50 transition-colors bg-white border-b border-gray-50 print:break-inside-avoid">
                                
                                <!-- ID -->
                                <td class="px-3 py-3 text-center text-gray-500 font-mono align-top">
                                    {{ record.user.id }}
                                </td>

                                <!-- Colaborador & Totales Consolidados -->
                                <td class="px-3 py-3 align-top">
                                    <div class="font-bold text-gray-800 text-sm">{{ record.user.name }}</div>
                                    <div class="text-[10px] text-gray-500 uppercase tracking-wide">{{ record.user.org_props?.department || 'General' }}</div>
                                    
                                    <!-- Badges Consolidados de la Catorcena -->
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded font-bold bg-blue-50 text-blue-700 border border-blue-100 text-[9px]" title="Días totales a pagar en catorcena">
                                            Días a pagar: {{ getDaysToPay(record.fullUserItem) }}
                                        </span>
                                        <span v-if="getTotalExtraTime(record.fullUserItem)" class="inline-flex items-center justify-center px-1.5 py-0.5 rounded font-bold bg-indigo-50 text-indigo-700 border border-indigo-100 text-[9px]" title="Total acumulado en catorcena">
                                            Total T.E.: {{ getTotalExtraTime(record.fullUserItem) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Incidencia (Específica del Día) -->
                                <td class="px-3 py-3 align-top">
                                    <div v-if="hasRealIncidence(record.incidence)" class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-100 inline-block">
                                        <span class="font-bold">{{ record.incidence.incidence }}</span>
                                    </div>
                                    <span v-else class="text-gray-300 italic">-</span>
                                </td>

                                <!-- Tiempo Extra (Específico del Día) -->
                                <td class="px-3 py-3 align-top">
                                    <div v-if="hasExtraTime(record.incidence)" class="flex flex-col text-green-700 bg-green-50/50 px-1.5 py-1 rounded border border-green-100">
                                        <div class="flex justify-between items-center">
                                            <span class="font-mono font-bold text-[11px]">
                                                {{ formatExtraTime(record.incidence.approved_extra_hours, record.incidence.approved_extra_minutes) }}
                                            </span>
                                        </div>
                                        <!-- Datos de aprobación -->
                                        <div class="text-[9px] text-gray-500 mt-1 border-t border-green-100/60 pt-1 leading-tight">
                                            <span class="font-semibold text-gray-600">{{ record.incidence.approver?.name || 'ID: ' + record.incidence.approved_by }}</span><br>
                                            {{ formatDateTime(record.incidence.approved_at) }}
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-300 italic">-</span>
                                </td>

                                <!-- Observaciones / Comentarios (Específicos del Día) -->
                                <td class="px-3 py-3 text-gray-600 align-top">
                                    <div v-if="record.incidence.comment && record.incidence.comment.comments" class="leading-tight text-[11px] bg-gray-50 p-1.5 rounded border border-gray-100">
                                        <span class="italic">"{{ record.incidence.comment.comments }}"</span>
                                    </div>
                                    <span v-else class="text-gray-300 italic">-</span>
                                </td>
                                
                            </tr>
                        </template>

                    </tbody>
                </table>
            </div>

            <!-- Tabla de Comentarios Generales (Globales) -->
            <div v-if="generalComments.length > 0" class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden print:shadow-none print:border-none print:mt-4 print:break-inside-avoid">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 text-gray-700 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th colspan="2" class="px-3 py-2 font-semibold">
                                <i class="fa-solid fa-comments mr-2"></i> Observaciones Generales de la Catorcena
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <tr v-for="(comment, index) in generalComments" :key="index" class="hover:bg-gray-50">
                            <td class="px-3 py-2 w-[25%] align-top font-bold text-gray-800 border-r border-gray-100">
                                {{ comment.user.name }}
                            </td>
                            <td class="px-3 py-2 w-[75%] align-top text-gray-600 italic">
                                "{{ comment.text }}"
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
    .print\:max-w-none {
        max-width: none !important;
    }
}
</style>