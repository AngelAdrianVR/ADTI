<script setup>
import { Head } from '@inertiajs/vue3';
import { format, parseISO, isValid } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    period_type: String,
    rangeLabel: String,
    start_date: String,
    end_date: String,
    total_people: Number,
    total_days: Number,
    generated_at: String,
});

const printScreen = () => window.print();
const closeWindow = () => window.close();

const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        const date = parseISO(dateString);
        return isValid(date) ? format(date, 'EEEE dd \'de\' MMMM, yyyy', { locale: es }) : '-';
    } catch (e) {
        return '-';
    }
};

const formatTime = (time) => time || '-';

// La ubicación es "lat,lng" o un texto; se enlaza a Google Maps cuando es válida.
const locationLink = (loc) => {
    if (!loc) return null;
    if (loc.includes(',') && !loc.toLowerCase().includes('denegada') && !loc.toLowerCase().includes('no disponible')) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(loc)}`;
    }
    return null;
};

const locationText = (loc) => {
    if (!loc) return null;
    if (locationLink(loc)) return loc;
    return null;
};

const periodLabel = () => {
    const labels = {
        monthly: 'Mensual',
        bimonthly: 'Bimestral',
        quadrimester: 'Cuatrimestral',
        custom: 'Periodo personalizado',
    };
    return labels[props.period_type] || props.period_type || '';
};
</script>

<template>
    <Head title="Reporte de personal en trabajo externo" />

    <div class="min-h-screen bg-gray-100 py-6">
        <!-- Barra de acciones (se oculta al imprimir) -->
        <div class="max-w-6xl mx-auto mb-4 px-4 print:hidden flex items-center justify-between gap-3">
            <button
                @click="closeWindow"
                class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50"
            >
                <i class="fa-solid fa-arrow-left mr-2"></i> Cerrar
            </button>
            <h1 class="text-lg font-bold text-gray-800">Reporte de personal en trabajo externo</h1>
            <button
                @click="printScreen"
                class="px-4 py-2 rounded-lg bg-[#1676A2] text-white text-sm font-medium hover:bg-[#126288]"
            >
                <i class="fa-solid fa-print mr-2"></i> Imprimir
            </button>
        </div>

        <!-- Documento imprimible -->
        <div class="max-w-6xl mx-auto px-4">
            <div class="bg-white shadow-sm border border-gray-200 p-8">
                <!-- Encabezado -->
                <div class="flex items-start justify-between border-b-2 border-gray-800 pb-4 mb-4">
                    <div>
                        <h1 class="text-xl font-extrabold uppercase text-gray-900">ADTI</h1>
                        <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">Personal que trabaja fuera de las instalaciones</p>
                    </div>
                    <div class="text-right text-xs text-gray-600 space-y-1">
                        <p><span class="font-bold">Periodo:</span> {{ rangeLabel || `${start_date} → ${end_date}` }}</p>
                        <p><span class="font-bold">Tipo:</span> {{ periodLabel() }}</p>
                        <p><span class="font-bold">Generado:</span> {{ generated_at }}</p>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="grid grid-cols-3 gap-4 mb-5">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-gray-800">{{ total_people || 0 }}</p>
                        <p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Empleados</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-gray-800">{{ total_days || 0 }}</p>
                        <p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Días con trabajo externo</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-gray-800">{{ rows.length }}</p>
                        <p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Registros</p>
                    </div>
                </div>

                <!-- Tabla -->
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-900 text-white">
                            <th class="border border-gray-700 px-2 py-2 text-left font-semibold">Empleado</th>
                            <th class="border border-gray-700 px-2 py-2 text-left font-semibold">Fecha</th>
                            <th class="border border-gray-700 px-2 py-2 text-center font-semibold">Entrada</th>
                            <th class="border border-gray-700 px-2 py-2 text-center font-semibold">Salida</th>
                            <th class="border border-gray-700 px-2 py-2 text-center font-semibold">Ubicación registrada</th>
                            <th class="border border-gray-700 px-2 py-2 text-left font-semibold">Proyecto(s) vinculado(s)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="rows.length > 0">
                            <tr v-for="(row, index) in rows" :key="index" class="odd:bg-white even:bg-gray-50">
                                <td class="border border-gray-300 px-2 py-1.5 align-top">
                                    <span class="font-semibold text-gray-800">{{ row.user?.name }}</span>
                                    <span v-if="row.user?.department" class="block text-[10px] text-gray-400">{{ row.user.department }}</span>
                                </td>
                                <td class="border border-gray-300 px-2 py-1.5 align-top capitalize">{{ formatDate(row.date) }}</td>
                                <td class="border border-gray-300 px-2 py-1.5 text-center font-mono align-top">{{ formatTime(row.check_in) }}</td>
                                <td class="border border-gray-300 px-2 py-1.5 text-center font-mono align-top">{{ formatTime(row.check_out) }}</td>
                                <td class="border border-gray-300 px-2 py-1.5 align-top">
                                    <div v-if="row.check_in_location || row.check_out_location" class="space-y-0.5">
                                        <a
                                            v-if="locationLink(row.check_in_location)"
                                            :href="locationLink(row.check_in_location)"
                                            target="_blank"
                                            class="text-blue-600 underline"
                                        >
                                            <i class="fa-solid fa-location-dot mr-1"></i>Entrada: {{ locationText(row.check_in_location) }}
                                        </a>
                                        <a
                                            v-if="locationLink(row.check_out_location)"
                                            :href="locationLink(row.check_out_location)"
                                            target="_blank"
                                            class="text-blue-600 underline"
                                        >
                                            <i class="fa-solid fa-location-dot mr-1"></i>Salida: {{ locationText(row.check_out_location) }}
                                        </a>
                                        <span
                                            v-if="!locationLink(row.check_in_location) && !locationLink(row.check_out_location)"
                                            class="text-gray-400 italic"
                                        >
                                            {{ row.check_in_location || row.check_out_location || 'Sin ubicación' }}
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400 italic">Sin ubicación</span>
                                </td>
                                <td class="border border-gray-300 px-2 py-1.5 align-top">
                                    <ul class="space-y-0.5">
                                        <li v-for="p in row.projects" :key="p.id" class="flex flex-col">
                                            <span class="font-medium text-gray-800">
                                                <i class="fa-solid fa-diagram-project mr-1 text-gray-400"></i>{{ p.name }}
                                            </span>
                                            <span class="text-[10px] text-gray-400">
                                                {{ p.client || 'Sin cliente' }}<template v-if="p.department"> · {{ p.department }}</template>
                                            </span>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="6" class="border border-gray-300 px-4 py-8 text-center text-gray-400">
                                No se encontraron días con trabajo externo en el periodo seleccionado.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-8 pt-4 border-t border-gray-200 text-center text-[10px] text-gray-400">
                    Documento generado por el Sistema ADTI · {{ generated_at }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .min-h-screen { background: white !important; padding: 0 !important; }
    .max-w-6xl { max-width: 100% !important; padding: 0 !important; }
}
</style>

