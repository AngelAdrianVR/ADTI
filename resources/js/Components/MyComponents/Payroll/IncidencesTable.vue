<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { format, isSameDay, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElNotification } from 'element-plus';

const props = defineProps({
    payrollUser: {
        type: Object,
        required: true
    },
    payroll: {
        type: Object,
        required: true
    },
    canEdit: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['edit-comment']);

// State
const isOpen = ref(false); 
const showAttendanceModal = ref(false);
const showApproveModal = ref(false); 
const incidences = ref(['Falta injustificada', 'Falta justificada', 'Incapacidad', 'Permiso sin goce', 'Permiso con goce', 'Vacaciones', 'Descanso', 'Día festivo']);

const form = useForm({
    date: null,
    check_in: null,
    check_out: null,
    incidence: null,
    user_id: props.payrollUser.user.id,
    payroll_id: props.payroll.id,
});

// Formulario para aprobar horas extras
const approveForm = useForm({
    date: null,
    user_id: props.payrollUser.user.id,
    payroll_id: props.payroll.id,
    approved_extra_hours: 0,
    approved_extra_minutes: 0,
    comments: '',
});

// Computed Stats (Separado en Aprobado y Pendiente)
const stats = computed(() => {
    let extraMinutesApproved = 0;
    let extraMinutesPending = 0;
    let lateMinutes = 0;

    props.payrollUser.incidences.forEach(day => {
        // Separar lógica de extras
        if (day.approved_at) {
            extraMinutesApproved += (day.approved_extra_hours || 0) * 60 + (day.approved_extra_minutes || 0);
        } else if (day.extra_hours || day.extra_minutes) {
            extraMinutesPending += (day.extra_hours || 0) * 60 + (day.extra_minutes || 0);
        }

        // Sumar retardos
        if (day.late) {
            lateMinutes += day.late;
        }
    });

    const formatMins = (mins) => {
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        return `${h}h ${m}m`;
    };

    return {
        extraApproved: formatMins(extraMinutesApproved),
        extraPending: formatMins(extraMinutesPending),
        late: formatMins(lateMinutes),
    };
});

// Methods
const toggleAccordion = () => {
    isOpen.value = !isOpen.value;
};

const formatDate = (date) => {
    return format(new Date(date), 'dd MMM', { locale: es });
};

const getDayName = (date) => {
    return format(new Date(date), 'EEEE', { locale: es });
};

const getIncidenceColor = (incidence) => {
    if (!incidence) return 'bg-white';
    if (incidence.check_in && incidence.check_out) return 'bg-green-50 border-green-200';
    if (incidence.incidence === 'Falta injustificada') return 'bg-red-50 border-red-200';
    if (incidence.incidence === 'Vacaciones') return 'bg-blue-50 border-blue-200';
    if (incidence.incidence === 'Descanso') return 'bg-gray-50 border-gray-200';
    return 'bg-amber-50 border-amber-200';
};

const handleCommand = (command) => {
    const [action, date] = command.split('|');
    form.date = date.split('T')[0];

    if (action === 'edit_time') {
        const register = props.payrollUser.incidences.find(i => isSameDay(parseISO(i.date), parseISO(date)));
        if (register) {
            form.check_in = register.check_in?.substring(0, 5) || null;
            form.check_out = register.check_out?.substring(0, 5) || null;
        } else {
            form.check_in = null;
            form.check_out = null;
        }
        showAttendanceModal.value = true;
    } else if (action === 'remove_late') {
        removeLate();
    } else if (action === 'edit_comment') {
        const register = props.payrollUser.incidences.find(i => isSameDay(parseISO(i.date), parseISO(date)));
        const currentComment = register?.comment?.comments || '';
        
        emit('edit-comment', {
            userId: props.payrollUser.user.id,
            userName: props.payrollUser.user.name,
            date: form.date,
            comments: currentComment
        });
    } else if (action === 'approve_extra_time') {
        const register = props.payrollUser.incidences.find(i => isSameDay(parseISO(i.date), parseISO(date)));
        approveForm.date = form.date;
        approveForm.approved_extra_hours = register.extra_hours || 0;
        approveForm.approved_extra_minutes = register.extra_minutes || 0;
        approveForm.comments = register?.comment?.comments || '';
        showApproveModal.value = true;
    } else if (action === 'revert_extra_time') {
        router.put(route('payroll-users.revert-extra-time'), {
            date: form.date,
            user_id: props.payrollUser.user.id,
        }, {
            preserveScroll: true,
            onSuccess: () => ElNotification.success('Aprobación de horas revertida'),
        });
    } else {
        // Es una incidencia directa
        form.incidence = action;
        setIncidence();
    }
};

const setIncidence = () => {
    form.put(route('payroll-users.set-incidence'), {
        onSuccess: () => {
            ElNotification.success('Incidencia actualizada');
            form.reset();
        },
        onError: () => ElNotification.error('Error al actualizar'),
    });
};

const updateAttendance = () => {
    form.put(route('payroll-users.update-attendance'), {
        onSuccess: () => {
            ElNotification.success('Asistencia actualizada');
            showAttendanceModal.value = false;
            form.reset();
        },
    });
};

const removeLate = () => {
    form.put(route('payroll-users.remove-late'), {
        onSuccess: () => ElNotification.success('Retardo eliminado'),
    });
};

const submitApproveExtraTime = () => {
    approveForm.put(route('payroll-users.approve-extra-time'), {
        preserveScroll: true,
        onSuccess: () => {
            ElNotification.success('Tiempo extra aprobado');
            showApproveModal.value = false;
            approveForm.reset();
        }
    });
};
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden transition-all duration-300">
        
        <!-- Header (Resumen Compacto) -->
        <div 
            @click="toggleAccordion" 
            class="p-4 flex flex-col md:flex-row items-center justify-between cursor-pointer hover:bg-gray-50 select-none gap-4"
        >
            <!-- Info Usuario -->
            <div class="flex items-center gap-4 w-full md:w-1/3">
                <div class="relative">
                    <img 
                        :src="payrollUser.user.profile_photo_url" 
                        class="h-10 w-10 rounded-full object-cover border border-gray-200" 
                        alt="Avatar"
                    >
                    <span v-if="payrollUser.user.paused" class="absolute -bottom-1 -right-1 flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                    </span>
                </div>
                <div>
                    <!-- Nombre y Etiqueta de "Sin Asistencia" -->
                    <h3 class="text-sm font-bold text-gray-800 leading-tight flex items-center flex-wrap gap-2">
                        {{ payrollUser.user.name }}
                        <span v-if="!payrollUser.user.has_attendances" 
                              class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-50 text-red-600 border border-red-100 uppercase tracking-wider" 
                              title="Sin registros de asistencia en esta catorcena">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin Asistencia
                        </span>
                    </h3>
                    <p class="text-xs text-gray-500">{{ payrollUser.user.org_props.department }}</p>
                </div>
            </div>

            <!-- Stats (Compacto) -->
            <div class="flex items-center gap-4 lg:gap-6 text-xs text-gray-600 w-full md:w-auto justify-end">
                <div class="flex flex-col items-end">
                    <span class="uppercase text-[10px] text-gray-400 font-bold">Retardos</span>
                    <span :class="stats.late !== '0h 0m' ? 'text-red-500 font-bold' : ''">{{ stats.late }}</span>
                </div>
                <div class="flex flex-col items-end">
                    <span class="uppercase text-[10px] text-gray-400 font-bold">T. E. (Pend)</span>
                    <span :class="stats.extraPending !== '0h 0m' ? 'text-amber-500 font-bold' : ''">{{ stats.extraPending }}</span>
                </div>
                <div class="flex flex-col items-end">
                    <span class="uppercase text-[10px] text-green-600 font-bold">T. E. (Aprob)</span>
                    <span :class="stats.extraApproved !== '0h 0m' ? 'text-green-600 font-bold' : ''">{{ stats.extraApproved }}</span>
                </div>
                
                <i 
                    class="fa-solid fa-chevron-down text-gray-400 transition-transform duration-300 ml-2"
                    :class="{'rotate-180': isOpen}"
                ></i>
            </div>
        </div>

        <!-- Body (Detalle 14 Días) -->
        <div v-show="isOpen" class="border-t border-gray-100 bg-gray-50/50 p-4">
            
            <!-- Grid de Días -->
            <div class="overflow-x-auto pb-2">
                <div class="flex gap-2 min-w-max">
                    <div 
                        v-for="(day, index) in payrollUser.incidences" 
                        :key="index" 
                        class="flex flex-col w-32 border rounded-lg bg-white overflow-hidden shadow-sm transition-all hover:shadow-md relative"
                        :class="getIncidenceColor(day)"
                    >
                        <!-- Indicador de Comentario -->
                        <div v-if="day.comment" class="absolute top-1 left-1 z-10">
                            <el-tooltip :content="day.comment.comments" placement="top" effect="dark">
                                <i class="fa-solid fa-comment-dots text-indigo-500 text-xs drop-shadow-sm"></i>
                            </el-tooltip>
                        </div>

                        <!-- Fecha Header -->
                        <div class="text-center py-1.5 text-[10px] uppercase font-bold tracking-wider border-b border-gray-100" :class="day.check_in ? 'bg-gray-50 text-gray-600' : 'bg-white text-gray-400'">
                            {{ getDayName(day.date) }} <span class="text-gray-800">{{ formatDate(day.date) }}</span>
                        </div>

                        <!-- Contenido Día -->
                        <div class="flex-1 p-2 flex flex-col justify-center items-center text-xs gap-1 min-h-[85px] relative group">
                            
                            <!-- Dropdown de Acciones (canEdit) -->
                            <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity z-10" v-if="canEdit">
                                <el-dropdown trigger="click" @command="handleCommand" size="small">
                                    <button class="p-1 hover:bg-gray-200 rounded text-gray-400 hover:text-gray-600">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            
                                            <!-- ACCIONES TIEMPO EXTRA (Requieren permiso) -->
                                            <template v-if="$page.props.auth.user.permissions.includes('Aprobar tiempo extra')">
                                                <el-dropdown-item v-if="(day.extra_hours || day.extra_minutes) && !day.approved_at" :command="`approve_extra_time|${day.date}`">
                                                    <i class="fa-solid fa-check-circle mr-2 text-green-600"></i> Aprobar Extra
                                                </el-dropdown-item>
                                                <el-dropdown-item v-if="day.approved_at" :command="`revert_extra_time|${day.date}`">
                                                    <i class="fa-solid fa-rotate-left mr-2 text-red-600"></i> Revertir Extra
                                                </el-dropdown-item>
                                                <el-dropdown-item divided v-if="day.extra_hours || day.extra_minutes"></el-dropdown-item>
                                            </template>

                                            <!-- OTRAS ACCIONES -->
                                            <el-dropdown-item :command="`edit_time|${day.date}`">
                                                <i class="fa-regular fa-clock mr-2"></i> Editar Horas
                                            </el-dropdown-item>
                                            <el-dropdown-item :command="`edit_comment|${day.date}`">
                                                <i class="fa-regular fa-comment mr-2"></i> Comentario
                                            </el-dropdown-item>
                                            <el-dropdown-item v-if="day.late" :command="`remove_late|${day.date}`">
                                                <i class="fa-solid fa-eraser mr-2"></i> Quitar Retardo
                                            </el-dropdown-item>
                                            <el-dropdown-item divided disabled>Incidencias</el-dropdown-item>
                                            <el-dropdown-item v-for="inc in incidences" :key="inc" :command="`${inc}|${day.date}`">
                                                {{ inc }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>

                            <!-- Estado / Horas -->
                            <template v-if="day.check_in || day.check_out">
                                <div class="text-gray-800 font-mono font-bold text-[11px]">{{ day.check_in?.substring(0, 5) || '??' }} - {{ day.check_out?.substring(0, 5) || '??' }}</div>
                                <div v-if="day.late" class="text-[10px] text-red-500 bg-red-50 px-1.5 rounded border border-red-100">
                                    Retardo: {{ day.late }}m
                                </div>

                                <!-- UI de Tiempo Extra -->
                                <div v-if="day.approved_at" class="text-[10px] text-green-700 bg-green-100 px-1.5 py-0.5 rounded border border-green-300 font-semibold text-center leading-tight" title="Aprobado">
                                    T.E. Aprobado:<br>{{ day.approved_extra_hours }}h {{ day.approved_extra_minutes }}m <i class="fa-solid fa-check-circle ml-0.5"></i>
                                    <div class="text-[8px] mt-0.5 font-normal text-green-700 border-t border-green-200 pt-0.5" title="Persona que aprobó">
                                        Por: {{ day.approver?.name?.split(' ')[0] || 'Admin' }}
                                    </div>
                                    <div v-if="day.comment" class="text-[8px] mt-0.5 font-normal text-gray-600 border-t border-green-200 pt-0.5 italic text-left" style="white-space: normal; line-height: 1.2;">
                                        "{{ day.comment.comments }}"
                                    </div>
                                </div>
                                <div v-else-if="day.extra_hours || day.extra_minutes" class="text-[10px] text-amber-600 bg-amber-50 px-1.5 rounded border border-amber-200 text-center leading-tight" title="Pendiente de aprobación">
                                    Extra (Pend.):<br>{{ day.extra_hours }}h {{ day.extra_minutes }}m
                                </div>

                            </template>
                            <template v-else>
                                <span class="text-center font-medium" :class="day.incidence === 'Falta injustificada' ? 'text-red-500' : 'text-gray-400'">
                                    {{ day.incidence || '-' }}
                                </span>
                            </template>

                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL 1: Modificar Asistencia (Element Plus) -->
        <el-dialog
            v-model="showAttendanceModal"
            title="Modificar Asistencia"
            width="400px"
            class="!rounded-xl"
            destroy-on-close
        >
            <div class="grid grid-cols-2 gap-6 py-2">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Hora de Entrada</label>
                    <el-time-picker
                        v-model="form.check_in"
                        format="HH:mm"
                        value-format="HH:mm"
                        placeholder="00:00"
                        class="!w-full"
                        clearable
                    />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Hora de Salida</label>
                    <el-time-picker
                        v-model="form.check_out"
                        format="HH:mm"
                        value-format="HH:mm"
                        placeholder="00:00"
                        class="!w-full"
                        clearable
                    />
                </div>
            </div>
            
            <template #footer>
                <div class="flex justify-end gap-2 pt-2">
                    <el-button @click="showAttendanceModal = false">Cancelar</el-button>
                    <el-button 
                        type="primary" 
                        @click="updateAttendance" 
                        :loading="form.processing"
                        class="!bg-indigo-600 !border-indigo-600"
                    >
                        Guardar Cambios
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- MODAL 2: Aprobar Tiempo Extra (Element Plus) -->
        <el-dialog
            v-model="showApproveModal"
            title="Aprobar Tiempo Extra"
            width="450px"
            class="!rounded-xl"
            destroy-on-close
        >
            <div class="mb-5 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-2 items-start">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <p>Modifica el tiempo a aprobar si es necesario y opcionalmente agrega un comentario o justificación.</p>
            </div>

            <div class="grid grid-cols-2 gap-6 mb-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Horas aprobadas</label>
                    <el-input-number 
                        v-model="approveForm.approved_extra_hours" 
                        :min="0" 
                        class="!w-full" 
                        controls-position="right"
                    />
                    <span v-if="approveForm.errors.approved_extra_hours" class="text-xs text-red-500 mt-1 block">{{ approveForm.errors.approved_extra_hours }}</span>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Minutos aprobados</label>
                    <el-input-number 
                        v-model="approveForm.approved_extra_minutes" 
                        :min="0" 
                        :max="59" 
                        class="!w-full" 
                        controls-position="right"
                    />
                    <span v-if="approveForm.errors.approved_extra_minutes" class="text-xs text-red-500 mt-1 block">{{ approveForm.errors.approved_extra_minutes }}</span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Comentarios o Justificación (Opcional)</label>
                <el-input 
                    v-model="approveForm.comments" 
                    type="textarea" 
                    :rows="3" 
                    placeholder="Ej. Se autoriza por cierre de inventario de almacén." 
                />
                <span v-if="approveForm.errors.comments" class="text-xs text-red-500 mt-1 block">{{ approveForm.errors.comments }}</span>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-2">
                    <el-button @click="showApproveModal = false">Cancelar</el-button>
                    <el-button 
                        type="primary" 
                        @click="submitApproveExtraTime" 
                        :loading="approveForm.processing"
                        class="!bg-indigo-600 !border-indigo-600"
                    >
                        Aprobar Tiempo
                    </el-button>
                </div>
            </template>
        </el-dialog>

    </div>
</template>

<style scoped>
/* Asegura que los modales de Element Plus tengan un aspecto más limpio con los forms */
:deep(.el-input__wrapper) {
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px #e5e7eb inset;
}
:deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px #4f46e5 inset !important;
}
</style>