<script setup>
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    groups: { type: Array, required: true },
    totalRecords: { type: Number, default: 0 },
    editableRecords: { type: Object, required: true },
    isProcessing: { type: Boolean, default: false },
    processingRow: { type: String, default: null },
    processingGroup: { type: Number, default: null },
    processingType: { type: String, default: null },
    bulkProgress: { type: Number, default: 0 },
    bulkActionType: { type: String, default: null },
    activeFiltersLabel: { type: String, default: null },
    // Jerarquía de aprobación
    hierarchy: { type: Object, default: null },
    // Control de visibilidad de botones masivos
    canDoMassActions: { type: Boolean, default: true },
});

const emit = defineEmits(['approve-single', 'reject-single', 'approve-employee', 'reject-employee', 'approve-all', 'reject-all']);

// ─── Helpers ───
const formatDate = (dateStr) => format(parseISO(dateStr), "EEEE, dd 'de' MMM", { locale: es });
const formatTime = (timeStr) => timeStr ? timeStr.substring(0, 5) : '--:--';
const formatTotalTime = (mins) => {
    if (!mins) return '0h 0m';
    return `${Math.floor(mins / 60)}h ${mins % 60}m`;
};

// Determinar si un registro puede ser accionado por el usuario actual
function canActOnRecord(record) {
    if (!props.hierarchy) return true; // Sin jerarquía: cualquiera puede
    const perm = props.hierarchy.getActionPermission(record.incidence);
    return perm.canAct;
}

function getRecordPermission(record) {
    if (!props.hierarchy) return { canAct: true, reason: '', isMyEmployee: true };
    return props.hierarchy.getActionPermission(record.incidence);
}

// Saber si el usuario puede accionar al menos un registro del grupo
function canActOnGroup(group) {
    return group.records.some(r => canActOnRecord(r));
}

// Pipeline de aprobación por registro (misma lógica que IncidencesTable)
function getApprovalPipeline(record) {
    if (!props.hierarchy) return null;
    const perm = props.hierarchy.getActionPermission(record.incidence);
    if (!perm.isMyEmployee) return null;

    const decisions = record.incidence.approval_decisions || [];
    // Obtener los niveles ordenados del approvalLevels que están en el hierarchy
    // No tenemos acceso directo a approvalLevels aquí, usamos los datos del perm
    if (!perm.currentLevel) return null;

    // Reconstruir niveles desde la incidencia
    const allLevels = record.incidence.approval_decisions?.length
        ? [...new Map(record.incidence.approval_decisions.map(d => [d.level_id, { id: d.level_id, name: d.level_name }])).values()]
        : [];

    return {
        canAct: perm.canAct,
        reason: perm.reason,
        currentLevelName: perm.currentLevel?.name || null,
        previousDecisions: perm.previousDecisions || {},
        allLevels,
        decisions,
    };
}

// Badge de estado
function getStatusBadge(status) {
    switch (status) {
        case 'approved': return { bg: 'bg-green-100', text: 'text-green-700', icon: 'fa-check-circle' };
        case 'rejected': return { bg: 'bg-red-100', text: 'text-red-700', icon: 'fa-xmark-circle' };
        default: return { bg: 'bg-gray-100', text: 'text-gray-500', icon: 'fa-clock' };
    }
}
</script>

<template>
    <div v-if="groups.length === 0" class="py-12 text-center text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-300 mt-2">
        <i class="fa-solid fa-check-circle text-4xl text-green-300 mb-3 block"></i>
        <p class="font-medium">
            {{ activeFiltersLabel ? 'No hay pendientes con los filtros aplicados.' : 'Todo al día. No hay tiempo extra pendiente de revisar.' }}
        </p>
    </div>

    <div v-else class="mt-2">
        <!-- Toolbar masivo -->
        <div class="flex flex-col md:flex-row justify-between md:items-center bg-blue-50/50 p-3 rounded-t-lg border border-blue-100 border-b-0 gap-3">
            <div class="flex-1">
                <p v-if="!isProcessing" class="text-xs text-blue-700 font-medium">
                    <i class="fa-solid fa-circle-info mr-1"></i>
                    Mostrando <strong>{{ totalRecords }}</strong> registros pendientes{{ activeFiltersLabel ? ` (${activeFiltersLabel})` : '' }}.
                    <span v-if="!canDoMassActions && hierarchy" class="text-amber-600 font-semibold ml-2">
                        <i class="fa-solid fa-lock mr-1"></i> Solo puedes gestionar los registros de tu grupo
                    </span>
                </p>
                <p v-else class="text-xs text-amber-600 font-bold animate-pulse flex items-center gap-1.5">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Procesando lote ({{ bulkProgress }}%). No cierres la ventana.
                </p>
            </div>
            <div v-if="canDoMassActions" class="flex gap-2">
                <button @click="$emit('reject-all')" :disabled="isProcessing"
                    class="bg-white text-red-600 border border-red-200 hover:bg-red-50 px-4 py-1.5 rounded shadow-sm text-sm font-bold transition-colors disabled:opacity-50 flex items-center min-w-[130px]">
                    <template v-if="isProcessing && bulkActionType === 'reject' && !processingGroup">
                        <i class="fa-solid fa-spinner animate-spin mr-2"></i> {{ bulkProgress }}%
                    </template>
                    <span v-else>Rechazar todo</span>
                </button>
                <button @click="$emit('approve-all')" :disabled="isProcessing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 px-4 py-1.5 rounded shadow-sm text-sm font-bold transition-colors disabled:opacity-50 flex items-center min-w-[130px]">
                    <template v-if="isProcessing && bulkActionType === 'approve' && !processingGroup">
                        <i class="fa-solid fa-spinner animate-spin mr-2"></i> {{ bulkProgress }}%
                    </template>
                    <span v-else>Aprobar todo</span>
                </button>
            </div>
        </div>

        <!-- Barra de progreso -->
        <div v-if="isProcessing && !processingGroup" class="w-full bg-gray-200 h-1">
            <div class="bg-indigo-500 h-1 transition-all duration-300 ease-out" :style="{ width: `${bulkProgress}%` }"></div>
        </div>

        <!-- Tabla agrupada -->
        <div class="border border-gray-200 rounded-b-lg overflow-x-auto" :class="{ 'opacity-75 pointer-events-none': isProcessing && !processingGroup }">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-100 text-gray-600 uppercase text-[10px] tracking-wider border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 w-[18%]">Fecha</th>
                        <th class="px-4 py-3 w-[12%] text-center">Solicitado</th>
                        <th class="px-4 py-3 w-[28%] bg-indigo-50/50 border-x border-indigo-100 text-indigo-800">Resolución (ajuste)</th>
                        <th class="px-4 py-3 w-[22%]">Comentarios</th>
                        <th class="px-4 py-3 w-[10%] text-center">Nivel</th>
                        <th class="px-4 py-3 w-[10%] text-center">Acción</th>
                    </tr>
                </thead>

                <template v-for="group in groups" :key="group.user.id">
                    <tbody class="divide-y divide-gray-200 border-t-[3px] border-gray-300"
                           :class="{ 'opacity-50': isProcessing && processingGroup === group.user.id }">
                        <!-- Header del empleado -->
                        <tr class="bg-indigo-50/80 border-b border-indigo-100">
                            <td colspan="6" class="px-4 py-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <img :src="group.user.profile_photo_url" class="h-8 w-8 rounded-full border border-gray-300 object-cover">
                                        <div>
                                            <p class="font-bold text-[#0B3B51] text-sm uppercase">{{ group.user.name }}</p>
                                            <p class="text-[10px] text-gray-500">{{ group.user.org_props?.department || 'General' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded font-bold bg-amber-100 text-amber-800 border border-amber-200 text-xs">
                                            <i class="fa-solid fa-clock mr-1"></i> Total: {{ formatTotalTime(group.totalPendingMinutes) }}
                                        </span>
                                        <div v-if="canActOnGroup(group)" class="flex items-center gap-1 border-l border-indigo-200 pl-3">
                                            <button @click="$emit('reject-employee', group)" :disabled="isProcessing"
                                                class="w-7 h-7 rounded bg-white text-red-500 border border-red-200 hover:bg-red-50 flex justify-center items-center disabled:opacity-50"
                                                title="Rechazar todo del empleado">
                                                <i v-if="processingGroup === group.user.id && bulkActionType === 'reject'" class="fa-solid fa-spinner animate-spin"></i>
                                                <i v-else class="fa-solid fa-xmark"></i>
                                            </button>
                                            <button @click="$emit('approve-employee', group)" :disabled="isProcessing"
                                                class="w-7 h-7 rounded bg-indigo-600 text-white hover:bg-indigo-700 flex justify-center items-center disabled:opacity-50"
                                                title="Aprobar todo del empleado">
                                                <i v-if="processingGroup === group.user.id && bulkActionType === 'approve'" class="fa-solid fa-spinner animate-spin"></i>
                                                <i v-else class="fa-solid fa-check-double"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Días -->
                        <tr v-for="record in group.records" :key="`${record.user.id}_${record.date}`"
                            class="hover:bg-gray-50 transition-colors"
                            :class="{ 'bg-red-50/30': getRecordPermission(record).isMyEmployee && !getRecordPermission(record).canAct }">
                            <!-- Fecha -->
                            <td class="px-4 py-3">
                                <div class="text-gray-600 uppercase text-[11px] font-semibold">{{ formatDate(record.date) }}</div>
                                <div class="text-[12px] text-gray-500 font-mono mt-1 flex items-center gap-2">
                                    <span class="flex items-center gap-0.5"><i class="fa-solid fa-arrow-right-to-bracket text-emerald-500"></i>{{ formatTime(record.incidence.check_in) }}</span>
                                    <span class="flex items-center gap-0.5"><i class="fa-solid fa-arrow-right-from-bracket text-rose-500"></i>{{ formatTime(record.incidence.check_out) }}</span>
                                </div>
                            </td>
                            <!-- Solicitado -->
                            <td class="px-4 py-3 text-center align-top pt-4">
                                <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-mono text-xs font-bold border border-amber-200">{{ record.requestedStr }}</span>
                            </td>
                            <!-- Ajuste -->
                            <td class="px-4 py-2 bg-indigo-50/30 border-x border-indigo-50 align-top pt-3">
                                <div class="flex items-center justify-center gap-2" v-if="editableRecords[`${record.user.id}_${record.date}`]">
                                    <el-input-number v-model="editableRecords[`${record.user.id}_${record.date}`].hours"
                                        :min="0" size="small" class="!w-20" controls-position="right" />
                                    <span class="text-xs text-indigo-500 font-bold">h</span>
                                    <el-input-number v-model="editableRecords[`${record.user.id}_${record.date}`].minutes"
                                        :min="0" :max="59" size="small" class="!w-20" controls-position="right" />
                                    <span class="text-xs text-indigo-500 font-bold">m</span>
                                </div>
                            </td>
                            <!-- Comentario -->
                            <td class="px-4 py-2 align-top pt-3">
                                <el-input v-if="editableRecords[`${record.user.id}_${record.date}`]"
                                    v-model="editableRecords[`${record.user.id}_${record.date}`].comments"
                                    type="textarea" :rows="2" resize="none" placeholder="Ej. Proyecto Alpha..." class="text-xs" />
                            </td>
                            <!-- Nivel / Jerarquía -->
                            <td class="px-4 py-2 align-top pt-2">
                                <template v-if="hierarchy">
                                    <div v-if="getRecordPermission(record).isMyEmployee" class="text-[9px] space-y-0.5">
                                        <!-- Ya decidió: mostrar su decisión -->
                                        <span v-if="getRecordPermission(record).alreadyDecided"
                                              class="inline-block px-1.5 py-0.5 rounded border font-bold"
                                              :class="getRecordPermission(record).myDecision?.status === 'approved'
                                                  ? 'bg-green-100 text-green-700 border-green-200'
                                                  : 'bg-red-100 text-red-700 border-red-200'">
                                            <i :class="getRecordPermission(record).myDecision?.status === 'approved'
                                                ? 'fa-solid fa-check-circle mr-0.5' : 'fa-solid fa-xmark-circle mr-0.5'"></i>
                                            {{ getRecordPermission(record).myDecision?.status === 'approved' ? 'Aprobaste' : 'Rechazaste' }}
                                        </span>
                                        <!-- Tu turno -->
                                        <span v-else-if="getRecordPermission(record).canAct"
                                              class="inline-block bg-green-100 text-green-700 px-1.5 py-0.5 rounded border border-green-200 font-bold">
                                            <i class="fa-solid fa-arrow-right text-[7px] mr-0.5"></i>Tu turno
                                        </span>
                                        <!-- Esperando -->
                                        <span v-else class="inline-block text-amber-600 italic">
                                            {{ getRecordPermission(record).reason }}
                                        </span>
                                        <!-- Decisiones previas compactas -->
                                        <div v-if="getRecordPermission(record).previousDecisions" class="flex flex-col gap-0.5 mt-1">
                                            <div v-for="(dec, levelNum) in getRecordPermission(record).previousDecisions" :key="levelNum"
                                                 class="flex items-center gap-1 text-[7px] text-gray-500">
                                                <span class="font-semibold">{{ dec.name }}:</span>
                                                <span v-for="d in dec.decisions" :key="d.approverName" class="flex items-center gap-0.5">
                                                    <span class="w-3 h-3 rounded-full flex items-center justify-center text-[5px]"
                                                          :class="d.status === 'approved' ? 'bg-green-400 text-white' : 'bg-red-400 text-white'">
                                                        <i :class="d.status === 'approved' ? 'fa-solid fa-check' : 'fa-solid fa-xmark'"></i>
                                                    </span>
                                                    <span>{{ d.approverName.split(' ')[0] }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <span v-else class="text-[9px] text-gray-300">—</span>
                                </template>
                                <span v-else class="text-[9px] text-gray-300">—</span>
                            </td>
                            <!-- Acción -->
                            <td class="px-4 py-3 text-center align-top pt-4">
                                <div v-if="getRecordPermission(record).canAct" class="flex items-center justify-center gap-2">
                                    <button @click="$emit('reject-single', record)" :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                        class="w-8 h-8 rounded bg-white text-red-500 border border-red-200 hover:bg-red-50 flex justify-center items-center disabled:opacity-50">
                                        <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'reject'" class="fa-solid fa-spinner animate-spin"></i>
                                        <i v-else class="fa-solid fa-xmark"></i>
                                    </button>
                                    <button @click="$emit('approve-single', record)" :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                        class="w-8 h-8 rounded bg-indigo-50 text-indigo-600 border border-indigo-200 hover:bg-indigo-100 flex justify-center items-center disabled:opacity-50">
                                        <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'approve'" class="fa-solid fa-spinner animate-spin"></i>
                                        <i v-else class="fa-solid fa-check"></i>
                                    </button>
                                </div>
                                <span v-else class="text-[10px] text-gray-400 italic">En espera</span>
                            </td>
                        </tr>
                    </tbody>
                </template>
            </table>
        </div>
    </div>
</template>
