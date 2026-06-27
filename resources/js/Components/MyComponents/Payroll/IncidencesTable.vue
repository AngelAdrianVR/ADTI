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
    },
    // Niveles de autorización configurados para esta nómina
    approvalLevels: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['edit-comment']);

// State
const isOpen = ref(false); 
const showAttendanceModal = ref(false);
const showApproveModal = ref(false);
// Control de visibilidad del tiempo extra en la UI
const showExtraTime = ref(true);
const incidences = ref(['Falta injustificada', 'Falta justificada', 'Incapacidad', 'Permiso sin goce', 'Permiso con goce', 'Vacaciones', 'Descanso', 'Día festivo', 'Salió de Viaje']);

const form = useForm({
    date: null,
    check_in: null,
    check_out: null,
    incidence: null,
    user_id: props.payrollUser.user.id,
    payroll_id: props.payroll.id,
});

// Formulario para aprobar o rechazar horas extras
const approveForm = useForm({
    date: null,
    user_id: props.payrollUser.user.id,
    payroll_id: props.payroll.id,
    approved_extra_hours: 0,
    approved_extra_minutes: 0,
    comments: '',
});

// Computed Stats
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
    if (incidence.incidence === 'Salió de Viaje') return 'bg-purple-50 border-purple-200';
    return 'bg-amber-50 border-amber-200';
};

// --- Helpers para niveles de aprobación ---
// Obtiene el resumen de aprobación para un día específico
const getDayApprovalSummary = (day) => {
    if (!props.approvalLevels || props.approvalLevels.length === 0) return null;
    if (!day.approval_decisions || day.approval_decisions.length === 0) return null;

    const levels = props.approvalLevels.map(level => {
        const decisions = day.approval_decisions.filter(d => d.level_id === level.id);
        const allApproved = decisions.length > 0 && decisions.every(d => d.status === 'approved');
        const hasRejection = decisions.some(d => d.status === 'rejected');
        const pending = decisions.length === 0 || decisions.some(d => d.status === 'pending');

        return {
            ...level,
            decisions,
            status: hasRejection ? 'rejected' : (allApproved ? 'approved' : 'pending'),
        };
    });

    // Determinar estado global
    const globalRejected = levels.some(l => l.status === 'rejected');
    const allLevelsApproved = levels.every(l => l.status === 'approved');

    return {
        levels,
        globalStatus: globalRejected ? 'rejected' : (allLevelsApproved ? 'approved' : 'pending'),
    };
};

// Obtiene el color del badge de estado de aprobación
const getApprovalStatusBadge = (status) => {
    switch (status) {
        case 'approved': return { bg: 'bg-green-100', text: 'text-green-700', border: 'border-green-300', icon: 'fa-check-circle', label: 'Aprobado' };
        case 'rejected': return { bg: 'bg-red-100', text: 'text-red-700', border: 'border-red-300', icon: 'fa-xmark-circle', label: 'Rechazado' };
        default: return { bg: 'bg-amber-100', text: 'text-amber-700', border: 'border-amber-300', icon: 'fa-clock', label: 'Pendiente' };
    }
};

// --- Helpers para validación GPS ---
const isValidLocation = (loc) => {
    if (!loc) return false;
    // Asumimos válido si tiene coordenadas (contiene coma) y no es un mensaje de error del navegador
    return loc.includes(',') && !loc.includes('denegada') && !loc.includes('Soportado') && !loc.includes('disponible') && !loc.includes('agotado');
};

const getLocationError = (loc) => {
    if (!loc) return null;
    // Si no es válido según la lógica anterior, asumimos que es el string de error
    if (!isValidLocation(loc)) return loc;
    return null;
};
// -----------------------------------

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
            onSuccess: () => ElNotification.success('Resolución de horas revertida'),
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

const submitRejectExtraTime = () => {
    approveForm.put(route('payroll-users.reject-extra-time'), {
        preserveScroll: true,
        onSuccess: () => {
            ElNotification.success('Tiempo extra rechazado correctamente');
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
                    <h3 class="text-sm font-bold text-gray-800 leading-tight flex items-center flex-wrap gap-2">
                        {{ payrollUser.user.name }}
                        <span v-if="!payrollUser.user.has_attendances" 
                              class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-50 text-red-600 border border-red-100 uppercase tracking-wider" 
                              title="Sin registros de asistencia en esta catorcena">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin Asistencia
                        </span>
                    </h3>
                    <p class="text-xs text-gray-500">{{ payrollUser.user.org_props?.department || 'General' }}</p>
                </div>
            </div>

            <!-- Stats (Compacto) -->
            <div class="flex items-center gap-4 lg:gap-6 text-xs text-gray-600 w-full md:w-auto justify-end">
                <div class="flex flex-col items-end">
                    <span class="uppercase text-[10px] text-gray-400 font-bold">Retardos</span>
                    <span :class="stats.late !== '0h 0m' ? 'text-red-500 font-bold' : ''">{{ stats.late }}</span>
                </div>
                <!-- Toggle para ocultar/mostrar tiempo extra -->
                <button 
                    @click.stop="showExtraTime = !showExtraTime" 
                    class="flex flex-col items-end group cursor-pointer"
                    :title="showExtraTime ? 'Ocultar tiempo extra' : 'Mostrar tiempo extra'"
                >
                    <span class="uppercase text-[10px] font-bold transition-colors" 
                          :class="showExtraTime ? 'text-gray-400' : 'text-gray-300'">
                        <i :class="showExtraTime ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash'" class="mr-0.5"></i> T.E.
                    </span>
                </button>
                <div v-show="showExtraTime" class="flex flex-col items-end">
                    <span class="uppercase text-[10px] text-gray-400 font-bold">T. E. (Pend)</span>
                    <span :class="stats.extraPending !== '0h 0m' ? 'text-amber-500 font-bold' : ''">{{ stats.extraPending }}</span>
                </div>
                <div v-show="showExtraTime" class="flex flex-col items-end">
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
                                                    <i class="fa-solid fa-list-check mr-2 text-indigo-600"></i> Gestionar extra
                                                </el-dropdown-item>
                                                <el-dropdown-item v-if="day.approved_at && (day.extra_hours || day.extra_minutes)" :command="`revert_extra_time|${day.date}`">
                                                    <i class="fa-solid fa-rotate-left mr-2 text-red-600"></i> Revertir resolución
                                                </el-dropdown-item>
                                                <el-dropdown-item divided v-if="day.extra_hours || day.extra_minutes"></el-dropdown-item>
                                            </template>

                                            <!-- OTRAS ACCIONES -->
                                            <el-dropdown-item :command="`edit_time|${day.date}`">
                                                <i class="fa-regular fa-clock mr-2"></i> Editar/eliminar horas
                                            </el-dropdown-item>
                                            <el-dropdown-item :command="`edit_comment|${day.date}`">
                                                <i class="fa-regular fa-comment mr-2"></i> Comentario
                                            </el-dropdown-item>
                                            <el-dropdown-item v-if="day.late" :command="`remove_late|${day.date}`">
                                                <i class="fa-solid fa-eraser mr-2"></i> Quitar retardo
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
                                
                                <!-- Desglose de Horas y Marcadores GPS -->
                                <div class="flex items-center gap-1.5 justify-center mt-0.5">
                                    
                                    <!-- Check-in -->
                                    <div class="flex items-center gap-0.5">
                                        <span class="text-gray-800 font-mono font-bold text-[11px]">{{ day.check_in?.substring(0, 5) || '??' }}</span>
                                        
                                        <!-- Validadores GPS Entrada -->
                                        <el-tooltip v-if="isValidLocation(day.check_in_location)" content="Ver ubicación de entrada" placement="top">
                                            <a :href="`https://www.google.com/maps/search/?api=1&query=${day.check_in_location}`" target="_blank" class="text-blue-500 hover:text-blue-700 transition-colors" @click.stop>
                                                <i class="fa-solid fa-location-dot text-[9px]"></i>
                                            </a>
                                        </el-tooltip>
                                        <el-tooltip v-else-if="getLocationError(day.check_in_location)" :content="`Error GPS: ${getLocationError(day.check_in_location)}`" placement="top">
                                            <i class="fa-solid fa-location-crosshairs text-red-400 text-[9px] cursor-help"></i>
                                        </el-tooltip>
                                    </div>
                                    
                                    <span class="text-gray-400 text-[10px]">-</span>
                                    
                                    <!-- Check-out -->
                                    <div class="flex items-center gap-0.5">
                                        <span class="text-gray-800 font-mono font-bold text-[11px]">{{ day.check_out?.substring(0, 5) || '??' }}</span>
                                        
                                        <!-- Validadores GPS Salida -->
                                        <el-tooltip v-if="isValidLocation(day.check_out_location)" content="Ver ubicación de salida" placement="top">
                                            <a :href="`https://www.google.com/maps/search/?api=1&query=${day.check_out_location}`" target="_blank" class="text-blue-500 hover:text-blue-700 transition-colors" @click.stop>
                                                <i class="fa-solid fa-location-dot text-[9px]"></i>
                                            </a>
                                        </el-tooltip>
                                        <el-tooltip v-else-if="getLocationError(day.check_out_location)" :content="`Error GPS: ${getLocationError(day.check_out_location)}`" placement="top">
                                            <i class="fa-solid fa-location-crosshairs text-red-400 text-[9px] cursor-help"></i>
                                        </el-tooltip>
                                    </div>

                                </div>

                                <div v-if="day.late" class="text-[10px] text-red-500 bg-red-50 px-1.5 rounded border border-red-100 mt-1">
                                    Retardo: {{ day.late }}m
                                </div>

                                <!-- UI de Tiempo Extra (Ocultable) -->
                                <template v-if="showExtraTime">
                                    <div v-if="day.approved_at && (day.extra_hours || day.extra_minutes)">
                                        <!-- Aprobado con 0 horas/minutos = RECHAZADO -->
                                        <div v-if="day.approved_extra_hours === 0 && day.approved_extra_minutes === 0" class="text-[10px] text-red-700 bg-red-100 px-1.5 py-0.5 rounded border border-red-300 font-semibold text-center leading-tight w-full mt-1" title="Tiempo Extra Rechazado">
                                            T.E. Rechazado <i class="fa-solid fa-xmark ml-0.5"></i>
                                            <div class="text-[8px] mt-0.5 font-normal text-red-700 border-t border-red-200 pt-0.5" title="Persona que rechazó">
                                                Por: {{ day.approver?.name?.split(' ')[0] || 'Admin' }}
                                            </div>
                                        </div>
                                        
                                        <!-- Aprobado con Horas/Minutos > 0 = APROBADO -->
                                        <div v-else class="text-[10px] text-green-700 bg-green-100 px-1.5 py-0.5 rounded border border-green-300 font-semibold text-center leading-tight w-full mt-1" title="Tiempo Extra Aprobado">
                                            T.E. Aprobado:<br>{{ day.approved_extra_hours }}h {{ day.approved_extra_minutes }}m <i class="fa-solid fa-check-circle ml-0.5"></i>
                                            <div class="text-[8px] mt-0.5 font-normal text-green-700 border-t border-green-200 pt-0.5" title="Persona que aprobó">
                                                Por: {{ day.approver?.name?.split(' ')[0] || 'Admin' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="day.extra_hours || day.extra_minutes" class="text-[10px] text-amber-600 bg-amber-50 px-1.5 rounded border border-amber-200 text-center leading-tight mt-1" title="Pendiente de aprobación">
                                        Extra:<br>{{ day.extra_hours }}h {{ day.extra_minutes }}m
                                        <!-- Costo por hora si está configurado -->
                                        <span v-if="day.cost_per_hour" class="block text-[8px] text-amber-500 mt-0.5">
                                            ${{ day.cost_per_hour }}/hr
                                        </span>
                                    </div>

                                    <!-- Niveles de Autorización (solo si hay niveles configurados y tiempo extra) -->
                                    <div v-if="(day.extra_hours || day.extra_minutes) && getDayApprovalSummary(day)" class="mt-1.5 border-t border-gray-100 pt-1">
                                        <div class="flex flex-col gap-0.5">
                                            <div 
                                                v-for="level in getDayApprovalSummary(day).levels" 
                                                :key="level.id"
                                                class="flex items-center gap-1 text-[8px]"
                                                :title="`${level.name || 'Nivel ' + level.level}: ${getApprovalStatusBadge(level.status).label}`"
                                            >
                                                <!-- Badge de estado del nivel -->
                                                <span class="flex-shrink-0 w-3.5 h-3.5 rounded-full flex items-center justify-center text-[6px]"
                                                      :class="getApprovalStatusBadge(level.status).bg + ' ' + getApprovalStatusBadge(level.status).text">
                                                    <i :class="'fa-solid ' + getApprovalStatusBadge(level.status).icon"></i>
                                                </span>
                                                <!-- Avatares de aprobadores del nivel -->
                                                <span class="text-gray-500 font-medium truncate">{{ level.name || 'N' + level.level }}:</span>
                                                <div class="flex -space-x-1.5">
                                                    <el-tooltip 
                                                        v-for="approver in level.approvers" 
                                                        :key="approver.id"
                                                        :content="approver.name"
                                                        placement="top"
                                                    >
                                                        <img 
                                                            :src="approver.profile_photo_url" 
                                                            class="w-4 h-4 rounded-full border border-white object-cover"
                                                            :alt="approver.name"
                                                        >
                                                    </el-tooltip>
                                                </div>
                                                <!-- Indicador de decisión -->
                                                <span v-if="level.decisions.length > 0" class="ml-auto flex -space-x-1">
                                                    <span 
                                                        v-for="dec in level.decisions" 
                                                        :key="dec.id"
                                                        class="w-3 h-3 rounded-full border border-white flex items-center justify-center text-[6px]"
                                                        :class="dec.status === 'approved' ? 'bg-green-400 text-white' : 'bg-red-400 text-white'"
                                                        :title="dec.approver.name + ': ' + (dec.status === 'approved' ? 'Aprobó' : 'Rechazó')"
                                                    >
                                                        <i :class="dec.status === 'approved' ? 'fa-solid fa-check' : 'fa-solid fa-xmark'"></i>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                            </template>
                           <template v-else>
                                <span class="text-center font-medium" :class="{
                                    'text-red-500': day.incidence === 'Falta injustificada',
                                    'text-purple-600': day.incidence === 'Salió de Viaje',
                                    'text-gray-400': !['Falta injustificada', 'Salió de Viaje'].includes(day.incidence)
                                }">
                                    {{ day.incidence || '-' }}
                                </span>
                            </template>

                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL 1: Modificar Asistencia -->
        <el-dialog
            v-model="showAttendanceModal"
            title="Modificar asistencia"
            width="400px"
            class="!rounded-xl"
            destroy-on-close
        >
        <div class="mb-5 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-2 items-start">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <p>Al borrar las horas registradas y guardar cambios, se marcará como falta en automático.</p>
            </div>
            <div class="grid grid-cols-2 gap-6 py-2">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Hora de entrada</label>
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Hora de salida</label>
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
                        Guardar cambios
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- MODAL 2: Gestionar Tiempo Extra -->
        <el-dialog
            v-model="showApproveModal"
            title="Gestionar Tiempo Extra"
            width="450px"
            class="!rounded-xl"
            destroy-on-close
        >
            <div class="mb-5 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-2 items-start">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <p>Ajusta el tiempo a aprobar, o rechaza el tiempo extra. Puedes agregar una justificación.</p>
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
                <div class="flex justify-between items-center w-full pt-2">
                    <el-button 
                        type="danger" 
                        plain
                        @click="submitRejectExtraTime" 
                        :loading="approveForm.processing"
                    >
                        <i class="fa-solid fa-xmark mr-2"></i> Rechazar
                    </el-button>
                    
                    <div class="flex gap-2">
                        <el-button @click="showApproveModal = false">Cancelar</el-button>
                        <el-button 
                            type="primary" 
                            @click="submitApproveExtraTime" 
                            :loading="approveForm.processing"
                            class="!bg-indigo-600 !border-indigo-600"
                        >
                            <i class="fa-solid fa-check mr-2"></i> Aprobar
                        </el-button>
                    </div>
                </div>
            </template>
        </el-dialog>

    </div>
</template>

<style scoped>
:deep(.el-input__wrapper) {
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px #e5e7eb inset;
}
:deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px #4f46e5 inset !important;
}
</style>