<script setup>
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    groups: { type: Array, required: true },
    totalRecords: { type: Number, default: 0 },
    isProcessing: { type: Boolean, default: false },
    processingRow: { type: String, default: null },
    processingType: { type: String, default: null },
    activeFiltersLabel: { type: String, default: null },
});

const emit = defineEmits(['revert-single']);

const formatDate = (dateStr) => format(parseISO(dateStr), "EEEE, dd 'de' MMM", { locale: es });
const formatTime = (timeStr) => timeStr ? timeStr.substring(0, 5) : '--:--';
const formatTotalTime = (mins) => {
    if (!mins) return '0h 0m';
    return `${Math.floor(mins / 60)}h ${mins % 60}m`;
};
</script>

<template>
    <div v-if="groups.length === 0" class="py-12 text-center text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-300 mt-2">
        <i class="fa-regular fa-folder-open text-4xl text-gray-300 mb-3 block"></i>
        <p class="font-medium">
            {{ activeFiltersLabel ? 'No hay historial con los filtros aplicados.' : 'No hay historial de resoluciones en esta nómina.' }}
        </p>
    </div>

    <div v-else class="mt-2 border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-gray-100 text-gray-600 uppercase text-[10px] tracking-wider border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 w-[18%]">Fecha</th>
                    <th class="px-4 py-3 w-[12%] text-center">Estatus</th>
                    <th class="px-4 py-3 w-[12%] text-center">Otorgado</th>
                    <th class="px-4 py-3 w-[38%]">Comentarios / aprobado por</th>
                    <th class="px-4 py-3 w-[10%] text-center">Niveles</th>
                    <th class="px-4 py-3 w-[10%] text-center">Acción</th>
                </tr>
            </thead>

            <template v-for="group in groups" :key="group.user.id">
                <tbody class="divide-y divide-gray-200 border-t-[3px] border-gray-300">
                    <tr class="bg-gray-100 border-b border-gray-200">
                        <td colspan="6" class="px-4 py-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <img :src="group.user.profile_photo_url" class="h-8 w-8 rounded-full border border-gray-300 object-cover">
                                    <div>
                                        <p class="font-bold text-gray-700 text-sm uppercase">{{ group.user.name }}</p>
                                        <p class="text-[10px] text-gray-500">{{ group.user.org_props?.department || 'General' }}</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2 py-1 rounded font-bold bg-green-100 text-green-800 border border-green-200 text-xs">
                                    <i class="fa-solid fa-check-double mr-1"></i> Total aprobado: {{ formatTotalTime(group.totalApprovedMinutes) }}
                                </span>
                            </div>
                        </td>
                    </tr>

                    <tr v-for="record in group.records" :key="`${record.user.id}_${record.date}`" class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <div class="text-gray-600 uppercase text-[11px] font-semibold">{{ formatDate(record.date) }}</div>
                            <div class="text-[12px] text-gray-500 font-mono mt-1 flex items-center gap-2">
                                <span class="flex items-center gap-0.5"><i class="fa-solid fa-arrow-right-to-bracket text-emerald-500"></i>{{ formatTime(record.incidence.check_in) }}</span>
                                <span class="flex items-center gap-0.5"><i class="fa-solid fa-arrow-right-from-bracket text-rose-500"></i>{{ formatTime(record.incidence.check_out) }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center align-top pt-4">
                            <span v-if="record.isRejected" class="bg-red-100 text-red-700 px-2 py-0.5 rounded font-bold text-[10px] uppercase border border-red-200">Rechazado</span>
                            <span v-else class="bg-green-100 text-green-700 px-2 py-0.5 rounded font-bold text-[10px] uppercase border border-green-200">Aprobado</span>
                        </td>
                        <td class="px-4 py-3 text-center align-top pt-4">
                            <span class="font-mono text-xs font-bold text-gray-700">{{ record.approvedStr }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-normal align-top pt-3">
                            <div class="bg-white border border-gray-100 p-2 rounded">
                                <p class="text-[11px] italic text-gray-600 leading-tight">"{{ record.commentText }}"</p>
                                <p class="text-[9px] text-gray-400 mt-1.5 uppercase font-semibold border-t border-gray-100 pt-1">
                                    Resuelto por: {{ record.incidence.approver?.name || 'Admin' }}
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-center align-top pt-4">
                            <!-- Mini resumen de niveles de aprobación -->
                            <div v-if="record.incidence.approval_decisions?.length" class="flex flex-col gap-0.5 items-center">
                                <div v-for="dec in record.incidence.approval_decisions" :key="dec.id" class="flex items-center gap-1 text-[8px]">
                                    <span class="w-3 h-3 rounded-full flex items-center justify-center text-[6px]"
                                          :class="dec.status === 'approved' ? 'bg-green-400 text-white' : 'bg-red-400 text-white'">
                                        <i :class="dec.status === 'approved' ? 'fa-solid fa-check' : 'fa-solid fa-xmark'"></i>
                                    </span>
                                    <img :src="dec.approver?.profile_photo_url" class="w-3.5 h-3.5 rounded-full" :title="dec.approver?.name">
                                </div>
                            </div>
                            <span v-else class="text-[9px] text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-center align-top pt-5">
                            <button @click="$emit('revert-single', record)" :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                class="text-xs font-bold text-indigo-500 hover:text-indigo-700 underline disabled:opacity-50 flex items-center justify-center mx-auto gap-1">
                                <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'revert'" class="fa-solid fa-spinner animate-spin"></i>
                                <span v-else>Revertir</span>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </template>
        </table>
    </div>
</template>
