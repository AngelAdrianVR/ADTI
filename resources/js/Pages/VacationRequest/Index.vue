<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElNotification } from 'element-plus';

const props = defineProps({
    requests: {
        type: Array,
        default: () => []
    },
});

// --- Estados ---
const search = ref('');
const statusFilter = ref('Pendiente'); // Mostrar pendientes por defecto
const statusOptions = ['Todos', 'Pendiente', 'Aprobada', 'Rechazada', 'Cancelada'];

const showResolveModal = ref(false);
const selectedRequest = ref(null);
const resolveAction = ref(''); // 'approve' o 'reject'

const form = useForm({
    reviewer_notes: '',
});

// --- Computed ---
const filteredRequests = computed(() => {
    return props.requests.filter(req => {
        const matchesSearch = req.user?.name.toLowerCase().includes(search.value.toLowerCase()) || 
                              req.user?.org_props?.department?.toLowerCase().includes(search.value.toLowerCase());
        const matchesStatus = statusFilter.value === 'Todos' || req.status === statusFilter.value;
        return matchesSearch && matchesStatus;
    });
});

// Estadísticas rápidas
const stats = computed(() => {
    return {
        pending: props.requests.filter(r => r.status === 'Pendiente').length,
        approved: props.requests.filter(r => r.status === 'Aprobada').length,
        rejected: props.requests.filter(r => r.status === 'Rechazada').length,
    };
});

// --- Métodos ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM yyyy', { locale: es });
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), "dd MMM yyyy, HH:mm 'hrs'", { locale: es });
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

const openResolveModal = (req, action) => {
    selectedRequest.value = req;
    resolveAction.value = action;
    form.reviewer_notes = '';
    form.clearErrors();
    showResolveModal.value = true;
};

const submitResolution = () => {
    const routeName = resolveAction.value === 'approve' 
        ? 'vacation-requests.approve' 
        : 'vacation-requests.reject';
        
    form.put(route(routeName, selectedRequest.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showResolveModal.value = false;
            form.reset();
            ElNotification.success({
                title: 'Éxito',
                message: `La solicitud ha sido ${resolveAction.value === 'approve' ? 'aprobada' : 'rechazada'} correctamente.`
            });
            selectedRequest.value = null;
        }
    });
};
</script>

<template>
    <AppLayout title="Solicitudes de Vacaciones">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Encabezado -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Solicitudes de Vacaciones</h1>
                        <p class="text-sm text-gray-500 mt-1">
                            Gestiona y autoriza las peticiones de tiempo libre de tu equipo.
                        </p>
                    </div>
                </div>

                <!-- Tarjetas de Estadísticas -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:border-amber-300 transition-colors" @click="statusFilter = 'Pendiente'">
                        <div>
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Pendientes</p>
                            <span class="text-2xl font-bold text-gray-800">{{ stats.pending }}</span>
                        </div>
                        <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center text-xl">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:border-green-300 transition-colors" @click="statusFilter = 'Aprobada'">
                        <div>
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Aprobadas</p>
                            <span class="text-2xl font-bold text-gray-800">{{ stats.approved }}</span>
                        </div>
                        <div class="w-12 h-12 bg-green-50 text-green-500 rounded-full flex items-center justify-center text-xl">
                            <i class="fa-solid fa-check-double"></i>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm flex items-center justify-between cursor-pointer hover:border-red-300 transition-colors" @click="statusFilter = 'Rechazada'">
                        <div>
                            <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Rechazadas</p>
                            <span class="text-2xl font-bold text-gray-800">{{ stats.rejected }}</span>
                        </div>
                        <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center text-xl">
                            <i class="fa-solid fa-ban"></i>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    <div class="md:col-span-6 lg:col-span-5">
                        <el-input v-model="search" placeholder="Buscar por empleado o departamento..." clearable size="large">
                            <template #prefix><i class="fa-solid fa-magnifying-glass text-gray-400"></i></template>
                        </el-input>
                    </div>
                    <div class="md:col-span-4 lg:col-span-3">
                        <el-select v-model="statusFilter" class="w-full" size="large">
                            <el-option v-for="status in statusOptions" :key="status" :label="`Estatus: ${status}`" :value="status" />
                        </el-select>
                    </div>
                    <div class="md:col-span-2 lg:col-span-4 text-right text-xs text-gray-500">
                        Mostrando <span class="font-bold text-gray-800">{{ filteredRequests.length }}</span> solicitudes
                    </div>
                </div>

                <!-- Tabla de Resultados -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-0 overflow-x-auto">
                        <el-table :data="filteredRequests" style="width: 100%" stripe empty-text="No se encontraron solicitudes con estos filtros.">
                            
                            <!-- Colaborador -->
                            <el-table-column label="Colaborador" min-width="250">
                                <template #default="scope">
                                    <div class="flex items-center gap-3 py-1">
                                        <img :src="scope.row.user.profile_photo_url" class="w-8 h-8 rounded-full object-cover border border-gray-200" :alt="scope.row.user.name">
                                        <div>
                                            <p class="font-bold text-gray-800 text-sm leading-tight">{{ scope.row.user.name }}</p>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-wide mt-0.5">{{ scope.row.user.org_props?.department || 'General' }}</p>
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>

                            <!-- Periodo -->
                            <el-table-column label="Fechas Solicitadas" min-width="190">
                                <template #default="scope">
                                    <div class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <i class="fa-regular fa-calendar text-gray-400"></i>
                                        {{ formatDate(scope.row.start_date) }} <i class="fa-solid fa-arrow-right text-gray-300 text-[10px]"></i> {{ formatDate(scope.row.end_date) }}
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1">Enviada: {{ formatDate(scope.row.created_at) }}</p>
                                </template>
                            </el-table-column>

                            <!-- Días -->
                            <el-table-column label="Días" width="80" align="center">
                                <template #default="scope">
                                    <span class="font-bold text-gray-800 bg-gray-100 px-2 py-1 rounded">{{ scope.row.days_requested }}</span>
                                </template>
                            </el-table-column>

                            <!-- Estado -->
                            <el-table-column label="Estado" width="120" align="center">
                                <template #default="scope">
                                    <span class="text-xs font-bold px-2 py-1 rounded border" :class="getStatusColor(scope.row.status)">
                                        {{ scope.row.status }}
                                    </span>
                                </template>
                            </el-table-column>

                            <!-- Notas Empleado -->
                            <el-table-column label="Notas del Empleado" min-width="200">
                                <template #default="scope">
                                    <el-tooltip :content="scope.row.employee_notes" placement="top" :disabled="!scope.row.employee_notes">
                                        <span class="text-xs text-gray-600 block truncate max-w-[200px]" :class="{'italic text-gray-400': !scope.row.employee_notes}">
                                            {{ scope.row.employee_notes ? `"${scope.row.employee_notes}"` : 'Sin notas' }}
                                        </span>
                                    </el-tooltip>
                                </template>
                            </el-table-column>

                            <!-- Acciones / Resolución -->
                            <el-table-column align="right" min-width="180">
                                <template #default="scope">
                                    
                                    <!-- Si está Pendiente: Mostrar Botones de Acción -->
                                    <div v-if="scope.row.status === 'Pendiente'" class="flex justify-end gap-2">
                                        <el-button 
                                            @click="openResolveModal(scope.row, 'reject')"
                                            type="danger" 
                                            plain 
                                            size="small"
                                        >
                                            <i class="fa-solid fa-xmark mr-1"></i> Rechazar
                                        </el-button>
                                        <el-button 
                                            @click="openResolveModal(scope.row, 'approve')"
                                            type="success" 
                                            plain 
                                            size="small"
                                        >
                                            <i class="fa-solid fa-check mr-1"></i> Aprobar
                                        </el-button>
                                    </div>

                                    <!-- Si ya está resuelta: Mostrar quién y cuándo -->
                                    <div v-else class="text-xs text-left inline-block">
                                        <p class="font-semibold text-gray-700">Por: {{ scope.row.reviewer?.name || 'Sistema' }}</p>
                                        <p class="text-gray-400 text-[10px] mt-0.5">{{ formatDateTime(scope.row.resolved_at) }}</p>
                                        <el-tooltip :content="scope.row.reviewer_notes" placement="top-end" :disabled="!scope.row.reviewer_notes">
                                            <p class="italic text-gray-500 mt-1 truncate max-w-[150px]" v-if="scope.row.reviewer_notes">"{{ scope.row.reviewer_notes }}"</p>
                                        </el-tooltip>
                                    </div>

                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal de Resolución (Aprobar/Rechazar) -->
        <el-dialog
            v-model="showResolveModal"
            :title="resolveAction === 'approve' ? 'Aprobar Solicitud' : 'Rechazar Solicitud'"
            width="450px"
            destroy-on-close
            class="!rounded-xl"
        >
            <div v-if="selectedRequest" class="space-y-4">
                
                <!-- Resumen de la petición -->
                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img :src="selectedRequest.user.profile_photo_url" class="w-8 h-8 rounded-full" alt="">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">{{ selectedRequest.user.name }}</p>
                            <p class="text-xs text-gray-500">{{ selectedRequest.days_requested }} días solicitados</p>
                        </div>
                    </div>
                </div>

                <!-- Aviso dinámico -->
                <div v-if="resolveAction === 'approve'" class="text-sm text-green-700 bg-green-50 p-3 rounded-lg border border-green-200">
                    <i class="fa-solid fa-circle-check mr-1.5"></i> Al aprobar esta solicitud, los días se considerarán confirmados para el empleado.
                </div>
                <div v-else class="text-sm text-red-700 bg-red-50 p-3 rounded-lg border border-red-200">
                    <i class="fa-solid fa-triangle-exclamation mr-1.5"></i> El empleado será notificado del rechazo. Los días retenidos volverán a su saldo disponible.
                </div>

                <!-- Formulario de Notas -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Comentarios adicionales <span v-if="resolveAction === 'reject'" class="text-red-500">*</span>
                    </label>
                    <el-input 
                        v-model="form.reviewer_notes" 
                        type="textarea" 
                        :rows="3" 
                        :placeholder="resolveAction === 'approve' ? 'Ej. ¡Disfruta tus vacaciones! (Opcional)' : 'Explica el motivo del rechazo...'" 
                    />
                    <span v-if="form.errors.reviewer_notes" class="text-xs text-red-500 mt-1 block">{{ form.errors.reviewer_notes }}</span>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-2">
                    <el-button @click="showResolveModal = false">Cancelar</el-button>
                    <el-button 
                        v-if="resolveAction === 'approve'"
                        type="success" 
                        @click="submitResolution" 
                        :loading="form.processing"
                    >
                        Confirmar Aprobación
                    </el-button>
                    <el-button 
                        v-else
                        type="danger" 
                        @click="submitResolution" 
                        :loading="form.processing"
                    >
                        Confirmar Rechazo
                    </el-button>
                </div>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<style scoped>
:deep(.el-table) {
    --el-table-header-bg-color: #f9fafb;
    --el-table-header-text-color: #6b7280;
}
:deep(.el-table th) {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
:deep(.el-input__wrapper) {
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px #e5e7eb inset;
}
:deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px #4f46e5 inset !important;
}
:deep(.el-select__wrapper) {
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px #e5e7eb inset;
}
:deep(.el-select__wrapper.is-focus) {
    box-shadow: 0 0 0 1px #4f46e5 inset !important;
}
</style>