<script setup>
import { ref, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { format, isSameDay, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElNotification } from 'element-plus';
import { useIncidencesApproval } from '@/Composables/payroll/useIncidencesApproval.js';
import IncidencesDayCard from '@/Components/MyComponents/Payroll/IncidencesDayCard.vue';

const props = defineProps({
    payrollUser: { type: Object, required: true },
    payroll: { type: Object, required: true },
    canEdit: { type: Boolean, default: true },
    approvalGroups: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
});

const emit = defineEmits(['edit-comment']);

const page = usePage();

// ─── Jerarquía ───
const approval = useIncidencesApproval(computed(() => props.approvalGroups));

// ─── Control de visibilidad de montos ───
// Solo los usuarios con rol "Super admin" pueden ver montos de dinero
const canSeeMoney = computed(() => {
    const roles = page.props?.auth?.user?.roles || [];
    return roles.includes('Super admin');
});

// ─── State ───
const isOpen = ref(false);
const showAttendanceModal = ref(false);
const showApproveModal = ref(false);
const showProjectModal = ref(false);
const incidences = ref(['Falta injustificada', 'Falta justificada', 'Incapacidad', 'Permiso sin goce', 'Permiso con goce', 'Vacaciones', 'Descanso', 'Día festivo', 'Salió de Viaje']);

// ─── Drag-to-scroll ───
const scrollContainer = ref(null);
const isDragging = ref(false);
const dragStartX = ref(0);
const dragScrollLeft = ref(0);

const onDragStart = (e) => {
    isDragging.value = true;
    dragStartX.value = e.pageX - scrollContainer.value.offsetLeft;
    dragScrollLeft.value = scrollContainer.value.scrollLeft;
    scrollContainer.value.style.cursor = 'grabbing';
    scrollContainer.value.style.userSelect = 'none';
};

const onDragMove = (e) => {
    if (!isDragging.value) return;
    e.preventDefault();
    const x = e.pageX - scrollContainer.value.offsetLeft;
    const walk = (x - dragStartX.value) * 1.5; // Multiplicador para velocidad
    scrollContainer.value.scrollLeft = dragScrollLeft.value - walk;
};

const onDragEnd = () => {
    isDragging.value = false;
    if (scrollContainer.value) {
        scrollContainer.value.style.cursor = 'grab';
        scrollContainer.value.style.userSelect = '';
    }
};

const form = useForm({ date: null, check_in: null, check_out: null, break_start: null, break_end: null, incidence: null, user_id: props.payrollUser.user.id, payroll_id: props.payroll.id });
const approveForm = useForm({ date: null, user_id: props.payrollUser.user.id, payroll_id: props.payroll.id, approved_extra_hours: 0, approved_extra_minutes: 0, comments: '' });
const projectForm = useForm({ date: null, user_id: props.payrollUser.user.id, project_id: null });

// ─── Formateador de dinero con separadores de miles ───
const formatMoney = (value) => {
    if (value === null || value === undefined || value === 0) return '0.00';
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// ─── Stats ───
const stats = computed(() => {
    let extraMinutesApproved = 0, extraMinutesPending = 0, lateMinutes = 0, breakMinutes = 0, breakCount = 0;
    let extraAmountApproved = 0, extraAmountPending = 0;
    props.payrollUser.incidences.forEach(day => {
        if (day.approved_at) {
            // Solo contar aprobados con horas reales (> 0)
            if ((day.approved_extra_hours || 0) > 0 || (day.approved_extra_minutes || 0) > 0) {
                extraMinutesApproved += (day.approved_extra_hours || 0) * 60 + (day.approved_extra_minutes || 0);
                extraAmountApproved += (day.extra_amount || 0);
            }
        } else if (day.extra_hours || day.extra_minutes) {
            extraMinutesPending += (day.extra_hours || 0) * 60 + (day.extra_minutes || 0);
            extraAmountPending += (day.extra_amount || 0);
        }
        if (day.late) lateMinutes += day.late;
        if (day.break_minutes) {
            breakMinutes += day.break_minutes;
            breakCount++;
        }
    });
    const fmt = m => `${Math.floor(m / 60)}h ${m % 60}m`;
    return {
        extraApproved: fmt(extraMinutesApproved),
        extraPending: fmt(extraMinutesPending),
        late: fmt(lateMinutes),
        breakTime: fmt(breakMinutes),
        breakCount,
        extraAmountApproved: extraAmountApproved,
        extraAmountPending: extraAmountPending,
    };
});

// ─── GPS ───
const isValidLocation = (loc) => loc?.includes(',') && !loc.includes('denegada') && !loc.includes('Soportado') && !loc.includes('disponible') && !loc.includes('agotado');
const getLocationError = (loc) => (loc && !isValidLocation(loc)) ? loc : null;

// ─── Handlers ───
const toggleAccordion = () => { isOpen.value = !isOpen.value; };

const handleCommand = (command) => {
    const [action, date] = command.split('|');
    form.date = date.split('T')[0];

    if (action === 'edit_time') {
        const r = props.payrollUser.incidences.find(i => isSameDay(parseISO(i.date), parseISO(date)));
        form.check_in = r?.check_in?.substring(0, 5) || null;
        form.check_out = r?.check_out?.substring(0, 5) || null;
        form.break_start = r?.break_start?.substring(0, 5) || null;
        form.break_end = r?.break_end?.substring(0, 5) || null;
        showAttendanceModal.value = true;
    } else if (action === 'remove_late') removeLate();
    else if (action === 'edit_comment') {
        const r = props.payrollUser.incidences.find(i => isSameDay(parseISO(i.date), parseISO(date)));
        emit('edit-comment', { userId: props.payrollUser.user.id, userName: props.payrollUser.user.name, date: form.date, comments: r?.comment?.comments || '' });
    } else if (action === 'approve_extra_time') {
        const r = props.payrollUser.incidences.find(i => isSameDay(parseISO(i.date), parseISO(date)));
        approveForm.date = form.date;
        approveForm.approved_extra_hours = r?.extra_hours || 0;
        approveForm.approved_extra_minutes = r?.extra_minutes || 0;
        approveForm.comments = r?.comment?.comments || '';
        showApproveModal.value = true;
    } else if (action === 'revert_extra_time') {
        router.put(route('payroll-users.revert-extra-time'), { date: form.date, user_id: props.payrollUser.user.id }, { preserveScroll: true, onSuccess: () => ElNotification.success('Resolución revertida') });
    } else if (action === 'clear_extra_time') {
        router.put(route('payroll-users.clear-extra-time'), { date: form.date, user_id: props.payrollUser.user.id }, { preserveScroll: true, onSuccess: () => ElNotification.success('Tiempo extra eliminado') });
    } else if (action === 'link_project' || action === 'change_project') {
        const r = props.payrollUser.incidences.find(i => isSameDay(parseISO(i.date), parseISO(date)));
        projectForm.date = form.date;
        projectForm.project_id = r?.project_id || null;
        showProjectModal.value = true;
    } else if (action === 'unlink_project') {
        router.put(route('payroll-users.set-project'), { date: form.date, user_id: props.payrollUser.user.id, project_id: null }, {
            preserveScroll: true,
            onSuccess: () => ElNotification.success('Proyecto desvinculado')
        });
    } else {
        form.incidence = action;
        setIncidence();
    }
};

const setIncidence = () => form.put(route('payroll-users.set-incidence'), { onSuccess: () => { ElNotification.success('Incidencia actualizada'); form.reset(); }, onError: () => ElNotification.error('Error al actualizar') });
const updateAttendance = () => form.put(route('payroll-users.update-attendance'), { onSuccess: () => { ElNotification.success('Asistencia actualizada'); showAttendanceModal.value = false; form.reset(); } });
const removeLate = () => form.put(route('payroll-users.remove-late'), { onSuccess: () => ElNotification.success('Retardo eliminado') });
const submitProject = () => projectForm.put(route('payroll-users.set-project'), {
    preserveScroll: true,
    onSuccess: () => { ElNotification.success('Proyecto vinculado'); showProjectModal.value = false; projectForm.reset(); },
    onError: () => ElNotification.error('Error al vincular proyecto')
});
const submitApproveExtraTime = () => approveForm.put(route('payroll-users.approve-extra-time'), { preserveScroll: true, onSuccess: () => { ElNotification.success('Tiempo extra aprobado'); showApproveModal.value = false; approveForm.reset(); } });
const submitRejectExtraTime = () => approveForm.put(route('payroll-users.reject-extra-time'), { preserveScroll: true, onSuccess: () => { ElNotification.success('Tiempo extra rechazado'); showApproveModal.value = false; approveForm.reset(); } });
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden transition-all duration-300">

        <!-- Header -->
        <div @click="toggleAccordion" class="p-4 flex flex-col md:flex-row items-center justify-between cursor-pointer hover:bg-gray-50 select-none gap-4">
            <div class="flex items-center gap-4 w-full md:w-1/3">
                <div class="relative">
                    <img :src="payrollUser.user.profile_photo_url" class="h-10 w-10 rounded-full object-cover border border-gray-200" alt="Avatar">
                    <span v-if="payrollUser.user.paused" class="absolute -bottom-1 -right-1 flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-800 leading-tight flex items-center flex-wrap gap-2">
                        {{ payrollUser.user.name }}
                        <span v-if="!payrollUser.user.has_attendances" class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-50 text-red-600 border border-red-100 uppercase tracking-wider" title="Sin registros de asistencia en esta catorcena">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin Asistencia
                        </span>
                    </h3>
                    <p class="text-xs text-gray-500">{{ payrollUser.user.org_props?.department || 'General' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-4 lg:gap-6 text-xs text-gray-600 w-full md:w-auto justify-end">
                <div class="flex flex-col items-end"><span class="uppercase text-[10px] text-gray-400 font-bold">Retardos</span><span :class="stats.late !== '0h 0m' ? 'text-red-500 font-bold' : ''">{{ stats.late }}</span></div>
                <div v-if="stats.breakCount > 0" class="flex flex-col items-end"><span class="uppercase text-[10px] text-orange-400 font-bold">Comidas</span><span class="text-orange-600 font-bold">{{ stats.breakTime }}</span></div>
                <div class="flex flex-col items-end"><span class="uppercase text-[10px] text-gray-400 font-bold">T. E. (Pend)</span><span :class="stats.extraPending !== '0h 0m' ? 'text-amber-500 font-bold' : ''">{{ stats.extraPending }}</span><span v-if="canSeeMoney && stats.extraAmountPending > 0" class="text-[9px] text-amber-600 font-bold">${{ formatMoney(stats.extraAmountPending) }}</span><span v-else-if="canSeeMoney && stats.extraPending !== '0h 0m'" class="text-[8px] text-amber-400 italic">Sin costo</span></div>
                <div class="flex flex-col items-end"><span class="uppercase text-[10px] text-green-600 font-bold">T. E. (Aprob)</span><span :class="stats.extraApproved !== '0h 0m' ? 'text-green-600 font-bold' : ''">{{ stats.extraApproved }}</span><span v-if="canSeeMoney && stats.extraAmountApproved > 0" class="text-[9px] text-green-700 font-bold">${{ formatMoney(stats.extraAmountApproved) }}</span><span v-else-if="canSeeMoney && stats.extraApproved !== '0h 0m'" class="text-[8px] text-green-500 italic">Sin costo</span></div>
                <i class="fa-solid fa-chevron-down text-gray-400 transition-transform duration-300 ml-2" :class="{'rotate-180': isOpen}"></i>
            </div>
        </div>

        <!-- Body -->
        <div v-show="isOpen" class="border-t border-gray-100 bg-gray-50/50 p-4">
            <div
                ref="scrollContainer"
                class="overflow-x-auto pb-2 cursor-grab"
                @mousedown="onDragStart"
                @mousemove="onDragMove"
                @mouseup="onDragEnd"
                @mouseleave="onDragEnd"
            >
                <div class="flex gap-2 min-w-max">
                    <IncidencesDayCard
                        v-for="(day, index) in payrollUser.incidences" :key="index"
                        :day="day" :canEdit="canEdit" :incidences="incidences"
                        :canManageIncidence="approval.canManageIncidence"
                        :getIncidencePermission="approval.getIncidencePermission"
                        :getDayApprovalSummary="approval.getDayApprovalSummary"
                        :getApprovalStatusBadge="approval.getApprovalStatusBadge"
                        :isValidLocation="isValidLocation"
                        :getLocationError="getLocationError"
                        :projects="projects"
                        :canSeeMoney="canSeeMoney"
                        @command="handleCommand"
                    />
                </div>
            </div>
        </div>

        <!-- Modal: Modificar Asistencia -->
        <el-dialog v-model="showAttendanceModal" title="Modificar asistencia" width="400px" class="!rounded-xl" destroy-on-close>
            <div class="mb-5 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-2 items-start">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <p>Al borrar las horas registradas y guardar cambios, se marcará como falta en automático.</p>
            </div>
            <div class="grid grid-cols-2 gap-6 py-2">
                <div><label class="block text-sm font-semibold text-gray-700 mb-2">Hora de entrada</label><el-time-picker v-model="form.check_in" format="HH:mm" value-format="HH:mm" placeholder="00:00" class="!w-full" clearable /></div>
                <div><label class="block text-sm font-semibold text-gray-700 mb-2">Hora de salida</label><el-time-picker v-model="form.check_out" format="HH:mm" value-format="HH:mm" placeholder="00:00" class="!w-full" clearable /></div>
            </div>

            <!-- Tiempo de comida / Break -->
            <div class="mt-4 pt-4 border-t border-dashed border-gray-200">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fa-solid fa-utensils text-orange-500 text-sm"></i>
                    <span class="text-sm font-semibold text-gray-700">Tiempo de comida</span>
                    <span class="text-[10px] text-gray-400">(opcional)</span>
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div><label class="block text-sm font-semibold text-gray-700 mb-2">Inicio de comida</label><el-time-picker v-model="form.break_start" format="HH:mm" value-format="HH:mm" placeholder="12:00" class="!w-full" clearable /></div>
                    <div><label class="block text-sm font-semibold text-gray-700 mb-2">Fin de comida</label><el-time-picker v-model="form.break_end" format="HH:mm" value-format="HH:mm" placeholder="13:00" class="!w-full" clearable /></div>
                </div>
                <p class="text-[10px] text-gray-400 mt-2">Si ambos campos están vacíos, se eliminará el registro de comida. Si solo llenas el inicio, se dejará como pausa en curso.</p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 pt-2">
                    <el-button @click="showAttendanceModal = false">Cancelar</el-button>
                    <el-button type="primary" @click="updateAttendance" :loading="form.processing" class="!bg-indigo-600 !border-indigo-600">Guardar cambios</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Modal: Gestionar Tiempo Extra -->
        <el-dialog v-model="showApproveModal" title="Gestionar Tiempo Extra" width="450px" class="!rounded-xl" destroy-on-close>
            <div class="mb-5 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-2 items-start">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <p>Ajusta el tiempo a aprobar, o rechaza el tiempo extra. Puedes agregar una justificación.</p>
            </div>
            <div class="grid grid-cols-2 gap-6 mb-5">
                <div><label class="block text-sm font-semibold text-gray-700 mb-2">Horas aprobadas</label><el-input-number v-model="approveForm.approved_extra_hours" :min="0" class="!w-full" controls-position="right" /><span v-if="approveForm.errors.approved_extra_hours" class="text-xs text-red-500 mt-1 block">{{ approveForm.errors.approved_extra_hours }}</span></div>
                <div><label class="block text-sm font-semibold text-gray-700 mb-2">Minutos aprobados</label><el-input-number v-model="approveForm.approved_extra_minutes" :min="0" :max="59" class="!w-full" controls-position="right" /><span v-if="approveForm.errors.approved_extra_minutes" class="text-xs text-red-500 mt-1 block">{{ approveForm.errors.approved_extra_minutes }}</span></div>
            </div>
            <div><label class="block text-sm font-semibold text-gray-700 mb-2">Comentarios o Justificación (Opcional)</label><el-input v-model="approveForm.comments" type="textarea" :rows="3" placeholder="Ej. Se autoriza por cierre de inventario de almacén." /><span v-if="approveForm.errors.comments" class="text-xs text-red-500 mt-1 block">{{ approveForm.errors.comments }}</span></div>
            <template #footer>
                <div class="flex justify-between items-center w-full pt-2">
                    <el-button type="danger" plain @click="submitRejectExtraTime" :loading="approveForm.processing"><i class="fa-solid fa-xmark mr-2"></i> Rechazar</el-button>
                    <div class="flex gap-2">
                        <el-button @click="showApproveModal = false">Cancelar</el-button>
                        <el-button type="primary" @click="submitApproveExtraTime" :loading="approveForm.processing" class="!bg-indigo-600 !border-indigo-600"><i class="fa-solid fa-check mr-2"></i> Aprobar</el-button>
                    </div>
                </div>
            </template>
        </el-dialog>

        <!-- Modal: Vincular Proyecto -->
        <el-dialog v-model="showProjectModal" title="Vincular proyecto al día" width="420px" class="!rounded-xl" destroy-on-close>
            <div class="mb-5 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-2 items-start">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <p>Selecciona un proyecto para relacionarlo con este día. Esto permite asociar el tiempo trabajado a un proyecto específico.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Proyecto</label>
                <el-select 
                    v-model="projectForm.project_id" 
                    placeholder="Selecciona un proyecto..." 
                    filterable 
                    clearable
                    class="w-full"
                >
                    <el-option 
                        v-for="project in projects" 
                        :key="project.id" 
                        :label="`${project.name} (${project.client})`" 
                        :value="project.id" 
                    />
                </el-select>
                <span v-if="projectForm.errors.project_id" class="text-xs text-red-500 mt-1 block">{{ projectForm.errors.project_id }}</span>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 pt-2">
                    <el-button @click="showProjectModal = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitProject" :loading="projectForm.processing" class="!bg-indigo-600 !border-indigo-600">
                        <i class="fa-solid fa-link mr-2"></i> Vincular
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
:deep(.el-input__wrapper) { border-radius: 0.5rem; box-shadow: 0 0 0 1px #e5e7eb inset; }
:deep(.el-input__wrapper.is-focus) { box-shadow: 0 0 0 1px #4f46e5 inset !important; }
</style>
