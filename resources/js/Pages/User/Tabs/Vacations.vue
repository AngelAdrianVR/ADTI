<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElNotification, ElMessageBox } from 'element-plus';

const props = defineProps({
    user: Object,
    vacationDetails: Object, // Ahora recibe { current_balance, history: [...] }
});

const showAdjustmentModal = ref(false);

// Seleccionar el año más reciente por defecto
const selectedYearIndex = ref(props.vacationDetails.history.length - 1);

const form = useForm({
    days: 0,
    notes: '',
    date: new Date().toISOString().split('T')[0]
});

// --- Computed ---
// Obtenemos los datos del año seleccionado
const currentPeriodData = computed(() => {
    return props.vacationDetails.history[selectedYearIndex.value] || null;
});

// Opciones para el selector
const yearOptions = computed(() => {
    return props.vacationDetails.history.map((hist, index) => {
        return {
            value: index,
            label: `Año ${hist.years_worked} (${formatDate(hist.period_start)} - ${formatDate(hist.period_end)})`,
        };
    }).reverse(); // Mostramos el más reciente arriba
});

// --- Métodos ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM yyyy', { locale: es });
};

const formatFullDate = (dateString) => {
    if (!dateString) return 'Desconocida';
    return format(parseISO(dateString), 'dd de MMMM de yyyy', { locale: es });
};

const submitAdjustment = () => {
    form.post(route('users.vacation-adjustments.store', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAdjustmentModal.value = false;
            form.reset();
            ElNotification.success({ title: 'Éxito', message: 'Ajuste de vacaciones guardado correctamente.' });
        }
    });
};

const confirmDeleteAdjustment = (id) => {
    ElMessageBox.confirm(
        '¿Estás seguro de que deseas revertir y eliminar este ajuste manual? Esto afectará el saldo actual del empleado.',
        'Revertir Ajuste',
        {
            confirmButtonText: 'Sí, revertir',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        }
    ).then(() => {
        router.delete(route('users.vacation-adjustments.destroy', [props.user.id, id]), {
            preserveScroll: true,
            onSuccess: () => {
                ElNotification.success({ title: 'Éxito', message: 'El ajuste manual ha sido revertido.' });
            }
        });
    }).catch(() => {
        // Cancelado
    });
};
</script>

<template>
    <div class="space-y-8">
        
        <!-- Header con el Selector de Período -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Panel de Vacaciones</h2>
                <p class="text-xs text-gray-500">Consulta el historial de días devengados y tomados por período.</p>
            </div>
            <div class="w-full sm:w-80">
                <el-select 
                    v-model="selectedYearIndex" 
                    placeholder="Selecciona un período" 
                    class="w-full"
                    size="large"
                >
                    <el-option
                        v-for="item in yearOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                    >
                        <span style="float: left">{{ item.label.split('(')[0] }}</span>
                        <span style="float: right; color: var(--el-text-color-secondary); font-size: 12px">
                            {{ item.label.split('(')[1].replace(')', '') }}
                        </span>
                    </el-option>
                </el-select>
            </div>
        </div>

        <div v-if="currentPeriodData">
            <!-- Tarjetas de Resumen (Dashboard) -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
                
                <!-- Saldo Actual (Global, no depende del periodo seleccionado) -->
                <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 flex flex-col justify-center relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 opacity-10">
                        <i class="fa-solid fa-plane-departure text-6xl text-indigo-500"></i>
                    </div>
                    <div class="flex justify-between items-start z-10">
                        <p class="text-indigo-600 text-xs font-bold uppercase tracking-wider mb-1">Saldo Total Disponible</p>
                        
                        <!-- Tooltip de Información sobre el devengo -->
                        <el-tooltip
                            effect="dark"
                            placement="bottom-end"
                            trigger="hover"
                        >
                            <template #content>
                                <div class="max-w-xs text-xs leading-relaxed">
                                    <p class="font-bold mb-1">Cálculo Proporcional (Devengo Semanal)</p>
                                    <p>Cada semana se suma a este saldo la parte proporcional de las vacaciones anuales que le corresponden al empleado. (Total anual dividido entre 52 semanas).</p>
                                </div>
                            </template>
                            <i class="fa-solid fa-circle-info text-indigo-400 cursor-pointer hover:text-indigo-600 transition-colors"></i>
                        </el-tooltip>
                    </div>

                    <div class="flex items-end gap-2 z-10">
                        <span class="text-4xl font-black text-indigo-900 leading-none">{{ vacationDetails.current_balance }}</span>
                        <span class="text-indigo-700 text-sm font-medium mb-0.5">Días</span>
                    </div>
                    <p class="text-[10px] text-indigo-500 mt-2 z-10 font-medium">Última actualización: {{ formatFullDate(user.org_props?.updated_date_vacations) }}</p>
                </div>

                <!-- Periodo Anual Seleccionado -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-center">
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">Periodo Seleccionado</p>
                    <div class="flex items-center gap-2 text-sm text-gray-700 font-medium">
                        <i class="fa-regular fa-calendar text-gray-400"></i>
                        <span>{{ formatDate(currentPeriodData.period_start) }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-700 font-medium mt-1">
                        <i class="fa-solid fa-arrow-right text-gray-300 ml-1"></i>
                        <span>{{ formatDate(currentPeriodData.period_end) }}</span>
                    </div>
                </div>

                <!-- Días Correspondientes por Ley (Periodo seleccionado) -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-center">
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Días por Ley (Año {{ currentPeriodData.years_worked }})</p>
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold text-gray-800 leading-none">{{ currentPeriodData.days_per_year }}</span>
                        <span class="text-gray-500 text-xs font-medium mb-0.5">Días anuales</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-2 leading-tight">Monto total que el empleado devenga gradualmente en este año.</p>
                </div>

                <!-- Tomados en el periodo -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-center">
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Días Tomados</p>
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold text-gray-800 leading-none">{{ currentPeriodData.taken_in_period.length }}</span>
                        <span class="text-gray-500 text-xs font-medium mb-0.5">En este periodo</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 mt-3">
                        <div class="bg-indigo-500 h-1.5 rounded-full transition-all" :style="`width: ${Math.min((currentPeriodData.taken_in_period.length / currentPeriodData.days_per_year) * 100, 100)}%`"></div>
                    </div>
                </div>

            </div>

            <!-- Sección de Ajustes y Tablas -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Historial de Días Tomados -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col">
                    <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm">Vacaciones Tomadas</h3>
                            <p class="text-xs text-gray-500">Durante el periodo seleccionado</p>
                        </div>
                        <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2.5 py-1 rounded-md">{{ currentPeriodData.taken_in_period.length }} días</span>
                    </div>
                    <div class="flex-1 p-0 overflow-y-auto max-h-[350px]">
                        <el-table :data="currentPeriodData.taken_in_period" style="width: 100%" stripe empty-text="No hay vacaciones registradas en este periodo.">
                            <el-table-column label="Fecha">
                                <template #default="scope">
                                    <span class="text-xs font-medium text-gray-700">
                                        {{ formatDate(scope.row.date) }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Estado">
                                <template #default>
                                    <span class="text-[10px] bg-green-50 text-green-600 px-2 py-1 rounded border border-green-100 font-semibold">Día Tomado</span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </div>

                <!-- Historial de Ajustes Manuales / Devengo -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col">
                    <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm">Registro de Saldo</h3>
                            <p class="text-xs text-gray-500">Devengo semanal y ajustes en el periodo</p>
                        </div>
                        <el-button 
                            v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                            @click="showAdjustmentModal = true" 
                            type="primary"
                            plain
                            size="small"
                            class="!border-indigo-200 !text-indigo-600 hover:!bg-indigo-600 hover:!text-white hover:!border-indigo-600"
                        >
                            <i class="fa-solid fa-plus mr-1.5"></i> Nuevo Ajuste
                        </el-button>
                    </div>
                    <div class="flex-1 p-0 overflow-y-auto max-h-[350px]">
                        <el-table :data="currentPeriodData.adjustments" style="width: 100%" stripe empty-text="No hay ajustes ni devengos registrados en este periodo.">
                            <el-table-column label="Fecha" width="110">
                                <template #default="scope">
                                    <span class="text-[11px] text-gray-500">{{ formatDate(scope.row.date) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Días" width="80" align="center">
                                <template #default="scope">
                                    <span class="font-bold text-[11px]" :class="scope.row.days > 0 ? 'text-green-600' : 'text-red-500'">
                                        {{ scope.row.days > 0 ? '+' : '' }}{{ scope.row.days }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Motivo">
                                <template #default="scope">
                                    <el-tooltip :content="scope.row.notes" placement="top" :disabled="scope.row.notes?.length < 30">
                                        <span class="text-[11px] text-gray-600 truncate block max-w-[150px]">
                                            {{ scope.row.notes }}
                                        </span>
                                    </el-tooltip>
                                </template>
                            </el-table-column>
                            <el-table-column align="right" width="60">
                                <template #default="scope">
                                    <el-button 
                                        v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                                        @click="confirmDeleteAdjustment(scope.row.id)"
                                        type="danger" 
                                        text 
                                        size="small"
                                        title="Revertir/Eliminar ajuste"
                                    >
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal de Ajuste Manual (Element Plus) -->
        <el-dialog
            v-model="showAdjustmentModal"
            title="Ajuste Manual de Vacaciones"
            width="450px"
            destroy-on-close
            class="!rounded-xl"
        >
            <div class="mt-2 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-3 items-start mb-6">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <p>Agrega días positivos (ej. <b>2</b>) para sumar al saldo del empleado, o números negativos (ej. <b>-1</b>) para descontarle días. El nuevo saldo se reflejará inmediatamente.</p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Cantidad de Días <span class="text-red-500">*</span></label>
                    <el-input-number 
                        v-model="form.days" 
                        :precision="2" 
                        :step="0.5" 
                        class="!w-full" 
                        controls-position="right"
                        size="large"
                    />
                    <span v-if="form.errors.days" class="text-xs text-red-500 mt-1 block">{{ form.errors.days }}</span>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Fecha del Ajuste <span class="text-red-500">*</span></label>
                    <el-date-picker 
                        v-model="form.date" 
                        type="date" 
                        class="!w-full" 
                        value-format="YYYY-MM-DD"
                        placeholder="Selecciona la fecha" 
                        size="large"
                    />
                    <span v-if="form.errors.date" class="text-xs text-red-500 mt-1 block">{{ form.errors.date }}</span>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Motivo / Justificación <span class="text-red-500">*</span></label>
                    <el-input 
                        v-model="form.notes" 
                        type="textarea" 
                        :rows="3" 
                        placeholder="Ej. Días extra otorgados por bono de productividad..." 
                    />
                    <span v-if="form.errors.notes" class="text-xs text-red-500 mt-1 block">{{ form.errors.notes }}</span>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-2">
                    <el-button @click="showAdjustmentModal = false">Cancelar</el-button>
                    <el-button 
                        type="primary" 
                        @click="submitAdjustment" 
                        :loading="form.processing"
                        class="!bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700"
                    >
                        Guardar Ajuste
                    </el-button>
                </div>
            </template>
        </el-dialog>

    </div>
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