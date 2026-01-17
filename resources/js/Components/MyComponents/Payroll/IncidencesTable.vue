<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { format, isSameDay, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ElNotification } from 'element-plus';

const props = defineProps({
    payrollUser: Object,
    payroll: Object
});

// State
const isOpen = ref(false); // Controla el acordeón
const showAttendanceModal = ref(false);
const incidences = ref(['Falta injustificada', 'Falta justificada', 'Incapacidad', 'Permiso sin goce', 'Permiso con goce', 'Vacaciones', 'Descanso', 'Día festivo']);

const form = useForm({
    date: null,
    check_in: null,
    check_out: null,
    incidence: null,
    user_id: props.payrollUser.user.id,
    payroll_id: props.payroll.id,
});

// Computed Stats
const stats = computed(() => {
    let totalMinutes = 0;
    let extraMinutes = 0;
    let lateMinutes = 0;

    props.payrollUser.incidences.forEach(day => {
        // Calcular tiempo trabajado simple (estimado) si hay entrada y salida
        if (day.check_in && day.check_out) {
            // Lógica simple de visualización, el cálculo real está en backend
        }
        
        // Sumar extras
        if (day.extra_hours || day.extra_minutes) {
            extraMinutes += (day.extra_hours || 0) * 60 + (day.extra_minutes || 0);
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
        extra: formatMins(extraMinutes),
        late: formatMins(lateMinutes),
        // Puedes agregar más stats aquí si el backend los provee
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
                    <h3 class="text-sm font-bold text-gray-800 leading-tight">{{ payrollUser.user.name }}</h3>
                    <p class="text-xs text-gray-500">{{ payrollUser.user.org_props.department }}</p>
                </div>
            </div>

            <!-- Stats (Compacto) -->
            <div class="flex items-center gap-6 text-xs text-gray-600 w-full md:w-auto justify-end">
                <div class="flex flex-col items-end">
                    <span class="uppercase text-[10px] text-gray-400 font-bold">Retardos</span>
                    <span :class="stats.late !== '0h 0m' ? 'text-red-500 font-bold' : ''">{{ stats.late }}</span>
                </div>
                <div class="flex flex-col items-end">
                    <span class="uppercase text-[10px] text-gray-400 font-bold">Extras</span>
                    <span :class="stats.extra !== '0h 0m' ? 'text-green-600 font-bold' : ''">{{ stats.extra }}</span>
                </div>
                
                <i 
                    class="fa-solid fa-chevron-down text-gray-400 transition-transform duration-300 ml-2"
                    :class="{'rotate-180': isOpen}"
                ></i>
            </div>
        </div>

        <!-- Body (Detalle 14 Días) -->
        <div v-show="isOpen" class="border-t border-gray-100 bg-gray-50/50 p-4">
            
            <!-- Grid de Días (Horizontal Scroll en móvil) -->
            <div class="overflow-x-auto pb-2">
                <div class="flex gap-2 min-w-max">
                    <div 
                        v-for="(day, index) in payrollUser.incidences" 
                        :key="index" 
                        class="flex flex-col w-28 border rounded-lg bg-white overflow-hidden shadow-sm transition-all hover:shadow-md"
                        :class="getIncidenceColor(day)"
                    >
                        <!-- Fecha Header -->
                        <div class="text-center py-1.5 text-[10px] uppercase font-bold tracking-wider border-b border-gray-100" :class="day.check_in ? 'bg-gray-50 text-gray-600' : 'bg-white text-gray-400'">
                            {{ getDayName(day.date) }} <span class="text-gray-800">{{ formatDate(day.date) }}</span>
                        </div>

                        <!-- Contenido Día -->
                        <div class="flex-1 p-2 flex flex-col justify-center items-center text-xs gap-1 min-h-[80px] relative group">
                            
                            <!-- Dropdown de Acciones (Esquina) -->
                            <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity z-10" v-if="payroll.is_active">
                                <el-dropdown trigger="click" @command="handleCommand" size="small">
                                    <button class="p-1 hover:bg-gray-200 rounded text-gray-400 hover:text-gray-600">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item :command="`edit_time|${day.date}`">
                                                <i class="fa-regular fa-clock mr-2"></i> Editar Horas
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
                            <template v-if="day.check_in">
                                <div class="text-gray-800 font-mono font-bold">{{ day.check_in.substring(0, 5) }} - {{ day.check_out?.substring(0, 5) || '??' }}</div>
                                <div v-if="day.late" class="text-[10px] text-red-500 bg-red-50 px-1.5 rounded border border-red-100">
                                    Retardo: {{ day.late }}m
                                </div>
                                <div v-if="day.extra_hours || day.extra_minutes" class="text-[10px] text-green-600 bg-green-50 px-1.5 rounded border border-green-100">
                                    Extra: {{ day.extra_hours }}h {{ day.extra_minutes }}m
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

        <!-- Modal Editar Asistencia -->
        <DialogModal :show="showAttendanceModal" @close="showAttendanceModal = false">
            <template #title>
                Modificar Asistencia
            </template>
            <template #content>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Entrada" />
                        <TextInput type="time" v-model="form.check_in" class="w-full mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Salida" />
                        <TextInput type="time" v-model="form.check_out" class="w-full mt-1" />
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showAttendanceModal = false" class="mr-2">Cancelar</SecondaryButton>
                <PrimaryButton @click="updateAttendance" :disabled="form.processing">Guardar</PrimaryButton>
            </template>
        </DialogModal>

    </div>
</template>