<script setup>
import { ref } from 'vue';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessageBox } from 'element-plus';

const props = defineProps({
    groups: { type: Array, required: true },
    totalRecords: { type: Number, default: 0 },
    editableRecords: { type: Object, required: true },
    isProcessing: { type: Boolean, default: false },
    processingRow: { type: String, default: null },
    processingGroup: { type: Number, default: null },
    processingType: { type: String, default: null },
    activeFiltersLabel: { type: String, default: null },
    hierarchy: { type: Object, default: null },
    approvalGroups: { type: Array, default: () => [] },
    actionableCount: { type: Number, default: 0 },
});

const emit = defineEmits([
    'approve-single', 'reject-single', 'revert-single',
    'approve-employee', 'reject-employee',
]);

// ─── Colapsar grupos ───
const collapsedGroups = ref(new Set());
function toggleGroup(groupId) {
    if (collapsedGroups.value.has(groupId)) {
        collapsedGroups.value.delete(groupId);
    } else {
        collapsedGroups.value.add(groupId);
    }
}

// ─── Helpers ───
const formatDate = (dateStr) => format(parseISO(dateStr), "EEEE dd 'de' MMM", { locale: es });
const formatTime = (timeStr) => timeStr ? timeStr.substring(0, 5) : '--:--';
const formatTotalTime = (mins) => {
    if (!mins) return '0h 0m';
    return `${Math.floor(mins / 60)}h ${mins % 60}m`;
};

// ─── ESTATUS GLOBAL ───
function getGlobalStatus(record) {
    if (!props.hierarchy) {
        if (record.incidence.approved_at) {
            return record.incidence.approved_extra_hours === 0 && record.incidence.approved_extra_minutes === 0
                ? 'rejected' : 'approved';
        }
        return 'pending';
    }
    const info = getMyDecisionInfo(record);
    const decisions = record.incidence.approval_decisions || [];
    const status = record.incidence.extra_hour_status || 'none';

    // Rechazado global: algún nivel tiene rechazo
    if (decisions.some(d => d.status === 'rejected')) return 'rejected';

    // Ya decidí y el backend confirmó todo completo → Aprobado
    if (info.hasDecided && record.incidence.approved_at) return 'approved';

    // Fallback: estado desnormalizado final sin decisiones locales
    // (modo directo o datos legacy/huérfanos) → respetar el backend
    if (status === 'approved') return 'approved';
    if (status === 'rejected') return 'rejected';

    // Sin jerarquía aplicable → si backend marcó, aprobado; si no, en espera
    if (!info.isMyEmployee) {
        return record.incidence.approved_at ? 'approved' : 'pending';
    }

    // En espera: estoy bloqueado, es mi turno, o no hay decisiones aún
    return 'pending';
}

function getGlobalStatusLabel(status) {
    switch (status) {
        case 'approved': return { text: 'Aprobado', class: 'bg-green-100 text-green-700 border-green-200' };
        case 'rejected': return { text: 'Rechazado', class: 'bg-red-100 text-red-700 border-red-200' };
        case 'pending': return { text: 'En espera', class: 'bg-blue-100 text-blue-700 border-blue-200' };
        default: return { text: '—', class: 'bg-gray-50 text-gray-400' };
    }
}

// ─── MI DECISIÓN (del aprobador actual) ───
function getMyDecisionInfo(record) {
    if (!props.hierarchy) return { hasDecided: false, status: null, canAct: true, canRevert: false };
    const perm = props.hierarchy.getActionPermission(record.incidence);
    // ¿Puedo revertir este registro? Un aprobador del grupo puede desbloquear
    // estados finales (incluidos huérfanos sin mi decisión).
    const canRevert = props.hierarchy.canRevertDecision(record.incidence);
    return {
        hasDecided: perm.alreadyDecided || false,
        status: perm.myDecision?.status || null,
        canAct: perm.canAct && !perm.alreadyDecided,
        canRevert,
        reason: perm.reason || '',
        isMyEmployee: perm.isMyEmployee || false,
    };
}

// ─── Stats por grupo (basado en MI decisión) ───
function getGroupStats(group) {
    let pending = 0, rejected = 0, approved = 0;
    group.records.forEach(record => {
        const mins = (record.incidence.extra_hours || 0) * 60 + (record.incidence.extra_minutes || 0);
        const info = getMyDecisionInfo(record);
        if (!info.hasDecided && info.canAct) pending += mins;
        else if (info.status === 'rejected') rejected += mins;
        else if (info.status === 'approved') approved += mins;
    });
    return { pending, rejected, approved };
}

// ─── ¿El grupo tiene registros accionables? ───
function canActOnRecord(record) {
    return getMyDecisionInfo(record).canAct;
}

function canActOnGroup(group) {
    return group.records.some(r => canActOnRecord(r));
}

// ─── Flujo de aprobación (derivado de decisions, NO depende de approvalLevels prop) ───
function getAllLevelsSummary(record) {
    if (!props.hierarchy) return [];
    const decisions = record.incidence.approval_decisions || [];
    if (decisions.length === 0) return [];

    // Agrupar por level_id y ordenar por level_id
    const byLevel = {};
    decisions.forEach(d => {
        if (!byLevel[d.level_id]) byLevel[d.level_id] = { name: d.level_name || `Nivel ${d.level_id}`, approvers: [] };
        byLevel[d.level_id].approvers.push({
            id: d.approver?.id,
            name: d.approver?.name || '?',
            profile_photo_url: d.approver?.profile_photo_url || '',
            decision: { status: d.status },
            // Auditoría del ajuste propuesto por este aprobador
            proposedHours: d.proposed_extra_hours ?? null,
            proposedMinutes: d.proposed_extra_minutes ?? null,
        });
    });

    return Object.entries(byLevel)
        .sort(([a], [b]) => Number(a) - Number(b))
        .map(([levelId, data]) => ({
            levelId: Number(levelId),
            levelName: data.name,
            approvers: data.approvers,
            allApproved: data.approvers.every(a => a.decision?.status === 'approved'),
            hasRejection: data.approvers.some(a => a.decision?.status === 'rejected'),
            isPending: !data.approvers.every(a => a.decision?.status === 'approved') 
                       && !data.approvers.some(a => a.decision?.status === 'rejected'),
        }));
}

// ─── Acciones con confirmación (one-time, no reversible) ───
async function confirmAndApprove(record) {
    try {
        await ElMessageBox.confirm(
            `¿APROBAR el tiempo extra de ${record.user.name.split(' ')[0]} del ${formatDate(record.date)}? Una vez decidido no se podrá cambiar.`,
            'Confirmar aprobación',
            { confirmButtonText: 'Sí, aprobar', cancelButtonText: 'Cancelar', type: 'warning' }
        );
        emit('approve-single', record);
    } catch (e) { /* cancelado */ }
}

async function confirmAndReject(record) {
    try {
        await ElMessageBox.confirm(
            `¿RECHAZAR el tiempo extra de ${record.user.name.split(' ')[0]} del ${formatDate(record.date)}? Una vez decidido no se podrá cambiar.`,
            'Confirmar rechazo',
            { confirmButtonText: 'Sí, rechazar', cancelButtonText: 'Cancelar', type: 'error' }
        );
        emit('reject-single', record);
    } catch (e) { /* cancelado */ }
}

async function confirmAndRevert(record) {
    try {
        await ElMessageBox.confirm(
            `¿REVERTIR tu decisión sobre el tiempo extra de ${record.user.name.split(' ')[0]} del ${formatDate(record.date)}? El registro volverá a tu turno para que puedas decidir de nuevo.`,
            'Confirmar reversión',
            { confirmButtonText: 'Sí, revertir', cancelButtonText: 'Cancelar', type: 'warning' }
        );
        emit('revert-single', record);
    } catch (e) { /* cancelado */ }
}
</script>

<template>
    <div v-if="groups.length === 0" class="py-16 text-center text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-300 mt-2">
        <i class="fa-solid fa-check-circle text-5xl text-green-300 mb-4 block"></i>
        <p class="text-lg font-medium text-gray-500">
            {{ activeFiltersLabel ? 'No hay registros con los filtros aplicados.' : 'No hay tiempo extra registrado en esta nómina.' }}
        </p>
        <p class="text-sm text-gray-400 mt-1">Todo está al día.</p>
    </div>

    <div v-else class="mt-2">
        <!-- Barra superior -->
        <div class="flex flex-col md:flex-row justify-between md:items-center bg-gray-50 p-3 rounded-t-lg border border-gray-200 border-b-0 gap-3">
            <div>
                <p class="text-xs text-gray-600 font-medium">
                    <i class="fa-solid fa-circle-info mr-1"></i>
                    <strong>{{ totalRecords }}</strong> registros{{ activeFiltersLabel ? ` (${activeFiltersLabel})` : '' }}
                    <template v-if="hierarchy && actionableCount > 0">
                        · <span class="text-blue-600 font-bold">{{ actionableCount }} en tu turno</span>
                    </template>
                </p>
            </div>
        </div>

        <!-- Lista de empleados colapsables -->
        <div class="border border-gray-200 rounded-b-lg divide-y divide-gray-200">
            <div v-for="group in groups" :key="group.user.id">

                <!-- Header del empleado (click para colapsar) -->
                <div @click="toggleGroup(group.user.id)"
                    class="bg-gray-50/80 px-4 py-2.5 flex items-center justify-between cursor-pointer hover:bg-gray-100 transition-colors select-none"
                    :class="{ 'border-b border-gray-200': !collapsedGroups.has(group.user.id) }">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid text-gray-400 text-xs transition-transform duration-200"
                           :class="collapsedGroups.has(group.user.id) ? 'fa-chevron-right' : 'fa-chevron-down'"></i>
                        <img :src="group.user.profile_photo_url" class="h-8 w-8 rounded-full border border-gray-300 object-cover">
                        <div>
                            <p class="font-bold text-[#0B3B51] text-sm">{{ group.user.name }}</p>
                            <p class="text-[10px] text-gray-500">{{ group.user.org_props?.department || 'General' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Mini stats -->
                        <div class="hidden sm:flex items-center gap-1.5 text-[9px]">
                            <span v-if="getGroupStats(group).pending > 0" class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-bold">
                                {{ formatTotalTime(getGroupStats(group).pending) }} pend.
                            </span>
                            <span v-if="getGroupStats(group).rejected > 0" class="bg-red-100 text-red-700 px-1.5 py-0.5 rounded font-bold">
                                {{ formatTotalTime(getGroupStats(group).rejected) }} rech.
                            </span>
                            <span v-if="getGroupStats(group).approved > 0" class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-bold">
                                {{ formatTotalTime(getGroupStats(group).approved) }} aprob.
                            </span>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded font-bold bg-gray-100 text-gray-700 border border-gray-200 text-[10px]">
                            {{ formatTotalTime(group.totalMinutes) }}
                        </span>
                        <!-- Botones por empleado (solo accionables) -->
                        <div v-if="canActOnGroup(group) && hierarchy" class="flex items-center gap-1 border-l border-gray-200 pl-3" @click.stop>
                            <button @click="$emit('reject-employee', group)" :disabled="isProcessing"
                                class="w-7 h-7 rounded bg-white text-red-500 border border-red-200 hover:bg-red-50 flex justify-center items-center disabled:opacity-50"
                                title="Rechazar todo lo pendiente">
                                <i v-if="processingGroup === group.user.id && processingType === 'reject'" class="fa-solid fa-spinner animate-spin"></i>
                                <i v-else class="fa-solid fa-xmark"></i>
                            </button>
                            <button @click="$emit('approve-employee', group)" :disabled="isProcessing"
                                class="w-7 h-7 rounded bg-blue-600 text-white hover:bg-blue-700 flex justify-center items-center disabled:opacity-50"
                                title="Aprobar todo lo pendiente">
                                <i v-if="processingGroup === group.user.id && processingType === 'approve'" class="fa-solid fa-spinner animate-spin"></i>
                                <i v-else class="fa-solid fa-check-double"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Días (colapsables) -->
                <div v-show="!collapsedGroups.has(group.user.id)" class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-[11px] tracking-wider border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-2.5 w-[13%]">Fecha</th>
                                <th class="px-4 py-2.5 w-[9%] text-center">Solicitado</th>
                                <th class="px-4 py-2.5 w-[10%] text-center">Estatus</th>
                                <th class="px-4 py-2.5 w-[25%]">Flujo de aprobación</th>
                                <th class="px-4 py-2.5 w-[25%]">Comentarios / Proyecto</th>
                                <th class="px-4 py-2.5 w-[18%] text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="record in group.records" :key="`${record.user.id}_${record.date}`"
                                class="hover:bg-gray-50 transition-colors"
                                :class="{
                                    'bg-blue-50/30': getGlobalStatus(record) === 'pending' && getMyDecisionInfo(record).canAct,
                                    'bg-red-50/20': getGlobalStatus(record) === 'rejected',
                                }">
                                <!-- Fecha -->
                                <td class="px-4 py-2.5">
                                    <div class="text-gray-700 uppercase text-[12px] font-semibold">{{ formatDate(record.date) }}</div>
                                    <div class="text-[11px] text-gray-500 font-mono mt-0.5 flex items-center gap-1.5">
                                        <span class="flex items-center gap-0.5"><i class="fa-solid fa-arrow-right-to-bracket text-emerald-500 text-[10px]"></i>{{ formatTime(record.incidence.check_in) }}</span>
                                        <span class="flex items-center gap-0.5"><i class="fa-solid fa-arrow-right-from-bracket text-rose-500 text-[10px]"></i>{{ formatTime(record.incidence.check_out) }}</span>
                                    </div>
                                </td>
                                <!-- Solicitado -->
                                <td class="px-4 py-2.5 text-center">
                                    <el-tooltip
                                        v-if="record.originalStr && record.originalStr !== record.requestedStr"
                                        :content="`Original: ${record.originalStr} → Ajustado a ${record.requestedStr}`"
                                        placement="top">
                                        <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-mono text-xs font-bold border border-amber-200">
                                            {{ record.requestedStr }}
                                            <i class="fa-solid fa-pen-to-square text-[9px]"></i>
                                        </span>
                                    </el-tooltip>
                                    <span v-else class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-mono text-xs font-bold border border-amber-200">{{ record.requestedStr }}</span>
                                </td>
                                <!-- Estatus (GLOBAL) -->
                                <td class="px-4 py-2.5 text-center">
                                    <span class="inline-block px-2 py-1 rounded text-[11px] font-bold border"
                                        :class="getGlobalStatusLabel(getGlobalStatus(record)).class">
                                        {{ getGlobalStatusLabel(getGlobalStatus(record)).text }}
                                    </span>
                                </td>
                                <!-- Flujo de aprobación -->
                                <td class="px-4 py-2.5">
                                    <div v-if="getAllLevelsSummary(record).length === 0" class="text-[11px] text-gray-300">—</div>
                                    <div v-else class="flex flex-col gap-2.5">
                                        <div v-for="(lvl, idx) in getAllLevelsSummary(record)" :key="lvl.levelId"
                                            class="rounded px-2 py-1.5"
                                            :class="{
                                                'bg-green-50 border border-green-100': lvl.allApproved,
                                                'bg-red-50 border border-red-100': lvl.hasRejection,
                                                'bg-blue-50 border border-blue-100': lvl.isPending && !lvl.hasRejection,
                                                'bg-gray-50 border border-gray-100': !lvl.allApproved && !lvl.hasRejection && !lvl.isPending,
                                            }">
                                            <!-- Nombre del nivel con badge -->
                                            <div class="flex items-center justify-between mb-1.5">
                                                <span class="text-[11px] font-semibold text-gray-700">{{ lvl.levelName }}</span>
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full border"
                                                    :class="{
                                                        'bg-green-100 text-green-700 border-green-200': lvl.allApproved,
                                                        'bg-red-100 text-red-700 border-red-200': lvl.hasRejection,
                                                        'bg-blue-100 text-blue-700 border-blue-200': lvl.isPending && !lvl.hasRejection,
                                                        'bg-gray-100 text-gray-500 border-gray-200': !lvl.allApproved && !lvl.hasRejection && !lvl.isPending,
                                                    }">
                                                    Nivel {{ idx + 1 }}
                                                </span>
                                            </div>
                                            <!-- Avatares en fila -->
                                            <div class="flex flex-wrap items-end gap-3">
                                                <div v-for="approver in lvl.approvers" :key="approver.id" class="flex flex-col items-center gap-1">
                                                    <el-tooltip
                                                        :content="`${approver.name}${approver.proposedHours !== null ? ` · dejó ${approver.proposedHours}h ${approver.proposedMinutes ?? 0}m` : ''}`"
                                                        placement="top">
                                                        <div class="relative">
                                                            <img :src="approver.profile_photo_url" 
                                                                class="w-8 h-8 rounded-full border-2 object-cover"
                                                                :class="approver.decision?.status === 'approved' 
                                                                    ? 'border-green-400 ring-2 ring-green-200' 
                                                                    : approver.decision?.status === 'rejected'
                                                                    ? 'border-red-400 ring-2 ring-red-200' 
                                                                    : 'border-gray-200'">
                                                            <span v-if="approver.decision"
                                                                class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full flex items-center justify-center text-[7px] border-2 border-white"
                                                                :class="approver.decision.status === 'approved' 
                                                                    ? 'bg-green-500 text-white' 
                                                                    : 'bg-red-500 text-white'">
                                                                <i :class="approver.decision.status === 'approved' 
                                                                    ? 'fa-solid fa-check' : 'fa-solid fa-xmark'"></i>
                                                            </span>
                                                        </div>
                                                    </el-tooltip>
                                                    <span class="text-[10px] text-gray-600 max-w-[60px] truncate text-center leading-tight">{{ approver.name.split(' ')[0] }}</span>
                                                    <!-- Auditoría visible: qué valor dejó este aprobador -->
                                                    <span v-if="approver.proposedHours !== null && approver.decision?.status === 'approved'"
                                                        class="text-[8px] font-mono font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1 py-0.5 leading-none"
                                                        :title="`Ajuste propuesto por ${approver.name}`">
                                                        {{ approver.proposedHours }}h {{ approver.proposedMinutes ?? 0 }}m
                                                    </span>
                                                </div>
                                                <span v-if="lvl.approvers.length === 0" class="text-[10px] text-gray-400 italic">Sin aprobadores asignados</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <!-- Comentarios / Proyecto -->
                                <td class="px-4 py-2.5">
                                    <div class="space-y-1.5">
                                        <!-- Ajuste de horas (solo editable si es tu turno) -->
                                        <div v-if="getMyDecisionInfo(record).canAct" class="flex items-center gap-1">
                                            <el-input-number 
                                                v-model="editableRecords[`${record.user.id}_${record.date}`].hours"
                                                :min="0" size="small" class="!w-24" controls-position="right" 
                                            />
                                            <span class="text-[10px] text-gray-400">h</span>
                                            <el-input-number 
                                                v-model="editableRecords[`${record.user.id}_${record.date}`].minutes"
                                                :min="0" :max="59" size="small" class="!w-24" controls-position="right" 
                                            />
                                            <span class="text-[10px] text-gray-400">m</span>
                                        </div>
                                        <!-- Comentario solo lectura -->
                                        <p v-if="editableRecords[`${record.user.id}_${record.date}`]?.comments" 
                                           class="text-[11px] italic text-gray-500 leading-tight">
                                            💬 "{{ editableRecords[`${record.user.id}_${record.date}`].comments }}"
                                        </p>
                                        <p v-else class="text-[10px] text-gray-300 italic">Sin comentarios</p>
                                        <!-- Proyecto vinculado -->
                                        <div v-if="record.incidence.project" 
                                            class="text-[10px] text-teal-700 bg-teal-50 px-1.5 py-0.5 rounded border border-teal-200 font-semibold inline-block">
                                            📊 {{ record.incidence.project.name }}
                                            <span v-if="record.incidence.project.client" class="text-[9px] text-teal-500 font-normal ml-1">({{ record.incidence.project.client }})</span>
                                        </div>
                                    </div>
                                </td>
                                <!-- Acción (MI decisión) -->
                                <td class="px-4 py-2.5 text-center">
                                    <!-- Es mi turno → botones -->
                                    <div v-if="getMyDecisionInfo(record).canAct" class="flex items-center justify-center gap-2">
                                        <button @click="confirmAndReject(record)" 
                                            :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                            class="w-9 h-9 rounded bg-white text-red-500 border border-red-200 hover:bg-red-50 flex justify-center items-center disabled:opacity-50"
                                            title="Rechazar">
                                            <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'reject'" class="fa-solid fa-spinner animate-spin"></i>
                                            <i v-else class="fa-solid fa-xmark"></i>
                                        </button>
                                        <button @click="confirmAndApprove(record)" 
                                            :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                            class="w-9 h-9 rounded bg-blue-600 text-white hover:bg-blue-700 flex justify-center items-center disabled:opacity-50"
                                            title="Aprobar">
                                            <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'approve'" class="fa-solid fa-spinner animate-spin"></i>
                                            <i v-else class="fa-solid fa-check"></i>
                                        </button>
                                    </div>
                                    <!-- Ya decidí → ícono + texto + botón revertir mi decisión -->
                                    <div v-else-if="getMyDecisionInfo(record).hasDecided" class="flex flex-col items-center gap-1">
                                        <span class="w-9 h-9 rounded-full flex items-center justify-center text-sm"
                                            :class="getMyDecisionInfo(record).status === 'approved' 
                                                ? 'bg-green-100 text-green-600' 
                                                : 'bg-red-100 text-red-600'">
                                            <i :class="getMyDecisionInfo(record).status === 'approved'
                                                ? 'fa-solid fa-check' : 'fa-solid fa-xmark'"></i>
                                        </span>
                                        <span class="text-[11px] font-semibold"
                                            :class="getMyDecisionInfo(record).status === 'approved' 
                                                ? 'text-green-600' : 'text-red-600'">
                                            {{ getMyDecisionInfo(record).status === 'approved' ? 'Aprobaste' : 'Rechazaste' }}
                                        </span>
                                        <button @click="confirmAndRevert(record)" 
                                            :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                            class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold text-gray-600 bg-gray-100 hover:bg-amber-50 hover:text-amber-700 border border-gray-200 hover:border-amber-300 rounded px-2 py-1 transition-colors disabled:opacity-50"
                                            title="Revertir mi decisión para decidir de nuevo">
                                            <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'revert'" class="fa-solid fa-spinner animate-spin"></i>
                                            <i v-else class="fa-solid fa-rotate-left"></i>
                                            Revertir mi decisión
                                        </button>
                                    </div>
                                    <!-- Estado final sin mi decisión (huérfano) → botón revertir para desbloquear -->
                                    <div v-else-if="getMyDecisionInfo(record).canRevert" class="flex flex-col items-center gap-1">
                                        <button @click="confirmAndRevert(record)" 
                                            :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                            class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold text-gray-600 bg-gray-100 hover:bg-amber-50 hover:text-amber-700 border border-gray-200 hover:border-amber-300 rounded px-2 py-1 transition-colors disabled:opacity-50"
                                            title="Revertir resolución para volver a gestionar">
                                            <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'revert'" class="fa-solid fa-spinner animate-spin"></i>
                                            <i v-else class="fa-solid fa-rotate-left"></i>
                                            Revertir resolución
                                        </button>
                                    </div>
                                    <!-- Bloqueado / fuera de scope → razón -->
                                    <span v-else class="text-[11px] text-gray-400 italic">
                                        {{ getMyDecisionInfo(record).reason || '—' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
