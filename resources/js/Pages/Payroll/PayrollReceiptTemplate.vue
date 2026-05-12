<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { format, addDays, parseISO, isValid } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    payroll: Object,
    payrollUsers: Array,
});

const printScreen = () => {
    window.print();
};

// --- Helpers de Fecha ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        const date = dateString instanceof Date ? dateString : parseISO(dateString);
        return isValid(date) ? format(date, 'dd MMM, yyyy', { locale: es }) : '-';
    } catch (e) {
        return '-';
    }
};

const formatShortDate = (dateString) => {
    if (!dateString) return '-';
    try {
        const date = dateString instanceof Date ? dateString : parseISO(dateString);
        return isValid(date) ? format(date, 'E dd/MM', { locale: es }) : '-';
    } catch (e) {
        return '-';
    }
};

const getEndPeriod = (start) => {
    if (!start) return '-';
    try {
        const date = parseISO(start);
        return isValid(date) ? format(addDays(date, 13), 'dd MMM, yyyy', { locale: es }) : '-';
    } catch (e) {
        return '-';
    }
};

// Generar los 14 días estrictos de la catorcena
const get14DaysRecord = (userItem) => {
    if (!props.payroll || !props.payroll.start_date) return [];

    const startDate = parseISO(props.payroll.start_date);
    const days = [];

    for (let i = 0; i < 14; i++) {
        const currentDate = addDays(startDate, i);
        const dateStr = format(currentDate, 'yyyy-MM-dd'); 

        const incidence = userItem.incidences?.find(inc => {
            if (!inc.date) return false;
            return inc.date.startsWith(dateStr) || inc.date.split('T')[0] === dateStr;
        });

        // Verificamos si hubo tiempo extra aprobado
        const hasExtraTime = incidence?.approved_at && (incidence.approved_extra_hours > 0 || incidence.approved_extra_minutes > 0);

        days.push({
            dateObj: currentDate,
            label: formatShortDate(currentDate),
            checkIn: incidence?.check_in?.substring(0, 5) || '-',
            checkOut: incidence?.check_out?.substring(0, 5) || '-',
            incidenceText: incidence?.incidence && incidence.incidence !== 'Día normal' ? incidence.incidence : '',
            extraTime: hasExtraTime ? `${incidence.approved_extra_hours || 0}h ${incidence.approved_extra_minutes || 0}m` : '',
            isAbsent: incidence?.incidence === 'Falta injustificada',
        });
    }

    return days;
};

onMounted(() => {
    window.addEventListener('afterprint', () => {});
});
</script>

<template>
    <div class="min-h-screen bg-gray-100 print:bg-white font-sans text-gray-800">
        <Head :title="`Recibos Catorcena ${payroll.biweekly}`" />

        <div class="print:hidden p-4 bg-white shadow-sm flex justify-between items-center mb-6">
            <div>
                <h1 class="font-bold text-lg text-teal-700">Recibos de Catorcena {{ payroll.biweekly }}</h1>
                <p class="text-xs text-gray-500">Formato ultra compacto a 2 columnas.</p>
            </div>
            <!-- Uso de botón nativo HTML para garantizar renderizado visual -->
            <button @click="printScreen" class="!bg-teal-600 hover:!bg-teal-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-all flex items-center">
                <i class="fa-solid fa-print mr-2"></i> Imprimir recibos
            </button>
        </div>

        <main class="w-full max-w-5xl mx-auto print:max-w-none print:w-full print:p-0">
            <div 
                v-for="(item, index) in payrollUsers" 
                :key="item.user.id" 
                class="bg-white p-4 mb-4 print:mb-0 print:py-1.5 print:border-b print:border-gray-400 receipt-block shadow-sm print:shadow-none"
            >
                <!-- Encabezado del Recibo -->
                <div class="flex justify-between items-end mb-2 print:mb-1 border-b border-gray-800 pb-1 print:pb-0.5">
                    <div>
                        <h2 class="font-bold text-sm print:text-[10px] uppercase text-gray-800 tracking-wide leading-tight">Acuse de Catorcena {{ payroll.biweekly }}</h2>
                        <p class="text-[10px] print:text-[8px] text-gray-500 font-mono mt-0.5">Periodo: {{ formatDate(payroll.start_date) }} al {{ getEndPeriod(payroll.start_date) }}</p>
                    </div>
                    <div class="text-right">
                        <h3 class="font-bold text-sm print:text-[10px] text-gray-800 leading-tight">{{ item.user.name }}</h3>
                        <p class="text-[10px] print:text-[8px] text-gray-500 uppercase mt-0.5">ID: {{ item.user.id }} | {{ item.user.org_props?.department || 'General' }}</p>
                    </div>
                </div>

                <!-- División a Dos Columnas (Semana 1 y Semana 2) -->
                <div class="grid grid-cols-2 gap-4 print:gap-2">
                    
                    <!-- SEMANA 1 -->
                    <table class="w-full text-left text-[9px] print:text-[7.5px] border border-gray-300 print:border-gray-400">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[15%]">Día</th>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[15%] text-center border-l">Ent</th>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[15%] text-center border-l">Sal</th>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[35%] border-l">Incidencia</th>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[20%] text-center border-l">Extra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in get14DaysRecord(item).slice(0, 7)" :key="day.dateObj" class="border-b border-gray-200 print:border-gray-300 last:border-0 hover:bg-gray-50">
                                <td class="px-1 py-0.5 print:py-[1px] uppercase tracking-wide" :class="{'text-red-600 font-bold': day.isAbsent}">{{ day.label }}</td>
                                <td class="px-1 py-0.5 print:py-[1px] text-center font-mono border-l border-gray-200 print:border-gray-300" :class="{'text-red-600': day.isAbsent}">{{ day.checkIn }}</td>
                                <td class="px-1 py-0.5 print:py-[1px] text-center font-mono border-l border-gray-200 print:border-gray-300" :class="{'text-red-600': day.isAbsent}">{{ day.checkOut }}</td>
                                <td class="px-1 py-0.5 print:py-[1px] text-gray-700 truncate max-w-[80px] border-l border-gray-200 print:border-gray-300" :class="{'text-red-600 font-bold': day.isAbsent}">{{ day.incidenceText }}</td>
                                <td class="px-1 py-0.5 print:py-[1px] text-center font-mono text-gray-800 border-l border-gray-200 print:border-gray-300">{{ day.extraTime }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- SEMANA 2 -->
                    <table class="w-full text-left text-[9px] print:text-[7.5px] border border-gray-300 print:border-gray-400">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[15%]">Día</th>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[15%] text-center border-l">Ent</th>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[15%] text-center border-l">Sal</th>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[35%] border-l">Incidencia</th>
                                <th class="px-1 py-0.5 print:py-[1px] border-b border-gray-300 print:border-gray-400 w-[20%] text-center border-l">Extra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in get14DaysRecord(item).slice(7, 14)" :key="day.dateObj" class="border-b border-gray-200 print:border-gray-300 last:border-0 hover:bg-gray-50">
                                <td class="px-1 py-0.5 print:py-[1px] uppercase tracking-wide" :class="{'text-red-600 font-bold': day.isAbsent}">{{ day.label }}</td>
                                <td class="px-1 py-0.5 print:py-[1px] text-center font-mono border-l border-gray-200 print:border-gray-300" :class="{'text-red-600': day.isAbsent}">{{ day.checkIn }}</td>
                                <td class="px-1 py-0.5 print:py-[1px] text-center font-mono border-l border-gray-200 print:border-gray-300" :class="{'text-red-600': day.isAbsent}">{{ day.checkOut }}</td>
                                <td class="px-1 py-0.5 print:py-[1px] text-gray-700 truncate max-w-[80px] border-l border-gray-200 print:border-gray-300" :class="{'text-red-600 font-bold': day.isAbsent}">{{ day.incidenceText }}</td>
                                <td class="px-1 py-0.5 print:py-[1px] text-center font-mono text-gray-800 border-l border-gray-200 print:border-gray-300">{{ day.extraTime }}</td>
                            </tr>
                        </tbody>
                    </table>

                </div>

                <!-- Footer (Firma) -->
                <div class="mt-4 pt-1 print:mt-1.5 flex justify-end items-end">
                    <div class="w-48 text-center border-t border-gray-800 pt-0.5 relative">
                        <p class="text-[8px] print:text-[7px] uppercase font-bold text-gray-600 tracking-wider">Firma de conformidad</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style>
/* Hack estricto de CSS para que Google Chrome/Edge NUNCA corten el div a la mitad */
.receipt-block {
    page-break-inside: avoid;
    break-inside: avoid;
}

@media print {
    @page {
        margin: 0.5cm; /* Margen muy delgado para aprovechar el espacio en carta/A4 */
        size: portrait;
    }
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Forzar al contenedor a comportarse como tabla anula el corte en Webkit */
    .receipt-block {
        display: table;
        width: 100%;
    }

    .print\:hidden {
        display: none !important;
    }
    .print\:shadow-none {
        box-shadow: none !important;
    }
}
</style>