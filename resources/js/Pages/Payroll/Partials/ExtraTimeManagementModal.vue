<script setup>
import { ref, computed, watch } from 'vue';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import axios from 'axios';
import { ElNotification, ElMessageBox } from 'element-plus';

const props = defineProps({
    modelValue: Boolean,
    payrollUsers: Array,
    payrollId: Number
});

const emit = defineEmits(['update:modelValue', 'updated']);

// --- Estado del Modal ---
const activeTab = ref('pending');
const isProcessing = ref(false);
const processingRow = ref(null); // Qué fila específica está cargando
const processingType = ref(null); // 'approve', 'reject' o 'revert'

// Variables para Procesamiento Masivo
const bulkProgress = ref(0);
const bulkActionType = ref(null);

// Estado de carga inicial diferida
const isLoadingData = ref(true); 

// Visibilidad del Modal (v-model)
const isVisible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

// --- Estado Reactivo para Edición Rápida (Estilo Excel) ---
const editableRecords = ref({});

// Controlamos la carga diferida para evitar que el navegador se trabe al abrir
watch(isVisible, (newVal) => {
    if (newVal) {
        isLoadingData.value = true;
        setTimeout(() => {
            initializeEditableRecords();
            isLoadingData.value = false;
        }, 300);
    } else {
        isLoadingData.value = true;
        activeTab.value = 'pending';
    }
});

// Si la información cambia de fondo (ej. al aprobar algo individual), recargamos los inputs sin spinner
watch(() => props.payrollUsers, () => {
    if (isVisible.value && !isLoadingData.value && !isProcessing.value) {
        initializeEditableRecords();
    }
}, { deep: true });

function initializeEditableRecords() {
    props.payrollUsers.forEach(item => {
        item.incidences.forEach(inc => {
            if (!inc.approved_at && (inc.extra_hours > 0 || inc.extra_minutes > 0)) {
                const dateStr = inc.date.split('T')[0];
                const key = `${item.user.id}_${dateStr}`;
                
                if (!editableRecords.value[key]) {
                    editableRecords.value[key] = {
                        hours: inc.extra_hours || 0,
                        minutes: inc.extra_minutes || 0,
                        comments: inc.comment?.comments || '',
                    };
                }
            }
        });
    });
}

// --- Listas Computadas (Planas para procesamiento lógico) ---
const pendingRecords = computed(() => {
    const records = [];
    props.payrollUsers.forEach(item => {
        item.incidences.forEach(inc => {
            if (!inc.approved_at && (inc.extra_hours > 0 || inc.extra_minutes > 0)) {
                records.push({
                    user: item.user,
                    incidence: inc,
                    date: inc.date.split('T')[0],
                    requestedStr: `${inc.extra_hours || 0}h ${inc.extra_minutes || 0}m`
                });
            }
        });
    });
    return records.sort((a, b) => new Date(a.date) - new Date(b.date));
});

const resolvedRecords = computed(() => {
    const records = [];
    props.payrollUsers.forEach(item => {
        item.incidences.forEach(inc => {
            if (inc.approved_at && (inc.extra_hours > 0 || inc.extra_minutes > 0 || inc.approved_extra_hours > 0)) {
                records.push({
                    user: item.user,
                    incidence: inc,
                    date: inc.date.split('T')[0],
                    requestedStr: `${inc.extra_hours || 0}h ${inc.extra_minutes || 0}m`,
                    approvedStr: `${inc.approved_extra_hours || 0}h ${inc.approved_extra_minutes || 0}m`,
                    isRejected: inc.approved_extra_hours === 0 && inc.approved_extra_minutes === 0,
                    commentText: inc.comment?.comments || 'Sin comentarios'
                });
            }
        });
    });
    return records.sort((a, b) => new Date(b.date) - new Date(a.date));
});

// --- AGRUPACIÓN PARA VISTA EN TABLAS ---
const groupedPendingRecords = computed(() => {
    const groups = {};
    pendingRecords.value.forEach(record => {
        if (!groups[record.user.id]) {
            groups[record.user.id] = {
                user: record.user,
                records: []
            };
        }
        groups[record.user.id].records.push(record);
    });
    // Convertimos a array y ordenamos a los empleados alfabéticamente
    return Object.values(groups).sort((a, b) => a.user.name.localeCompare(b.user.name));
});

const groupedResolvedRecords = computed(() => {
    const groups = {};
    resolvedRecords.value.forEach(record => {
        if (!groups[record.user.id]) {
            groups[record.user.id] = {
                user: record.user,
                records: []
            };
        }
        groups[record.user.id].records.push(record);
    });
    // Convertimos a array y ordenamos a los empleados alfabéticamente
    return Object.values(groups).sort((a, b) => a.user.name.localeCompare(b.user.name));
});


// --- Helpers Visuales ---
const formatDate = (dateStr) => {
    return format(parseISO(dateStr), "EEEE, dd 'de' MMM", { locale: es });
};

const formatTime = (timeStr) => {
    if (!timeStr) return '--:--';
    return timeStr.substring(0, 5); 
};

// --- Acciones Individuales ---
const approveSingle = async (record) => {
    const key = `${record.user.id}_${record.date}`;
    const data = editableRecords.value[key];
    
    processingRow.value = key;
    processingType.value = 'approve'; 
    try {
        await axios.put(route('payroll-users.approve-extra-time'), {
            date: record.date,
            user_id: record.user.id,
            payroll_id: props.payrollId,
            approved_extra_hours: data.hours,
            approved_extra_minutes: data.minutes,
            comments: data.comments
        });
        ElNotification.success('Tiempo extra aprobado para ' + record.user.name.split(' ')[0]);
        emit('updated'); 
    } catch(e) {
        ElNotification.error('Ocurrió un error al aprobar');
    } finally {
        processingRow.value = null;
        processingType.value = null;
    }
};

const rejectSingle = async (record) => {
    const key = `${record.user.id}_${record.date}`;
    const data = editableRecords.value[key];

    processingRow.value = key;
    processingType.value = 'reject'; 
    try {
        await axios.put(route('payroll-users.reject-extra-time'), {
            date: record.date,
            user_id: record.user.id,
            payroll_id: props.payrollId,
            comments: data.comments
        });
        ElNotification.success('Tiempo extra rechazado para ' + record.user.name.split(' ')[0]);
        emit('updated');
    } catch(e) {
        ElNotification.error('Ocurrió un error al rechazar');
    } finally {
        processingRow.value = null;
        processingType.value = null;
    }
};

const revertSingle = async (record) => {
    const key = `${record.user.id}_${record.date}`;
    processingRow.value = key;
    processingType.value = 'revert';
    try {
        await axios.put(route('payroll-users.revert-extra-time'), {
            date: record.date,
            user_id: record.user.id,
        });
        ElNotification.success('Resolución revertida. Vuelve a estar pendiente.');
        emit('updated');
    } catch(e) {
        ElNotification.error('Ocurrió un error al revertir');
    } finally {
        processingRow.value = null;
        processingType.value = null;
    }
};

// --- Acciones en Lote (Masivas con Chunks) ---
const approveAll = async () => {
    try {
        await ElMessageBox.confirm(
            'Se aprobará todo el tiempo extra de la tabla. ¿Deseas continuar?',
            'Aprobar Todo',
            { confirmButtonText: 'Sí, aprobar todo', cancelButtonText: 'Cancelar', type: 'warning' }
        );

        isProcessing.value = true;
        bulkActionType.value = 'approve';
        bulkProgress.value = 0;

        const total = pendingRecords.value.length;
        let completed = 0;
        const chunkSize = 5; // Envía 5 peticiones simultáneas como máximo para no saturar el servidor

        for (let i = 0; i < total; i += chunkSize) {
            const chunk = pendingRecords.value.slice(i, i + chunkSize);
            await Promise.all(chunk.map(record => {
                const data = editableRecords.value[`${record.user.id}_${record.date}`];
                return axios.put(route('payroll-users.approve-extra-time'), {
                    date: record.date,
                    user_id: record.user.id,
                    payroll_id: props.payrollId,
                    approved_extra_hours: data.hours,
                    approved_extra_minutes: data.minutes,
                    comments: data.comments
                }).then(() => {
                    completed++;
                    bulkProgress.value = Math.round((completed / total) * 100);
                });
            }));
        }

        ElNotification.success('Todo el tiempo extra fue aprobado correctamente');
        emit('updated');
    } catch (e) {
        if (e !== 'cancel') ElNotification.error('Error al procesar el lote');
    } finally {
        isProcessing.value = false;
        bulkActionType.value = null;
        bulkProgress.value = 0;
    }
};

const rejectAll = async () => {
    try {
        await ElMessageBox.confirm(
            'Se rechazará por completo todo el tiempo pendiente en esta lista. ¿Deseas continuar?',
            'Rechazar Todo',
            { confirmButtonText: 'Sí, rechazar todo', cancelButtonText: 'Cancelar', type: 'error' }
        );

        isProcessing.value = true;
        bulkActionType.value = 'reject';
        bulkProgress.value = 0;

        const total = pendingRecords.value.length;
        let completed = 0;
        const chunkSize = 5; // Envía 5 peticiones simultáneas como máximo

        for (let i = 0; i < total; i += chunkSize) {
            const chunk = pendingRecords.value.slice(i, i + chunkSize);
            await Promise.all(chunk.map(record => {
                const data = editableRecords.value[`${record.user.id}_${record.date}`];
                return axios.put(route('payroll-users.reject-extra-time'), {
                    date: record.date,
                    user_id: record.user.id,
                    payroll_id: props.payrollId,
                    comments: data.comments
                }).then(() => {
                    completed++;
                    bulkProgress.value = Math.round((completed / total) * 100);
                });
            }));
        }

        ElNotification.success('Todo el tiempo extra pendiente fue rechazado');
        emit('updated');
    } catch (e) {
        if (e !== 'cancel') ElNotification.error('Error al procesar el lote');
    } finally {
        isProcessing.value = false;
        bulkActionType.value = null;
        bulkProgress.value = 0;
    }
};
</script>

<template>
    <el-dialog
        v-model="isVisible"
        title="Panel de control de tiempo extra"
        width="90%"
        class="!rounded-xl max-w-7xl mx-auto"
        destroy-on-close
        :close-on-click-modal="!isProcessing"
        :close-on-press-escape="!isProcessing"
        :show-close="!isProcessing"
    >
        <!-- ESTADO DE CARGA DIFERIDO -->
        <div v-if="isLoadingData" class="py-20 flex flex-col items-center justify-center min-h-[300px]">
            <i class="fa-solid fa-circle-notch animate-spin text-5xl text-indigo-500 mb-4"></i>
            <h3 class="text-lg font-bold text-gray-700">Cargando registros...</h3>
            <p class="text-sm text-gray-500 mt-1">Preparando la información de tiempo extra</p>
        </div>

        <!-- CONTENIDO REAL (TABLAS) -->
        <div v-else class="mb-4 animate-in fade-in duration-300">
            <el-tabs v-model="activeTab" class="w-full">
                
                <!-- PESTAÑA: PENDIENTES -->
                <el-tab-pane name="pending" :disabled="isProcessing">
                    <template #label>
                        <span class="flex items-center gap-2 font-semibold">
                            <i class="fa-solid fa-clock-rotate-left"></i> Pendientes por Aprobar
                            <span v-if="pendingRecords.length > 0" class="bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full text-xs">{{ pendingRecords.length }}</span>
                        </span>
                    </template>

                    <div v-if="pendingRecords.length === 0" class="py-12 text-center text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-300 mt-2">
                        <i class="fa-solid fa-check-circle text-4xl text-green-300 mb-3 block"></i>
                        <p class="font-medium">Todo al día. No hay tiempo extra pendiente de revisar.</p>
                    </div>

                    <div v-else class="mt-2">
                        <!-- Toolbar Masivo -->
                        <div class="flex flex-col md:flex-row justify-between md:items-center bg-blue-50/50 p-3 rounded-t-lg border border-blue-100 border-b-0 gap-3">
                            <div class="flex-1">
                                <p v-if="!isProcessing" class="text-xs text-blue-700 font-medium">
                                    <i class="fa-solid fa-circle-info mr-1"></i> Puedes ajustar las horas y observaciones individualmente antes de aprobar.
                                </p>
                                <p v-else class="text-xs text-amber-600 font-bold animate-pulse flex items-center gap-1.5">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    Procesando lote ({{ bulkProgress }}%). Por favor no cierres la ventana.
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="rejectAll" :disabled="isProcessing" class="bg-white text-red-600 border border-red-200 hover:bg-red-50 hover:border-red-300 px-4 py-1.5 rounded shadow-sm text-sm font-bold transition-colors disabled:opacity-50 flex items-center justify-center min-w-[130px]">
                                    <template v-if="isProcessing && bulkActionType === 'reject'">
                                        <i class="fa-solid fa-spinner animate-spin mr-2"></i> {{ bulkProgress }}%
                                    </template>
                                    <span v-else>Rechazar todo</span>
                                </button>
                                <button @click="approveAll" :disabled="isProcessing" class="bg-indigo-600 text-white hover:bg-indigo-700 px-4 py-1.5 rounded shadow-sm text-sm font-bold transition-colors disabled:opacity-50 flex items-center justify-center min-w-[130px]">
                                    <template v-if="isProcessing && bulkActionType === 'approve'">
                                        <i class="fa-solid fa-spinner animate-spin mr-2"></i> {{ bulkProgress }}%
                                    </template>
                                    <span v-else>Aprobar todo</span>
                                </button>
                            </div>
                        </div>

                        <!-- Barra de Progreso Superior (visible solo en acción masiva) -->
                        <div v-if="isProcessing" class="w-full bg-gray-200 h-1">
                            <div class="bg-indigo-500 h-1 transition-all duration-300 ease-out" :style="{ width: `${bulkProgress}%` }"></div>
                        </div>

                        <!-- Tabla Agrupada Estilo Excel -->
                        <div class="border border-gray-200 rounded-b-lg overflow-x-auto" :class="{'opacity-75 pointer-events-none': isProcessing}">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="bg-gray-100 text-gray-600 uppercase text-[10px] tracking-wider border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3 w-[20%]">Fecha</th>
                                        <th class="px-4 py-3 w-[15%] text-center">Solicitado</th>
                                        <th class="px-4 py-3 w-[30%] bg-indigo-50/50 border-x border-indigo-100 text-indigo-800">Resolución (Ajuste de Hrs)</th>
                                        <th class="px-4 py-3 w-[25%]">Comentarios / Proyecto</th>
                                        <th class="px-4 py-3 w-[10%] text-center">Acción</th>
                                    </tr>
                                </thead>
                                
                                <template v-for="group in groupedPendingRecords" :key="group.user.id">
                                    <tbody class="divide-y divide-gray-200 border-t-[3px] border-gray-300">
                                        <!-- Header del Colaborador -->
                                        <tr class="bg-indigo-50/80 border-b border-indigo-100">
                                            <td colspan="5" class="px-4 py-2">
                                                <div class="flex items-center gap-3">
                                                    <img :src="group.user.profile_photo_url" class="h-8 w-8 rounded-full border border-gray-300 object-cover shadow-sm">
                                                    <div>
                                                        <p class="font-bold text-[#0B3B51] text-sm uppercase leading-tight">{{ group.user.name }}</p>
                                                        <p class="text-[10px] text-gray-500 font-mono mt-0.5">ID: {{ group.user.id }} | {{ group.user.org_props?.department || 'General' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Registros de Días -->
                                        <tr v-for="record in group.records" :key="`${record.user.id}_${record.date}`" class="hover:bg-gray-50 transition-colors">
                                            
                                            <!-- Fecha y Horas de Registro -->
                                            <td class="px-4 py-3">
                                                <div class="text-gray-600 uppercase text-[11px] font-semibold tracking-wide">
                                                    {{ formatDate(record.date) }}
                                                </div>
                                                <div class="text-[12px] text-gray-500 font-mono mt-1 flex items-center gap-2">
                                                    <span title="Hora de entrada registrada" class="flex items-center gap-0.5">
                                                        <i class="fa-solid fa-arrow-right-to-bracket text-emerald-500"></i>
                                                        {{ formatTime(record.incidence.check_in) }}
                                                    </span>
                                                    <span title="Hora de salida registrada" class="flex items-center gap-0.5">
                                                        <i class="fa-solid fa-arrow-right-from-bracket text-rose-500"></i>
                                                        {{ formatTime(record.incidence.check_out) }}
                                                    </span>
                                                </div>
                                            </td>
                                            
                                            <!-- Solicitado Original -->
                                            <td class="px-4 py-3 text-center align-top pt-4">
                                                <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-mono text-xs font-bold border border-amber-200 shadow-sm">
                                                    {{ record.requestedStr }}
                                                </span>
                                            </td>

                                            <!-- Ajuste Manual -->
                                            <td class="px-4 py-2 bg-indigo-50/30 border-x border-indigo-50 align-top pt-3">
                                                <div class="flex items-center justify-center gap-2" v-if="editableRecords[`${record.user.id}_${record.date}`]">
                                                    <div class="flex items-center">
                                                        <el-input-number 
                                                            v-model="editableRecords[`${record.user.id}_${record.date}`].hours" 
                                                            :min="0" size="small" class="!w-20 shadow-sm" controls-position="right" 
                                                        />
                                                        <span class="text-xs text-indigo-500 font-bold ml-1">h</span>
                                                    </div>
                                                    <div class="flex items-center">
                                                        <el-input-number 
                                                            v-model="editableRecords[`${record.user.id}_${record.date}`].minutes" 
                                                            :min="0" :max="59" size="small" class="!w-20 shadow-sm" controls-position="right" 
                                                        />
                                                        <span class="text-xs text-indigo-500 font-bold ml-1">m</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Comentario -->
                                            <td class="px-4 py-2 align-top pt-3">
                                                <el-input 
                                                    v-if="editableRecords[`${record.user.id}_${record.date}`]"
                                                    v-model="editableRecords[`${record.user.id}_${record.date}`].comments" 
                                                    type="textarea" :rows="2" resize="none"
                                                    placeholder="Ej. Proyecto Alpha..." 
                                                    class="text-xs shadow-sm"
                                                />
                                            </td>

                                            <!-- Botones de Acción -->
                                            <td class="px-4 py-3 text-center align-top pt-4">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button 
                                                        @click="rejectSingle(record)"
                                                        :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                                        class="w-8 h-8 rounded bg-white text-red-500 border border-red-200 hover:bg-red-50 transition-colors shadow-sm focus:outline-none disabled:opacity-50 flex justify-center items-center"
                                                        title="Rechazar y marcar en 0h"
                                                    >
                                                        <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'reject'" class="fa-solid fa-spinner animate-spin"></i>
                                                        <i v-else class="fa-solid fa-xmark"></i>
                                                    </button>
                                                    <button 
                                                        @click="approveSingle(record)"
                                                        :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                                        class="w-8 h-8 rounded bg-indigo-50 text-indigo-600 border border-indigo-200 hover:bg-indigo-100 transition-colors shadow-sm focus:outline-none disabled:opacity-50 flex justify-center items-center"
                                                        title="Aprobar con ajuste"
                                                    >
                                                        <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'approve'" class="fa-solid fa-spinner animate-spin"></i>
                                                        <i v-else class="fa-solid fa-check"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </template>
                            </table>
                        </div>
                    </div>
                </el-tab-pane>

                <!-- PESTAÑA: RESUELTOS -->
                <el-tab-pane name="resolved" :disabled="isProcessing">
                    <template #label>
                        <span class="flex items-center gap-2 font-semibold text-gray-500">
                            <i class="fa-solid fa-clipboard-check"></i> Historial Resueltos
                        </span>
                    </template>

                    <div v-if="resolvedRecords.length === 0" class="py-12 text-center text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-300 mt-2">
                        <i class="fa-regular fa-folder-open text-4xl text-gray-300 mb-3 block"></i>
                        <p class="font-medium">No hay historial de resoluciones en esta nómina.</p>
                    </div>

                    <div v-else class="mt-2 border border-gray-200 rounded-lg overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-gray-100 text-gray-600 uppercase text-[10px] tracking-wider border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 w-[20%]">Fecha</th>
                                    <th class="px-4 py-3 w-[15%] text-center">Estatus</th>
                                    <th class="px-4 py-3 w-[15%] text-center">Otorgado</th>
                                    <th class="px-4 py-3 w-[40%]">Comentarios / Aprobado por</th>
                                    <th class="px-4 py-3 w-[10%] text-center">Acción</th>
                                </tr>
                            </thead>
                            
                            <template v-for="group in groupedResolvedRecords" :key="group.user.id">
                                <tbody class="divide-y divide-gray-200 border-t-[3px] border-gray-300">
                                    <!-- Header del Colaborador -->
                                    <tr class="bg-gray-100 border-b border-gray-200">
                                        <td colspan="5" class="px-4 py-2">
                                            <div class="flex items-center gap-3">
                                                <img :src="group.user.profile_photo_url" class="h-8 w-8 rounded-full border border-gray-300 object-cover shadow-sm">
                                                <div>
                                                    <p class="font-bold text-gray-700 text-sm uppercase leading-tight">{{ group.user.name }}</p>
                                                    <p class="text-[10px] text-gray-500 font-mono mt-0.5">ID: {{ group.user.id }} | {{ group.user.org_props?.department || 'General' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Registros de Días -->
                                    <tr v-for="record in group.records" :key="`${record.user.id}_${record.date}`" class="hover:bg-gray-50 transition-colors">
                                        
                                        <!-- Fecha y Horas de Registro -->
                                        <td class="px-4 py-3">
                                            <div class="text-gray-600 uppercase text-[11px] font-semibold tracking-wide">
                                                {{ formatDate(record.date) }}
                                            </div>
                                            <div class="text-[12px] text-gray-500 font-mono mt-1 flex items-center gap-2">
                                                <span title="Hora de entrada registrada" class="flex items-center gap-0.5">
                                                    <i class="fa-solid fa-arrow-right-to-bracket text-emerald-500"></i>
                                                    {{ formatTime(record.incidence.check_in) }}
                                                </span>
                                                <span title="Hora de salida registrada" class="flex items-center gap-0.5">
                                                    <i class="fa-solid fa-arrow-right-from-bracket text-rose-500"></i>
                                                    {{ formatTime(record.incidence.check_out) }}
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <td class="px-4 py-3 text-center align-top pt-4">
                                            <span v-if="record.isRejected" class="bg-red-100 text-red-700 px-2 py-0.5 rounded font-bold text-[10px] uppercase border border-red-200 shadow-sm">
                                                Rechazado
                                            </span>
                                            <span v-else class="bg-green-100 text-green-700 px-2 py-0.5 rounded font-bold text-[10px] uppercase border border-green-200 shadow-sm">
                                                Aprobado
                                            </span>
                                        </td>

                                        <td class="px-4 py-3 text-center align-top pt-4">
                                            <span class="font-mono text-xs font-bold text-gray-700">
                                                {{ record.approvedStr }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3 whitespace-normal align-top pt-3">
                                            <div class="bg-white border border-gray-100 p-2 rounded shadow-sm">
                                                <p class="text-[11px] italic text-gray-600 leading-tight">"{{ record.commentText }}"</p>
                                                <p class="text-[9px] text-gray-400 mt-1.5 uppercase font-semibold border-t border-gray-100 pt-1">
                                                    Resuelto por: {{ record.incidence.approver?.name || 'Admin' }}
                                                </p>
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 text-center align-top pt-5">
                                            <button 
                                                @click="revertSingle(record)"
                                                :disabled="isProcessing || processingRow === `${record.user.id}_${record.date}`"
                                                class="text-xs font-bold text-indigo-500 hover:text-indigo-700 underline focus:outline-none disabled:opacity-50 flex items-center justify-center mx-auto gap-1"
                                            >
                                                <i v-if="processingRow === `${record.user.id}_${record.date}` && processingType === 'revert'" class="fa-solid fa-spinner animate-spin"></i>
                                                <span v-else>Revertir</span>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </template>
                        </table>
                    </div>
                </el-tab-pane>

            </el-tabs>
        </div>
    </el-dialog>
</template>

<style scoped>
:deep(.el-input__wrapper) {
    border-radius: 0.375rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) inset;
}
:deep(.el-textarea__inner) {
    border-radius: 0.375rem;
}
</style>