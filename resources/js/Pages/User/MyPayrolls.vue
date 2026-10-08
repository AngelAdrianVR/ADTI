<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import IncidencesTable from '@/Components/MyComponents/Payroll/IncidencesTable.vue';
import { format, parseISO, addDays, differenceInBusinessDays, isAfter } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElNotification, ElMessageBox } from 'element-plus';

const props = defineProps({
    payrolls: {
        type: Array,
        default: () => []
    },
    vacationRequests: {
        type: Array,
        default: () => []
    },
    vacationDetails: {
        type: Object,
        default: () => ({ total_balance: 0, locked_days: 0, available_days: 0 })
    }
});

const page = usePage();
const currentUser = page.props.auth.user;

// --- Estado General ---
const activeTab = ref('payrolls'); // 'payrolls' o 'vacations'

// --- Estado para Nóminas (Scroll Infinito) ---
const limit = ref(5); 
const loadMoreTrigger = ref(null);

const visiblePayrolls = computed(() => {
    return props.payrolls.slice(0, limit.value);
});

// --- Estado para Solicitudes de Vacaciones ---
const showRequestModal = ref(false);

const requestForm = useForm({
    date_range: [], // Array con [start_date, end_date]
    days_requested: 0,
    employee_notes: '',
});

// Computed para calcular días (ignorando fines de semana, aunque esto puede ajustarse a tu política)
const calculatedDays = computed(() => {
    if (!requestForm.date_range || requestForm.date_range.length !== 2) return 0;
    
    const start = new Date(requestForm.date_range[0]);
    const end = new Date(requestForm.date_range[1]);
    
    // Si tu política cuenta días naturales, cambia a differenceInDays
    // +1 porque differenceInBusinessDays excluye el día final si cae en la misma semana
    return differenceInBusinessDays(end, start) + 1; 
});

// Deshabilitar fechas pasadas y fechas antes de 15 días en el DatePicker
const disabledDate = (time) => {
    const minDate = new Date();
    minDate.setDate(minDate.getDate() + 14); // Requiere 15 días de anticipación
    return time.getTime() < minDate.getTime();
};

// Validaciones en tiempo real
const hasEnoughBalance = computed(() => {
    return calculatedDays.value > 0 && calculatedDays.value <= props.vacationDetails.available_days;
});

// --- Helpers de Fecha y Generales ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM yyyy', { locale: es });
};

const getPeriodRange = (startDate) => {
    const start = parseISO(startDate);
    const end = addDays(start, 13);
    return `${format(start, 'dd MMM', { locale: es })} - ${format(end, 'dd MMM yyyy', { locale: es })}`;
};

const getStatusColor = (status) => {
    const colors = {
        'Pendiente': 'bg-amber-100 text-amber-700 border-amber-200',
        'Aprobada': 'bg-green-100 text-green-700 border-green-200',
        'Rechazada': 'bg-red-100 text-red-700 border-red-200',
        'Cancelada': 'bg-gray-100 text-gray-700 border-gray-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const getPayrollUserObject = (payrollItem) => {
    return {
        user: currentUser, 
        incidences: payrollItem.incidences
    };
};

// --- Acciones de Vacaciones ---
const submitRequest = () => {
    if (!hasEnoughBalance.value) {
        ElNotification.error({ title: 'Error', message: 'No tienes saldo suficiente para esta solicitud.' });
        return;
    }

    // Preparar form
    requestForm.days_requested = calculatedDays.value;
    
    // Transformar fechas a YYYY-MM-DD
    const start = new Date(requestForm.date_range[0]);
    const end = new Date(requestForm.date_range[1]);
    start.setMinutes(start.getMinutes() - start.getTimezoneOffset());
    end.setMinutes(end.getMinutes() - end.getTimezoneOffset());
    
    const start_str = start.toISOString().split('T')[0];
    const end_str = end.toISOString().split('T')[0];

    requestForm.transform((data) => ({
        ...data,
        start_date: start_str,
        end_date: end_str,
    })).post(route('vacation-requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showRequestModal.value = false;
            requestForm.reset();
            ElNotification.success({ title: 'Éxito', message: 'Tu solicitud de vacaciones ha sido enviada a revisión.' });
        }
    });
};

const cancelRequest = (id) => {
    ElMessageBox.confirm(
        '¿Estás seguro de que deseas cancelar esta solicitud? Los días retenidos volverán a tu saldo disponible.',
        'Cancelar Solicitud',
        { confirmButtonText: 'Sí, cancelar', cancelButtonText: 'No', type: 'warning' }
    ).then(() => {
        router.put(route('vacation-requests.cancel', id), {}, {
            preserveScroll: true,
            onSuccess: () => ElNotification.success({ title: 'Cancelada', message: 'La solicitud ha sido cancelada.' })
        });
    }).catch(() => {});
};

// --- Intersection Observer para Scroll ---
onMounted(() => {
    if (loadMoreTrigger.value) {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && limit.value < props.payrolls.length) {
                limit.value += 5; 
            }
        }, {
            rootMargin: '100px'
        });
        observer.observe(loadMoreTrigger.value);
    }
});
</script>

<template>
    <AppLayout title="Mi Nómina y Vacaciones">
        <div class="min-h-screen bg-gray-50/50 py-8 relative">
            
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Header con Avatar -->
                <div class="flex items-center gap-4 mb-8 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <img :src="currentUser.profile_photo_url" class="h-16 w-16 rounded-full object-cover shadow-sm ring-4 ring-gray-50" :alt="currentUser.name">
                    <div>
                        <h1 class="text-2xl font-black text-gray-800 tracking-tight">{{ currentUser.name }}</h1>
                        <p class="text-sm text-gray-500 font-medium mt-0.5">Centro de Control Personal</p>
                    </div>
                </div>

                <!-- Pestañas de Navegación -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 p-2 flex gap-2 overflow-x-auto">
                    <button 
                        @click="activeTab = 'payrolls'"
                        class="flex-1 py-2.5 px-4 rounded-lg text-sm font-bold transition-all whitespace-nowrap flex items-center justify-center gap-2"
                        :class="activeTab === 'payrolls' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                    >
                        <i class="fa-solid fa-money-check-dollar"></i> Historial de Nóminas
                    </button>
                    <button 
                        @click="activeTab = 'vacations'"
                        class="flex-1 py-2.5 px-4 rounded-lg text-sm font-bold transition-all whitespace-nowrap flex items-center justify-center gap-2"
                        :class="activeTab === 'vacations' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                    >
                        <i class="fa-solid fa-umbrella-beach"></i> Mis Vacaciones
                    </button>
                </div>

                <!-- ============================================== -->
                <!-- PESTAÑA: NÓMINAS                               -->
                <!-- ============================================== -->
                <div v-show="activeTab === 'payrolls'">
                    <div v-if="payrolls.length > 0" class="space-y-6">
                        <div class="space-y-5">
                            <div v-for="payroll in visiblePayrolls" :key="payroll.id" class="animate-fade-in group">
                                <div class="flex items-center justify-between mb-2 px-1">
                                    <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 rounded-full" :class="payroll.is_active ? 'bg-green-500 animate-pulse' : 'bg-gray-300'"></div>
                                        Catorcena {{ payroll.biweekly }}
                                        <span v-if="payroll.is_active" class="text-[10px] uppercase tracking-wider text-green-600 bg-green-50 px-2 py-0.5 rounded-full border border-green-100">Activa</span>
                                    </h3>
                                    <p class="text-xs font-medium text-gray-500 bg-white px-2.5 py-1 rounded-md border border-gray-100 shadow-sm">
                                        <i class="fa-regular fa-calendar-days mr-1.5 opacity-50"></i>
                                        {{ getPeriodRange(payroll.start_date) }}
                                    </p>
                                </div>
                                <IncidencesTable 
                                    :payrollUser="getPayrollUserObject(payroll)" 
                                    :payroll="payroll" 
                                    :canEdit="false"
                                />
                            </div>
                        </div>
                        <div ref="loadMoreTrigger" class="h-10 flex items-center justify-center text-gray-400 text-xs">
                            <span v-if="limit < payrolls.length"><i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Cargando historial...</span>
                            <span v-else class="italic opacity-50">Fin del historial</span>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-16 bg-white rounded-xl border border-dashed border-gray-300">
                        <i class="fa-regular fa-folder-open text-3xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900">Sin historial</h3>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- PESTAÑA: VACACIONES                            -->
                <!-- ============================================== -->
                <div v-show="activeTab === 'vacations'" class="space-y-6">
                    
                    <!-- Tarjetas de Saldo -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-indigo-600 rounded-xl p-5 text-white shadow-md relative overflow-hidden flex flex-col justify-center">
                            <i class="fa-solid fa-plane-departure absolute -right-4 -bottom-4 text-6xl text-white opacity-10"></i>
                            <p class="text-indigo-100 text-xs font-bold uppercase tracking-wider mb-1">Días Disponibles</p>
                            <div class="flex items-end gap-2">
                                <span class="text-4xl font-black">{{ vacationDetails.available_days }}</span>
                                <span class="text-indigo-200 text-sm mb-1 font-medium">días</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl p-5 border border-gray-200 flex flex-col justify-center">
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Total Ganados</p>
                            <span class="text-2xl font-bold text-gray-800">{{ vacationDetails.total_balance }} <span class="text-sm font-normal text-gray-400">días</span></span>
                        </div>

                        <div class="bg-white rounded-xl p-5 border border-gray-200 flex flex-col justify-center">
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">En Tránsito (Pend/Aprob)</p>
                            <span class="text-2xl font-bold text-gray-800">{{ vacationDetails.locked_days }} <span class="text-sm font-normal text-gray-400">días</span></span>
                        </div>
                    </div>

                    <!-- Botón Solicitar -->
                    <div class="flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <div>
                            <h3 class="font-bold text-gray-800">Solicitar Vacaciones</h3>
                            <p class="text-xs text-gray-500">Recuerda solicitarlas con al menos 15 días de anticipación.</p>
                        </div>
                        <el-button 
                            type="primary" 
                            @click="showRequestModal = true"
                            class="!bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700"
                            :disabled="vacationDetails.available_days < 1"
                        >
                            <i class="fa-solid fa-calendar-plus mr-2"></i> Crear Solicitud
                        </el-button>
                    </div>

                    <!-- Lista de Solicitudes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100 bg-gray-50/50">
                            <h3 class="font-bold text-gray-800 text-sm">Historial de Solicitudes</h3>
                        </div>
                        <div class="p-0 overflow-x-auto">
                            <el-table :data="vacationRequests" style="width: 100%" stripe empty-text="Aún no has realizado ninguna solicitud.">
                                <el-table-column label="Periodo Solicitado" min-width="180">
                                    <template #default="scope">
                                        <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                            <i class="fa-regular fa-calendar text-gray-400"></i>
                                            {{ formatDate(scope.row.start_date) }} <i class="fa-solid fa-arrow-right text-gray-300 text-[10px]"></i> {{ formatDate(scope.row.end_date) }}
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Días" width="80" align="center">
                                    <template #default="scope">
                                        <span class="font-bold text-gray-700">{{ scope.row.days_requested }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Estado" width="120" align="center">
                                    <template #default="scope">
                                        <span class="text-xs font-bold px-2 py-1 rounded border" :class="getStatusColor(scope.row.status)">
                                            {{ scope.row.status }}
                                        </span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Resolución" min-width="200">
                                    <template #default="scope">
                                        <div v-if="scope.row.reviewer" class="text-xs">
                                            <p class="font-semibold text-gray-700">Por: {{ scope.row.reviewer.name }}</p>
                                            <p class="text-gray-500 italic mt-0.5 truncate" :title="scope.row.reviewer_notes">"{{ scope.row.reviewer_notes || 'Sin comentarios' }}"</p>
                                        </div>
                                        <span v-else class="text-xs text-gray-400 italic">En espera de revisión...</span>
                                    </template>
                                </el-table-column>
                                <el-table-column align="right" width="80">
                                    <template #default="scope">
                                        <el-button 
                                            v-if="scope.row.status === 'Pendiente'"
                                            @click="cancelRequest(scope.row.id)"
                                            type="danger" 
                                            text 
                                            size="small"
                                            title="Cancelar Solicitud"
                                        >
                                            <i class="fa-solid fa-xmark"></i>
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal de Solicitud (Element Plus) -->
        <el-dialog
            v-model="showRequestModal"
            title="Nueva Solicitud de Vacaciones"
            width="450px"
            destroy-on-close
            class="!rounded-xl"
        >
            <div class="mt-2 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-3 items-start mb-6">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <p>Las solicitudes deben enviarse con al menos <b>15 días de anticipación</b>. Tienes <b>{{ vacationDetails.available_days }} días</b> disponibles.</p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Periodo Deseado <span class="text-red-500">*</span></label>
                    <el-date-picker
                        v-model="requestForm.date_range"
                        type="daterange"
                        range-separator="Hasta"
                        start-placeholder="Inicio"
                        end-placeholder="Fin"
                        class="!w-full"
                        size="large"
                        :disabled-date="disabledDate"
                    />
                    <span v-if="requestForm.errors.start_date" class="text-xs text-red-500 mt-1 block">{{ requestForm.errors.start_date }}</span>
                </div>

                <div v-if="requestForm.date_range && requestForm.date_range.length === 2" class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-semibold text-gray-700">Días solicitados:</span>
                        <span class="text-lg font-black" :class="hasEnoughBalance ? 'text-indigo-600' : 'text-red-600'">{{ calculatedDays }}</span>
                    </div>
                    <p v-if="!hasEnoughBalance" class="text-xs text-red-500"><i class="fa-solid fa-triangle-exclamation"></i> Excedes tu saldo disponible.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Comentarios (Opcional)</label>
                    <el-input 
                        v-model="requestForm.employee_notes" 
                        type="textarea" 
                        :rows="3" 
                        placeholder="Agrega alguna nota para Recursos Humanos..." 
                    />
                    <span v-if="requestForm.errors.employee_notes" class="text-xs text-red-500 mt-1 block">{{ requestForm.errors.employee_notes }}</span>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-2">
                    <el-button @click="showRequestModal = false">Cancelar</el-button>
                    <el-button 
                        type="primary" 
                        @click="submitRequest" 
                        :loading="requestForm.processing"
                        :disabled="!hasEnoughBalance"
                        class="!bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700"
                    >
                        Enviar Solicitud
                    </el-button>
                </div>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<style scoped>
/* Element Plus Overrides */
:deep(.el-table) {
    --el-table-header-bg-color: #f9fafb;
    --el-table-header-text-color: #6b7280;
}
:deep(.el-table th) {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
:deep(.el-range-editor.el-input__wrapper) {
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px #e5e7eb inset;
}
:deep(.el-range-editor.el-input__wrapper.is-active) {
    box-shadow: 0 0 0 1px #4f46e5 inset !important;
}
:deep(.el-textarea__inner) {
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px #e5e7eb inset;
}
:deep(.el-textarea__inner:focus) {
    box-shadow: 0 0 0 1px #4f46e5 inset !important;
}
</style>