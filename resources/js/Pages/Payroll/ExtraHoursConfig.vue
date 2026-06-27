<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import { ElNotification as notify } from 'element-plus';

const props = defineProps({
    payroll: Object,
    costs: Array,
    approvalLevels: Array,
    eligibleApprovers: Array,
    usersWithExtraTime: Array,
});

// --- SECCIÓN 1: COSTOS DE HORA EXTRA ---
const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Inicializar costos desde los datos existentes
const weekdayCost = ref(0);
const weekendCost = ref(0);
const specificCosts = ref(dayNames.map((name, idx) => ({
    day_of_week: idx,
    name,
    cost_per_hour: 0,
    enabled: false,
})));

// Cargar datos existentes
props.costs.forEach(cost => {
    if (cost.range_type === 'weekday') {
        weekdayCost.value = parseFloat(cost.cost_per_hour);
    } else if (cost.range_type === 'weekend') {
        weekendCost.value = parseFloat(cost.cost_per_hour);
    } else if (cost.range_type === 'specific' && cost.day_of_week !== null) {
        const spec = specificCosts.value.find(s => s.day_of_week === cost.day_of_week);
        if (spec) {
            spec.cost_per_hour = parseFloat(cost.cost_per_hour);
            spec.enabled = true;
        }
    }
});

const costsForm = useForm({
    costs: [],
});

const saveCosts = () => {
    const allCosts = [];

    // Costo entre semana
    if (weekdayCost.value > 0) {
        allCosts.push({ range_type: 'weekday', day_of_week: null, cost_per_hour: weekdayCost.value });
    }

    // Costo fin de semana
    if (weekendCost.value > 0) {
        allCosts.push({ range_type: 'weekend', day_of_week: null, cost_per_hour: weekendCost.value });
    }

    // Costos específicos por día
    specificCosts.value.filter(s => s.enabled && s.cost_per_hour > 0).forEach(s => {
        allCosts.push({ range_type: 'specific', day_of_week: s.day_of_week, cost_per_hour: s.cost_per_hour });
    });

    costsForm.costs = allCosts;
    costsForm.post(route('payrolls.extra-hours-costs.save', props.payroll.id), {
        preserveScroll: true,
        onSuccess: () => notify.success('Costos guardados correctamente'),
    });
};

// --- SECCIÓN 2: NIVELES DE AUTORIZACIÓN ---
const levels = ref([]);

// Inicializar niveles desde datos existentes
if (props.approvalLevels && props.approvalLevels.length > 0) {
    levels.value = props.approvalLevels.map(l => ({
        id: l.id,
        name: l.name || `Nivel ${l.level}`,
        approverIds: (l.approvers || []).map(a => a.id),
    }));
}

const levelsForm = useForm({
    levels: [],
});

const addLevel = () => {
    levels.value.push({
        id: null,
        name: `Nivel ${levels.value.length + 1}`,
        approverIds: [],
    });
};

const removeLevel = (index) => {
    levels.value.splice(index, 1);
};

const saveLevels = () => {
    // Validar que cada nivel tenga al menos un aprobador
    for (const level of levels.value) {
        if (!level.approverIds || level.approverIds.length === 0) {
            notify.error(`El nivel "${level.name}" debe tener al menos un aprobador.`);
            return;
        }
        if (!level.name || level.name.trim() === '') {
            notify.error('Todos los niveles deben tener un nombre.');
            return;
        }
    }

    levelsForm.levels = levels.value.map(l => ({
        name: l.name,
        approver_ids: l.approverIds,
    }));

    levelsForm.post(route('payrolls.extra-hours-levels.save', props.payroll.id), {
        preserveScroll: true,
        onSuccess: () => notify.success('Niveles de autorización guardados correctamente'),
    });
};

// --- Helpers ---
const getApproverById = (id) => {
    return props.eligibleApprovers.find(a => a.id === id);
};

const formatExtraTime = (minutes) => {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h ${m}m`;
};
</script>

<template>
    <AppLayout title="Configurar Horas Extra">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
                
                <!-- Header -->
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <Back :route="route('payrolls.show', payroll.id)" />
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Configuración de Horas Extra</h1>
                            <p class="text-sm text-gray-500">
                                Nómina #{{ payroll.id }} | Catorcena {{ payroll.biweekly }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Columna Izquierda: Costos + Niveles -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- SECCIÓN: Costos por Hora Extra -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h2 class="text-lg font-bold text-gray-800 mb-1 flex items-center gap-2">
                                <i class="fa-solid fa-dollar-sign text-green-600"></i> Costos por Hora Extra
                            </h2>
                            <p class="text-xs text-gray-500 mb-5">
                                Define el costo por hora extra. Los costos específicos por día tienen prioridad sobre los rangos generales.
                            </p>

                            <!-- Rangos generales -->
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                                    <label class="block text-sm font-bold text-blue-800 mb-2">
                                        <i class="fa-solid fa-calendar-day mr-1"></i> Entre Semana (L-V)
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-blue-600 font-bold">$</span>
                                        <el-input-number 
                                            v-model="weekdayCost" 
                                            :min="0" 
                                            :precision="2"
                                            :step="10"
                                            class="!w-full"
                                            controls-position="right"
                                            placeholder="0.00"
                                        />
                                        <span class="text-xs text-gray-500">/hora</span>
                                    </div>
                                </div>
                                <div class="bg-amber-50 rounded-lg p-4 border border-amber-100">
                                    <label class="block text-sm font-bold text-amber-800 mb-2">
                                        <i class="fa-solid fa-calendar-week mr-1"></i> Fin de Semana (S-D)
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-amber-600 font-bold">$</span>
                                        <el-input-number 
                                            v-model="weekendCost" 
                                            :min="0" 
                                            :precision="2"
                                            :step="10"
                                            class="!w-full"
                                            controls-position="right"
                                            placeholder="0.00"
                                        />
                                        <span class="text-xs text-gray-500">/hora</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Costos específicos por día -->
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                    <i class="fa-solid fa-sliders text-gray-500"></i> Costos Específicos por Día (Opcional)
                                    <span class="text-[10px] text-gray-400 font-normal">— Anulan el costo por rango para ese día</span>
                                </h3>
                                <div class="grid grid-cols-7 gap-2">
                                    <div 
                                        v-for="spec in specificCosts" 
                                        :key="spec.day_of_week"
                                        class="text-center"
                                    >
                                        <div 
                                            class="rounded-lg p-2 border-2 transition-all cursor-pointer"
                                            :class="spec.enabled 
                                                ? 'border-indigo-300 bg-indigo-50' 
                                                : 'border-gray-200 bg-white hover:border-gray-300'"
                                            @click="spec.enabled = !spec.enabled"
                                        >
                                            <p class="text-[10px] font-bold mb-1" 
                                               :class="spec.enabled ? 'text-indigo-700' : 'text-gray-500'"
                                               :title="spec.name">
                                                {{ spec.name.substring(0, 3) }}
                                            </p>
                                            <el-input-number
                                                v-if="spec.enabled"
                                                v-model="spec.cost_per_hour"
                                                :min="0"
                                                :precision="2"
                                                :step="5"
                                                size="small"
                                                class="!w-full"
                                                controls-position="right"
                                                @click.stop
                                            />
                                            <span v-else class="text-[10px] text-gray-300">—</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 pt-4 border-t border-gray-100 flex justify-end">
                                <el-button 
                                    type="primary" 
                                    @click="saveCosts" 
                                    :loading="costsForm.processing"
                                    class="!bg-indigo-600 !border-indigo-600"
                                >
                                    <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar Costos
                                </el-button>
                            </div>
                        </div>

                        <!-- SECCIÓN: Niveles de Autorización -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <div class="flex justify-between items-center mb-1">
                                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fa-solid fa-sitemap text-indigo-600"></i> Niveles de Autorización
                                </h2>
                                <el-button size="small" @click="addLevel" class="!rounded-lg">
                                    <i class="fa-solid fa-plus mr-1"></i> Agregar Nivel
                                </el-button>
                            </div>
                            <p class="text-xs text-gray-500 mb-5">
                                Define los niveles jerárquicos de aprobación. Todos los aprobadores de un nivel deben aprobar para pasar al siguiente.
                                Si alguien rechaza, el tiempo extra se considera rechazado.
                            </p>

                            <div v-if="levels.length === 0" class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                                <i class="fa-solid fa-layer-group text-gray-300 text-3xl mb-2"></i>
                                <p class="text-gray-400 text-sm">No hay niveles configurados.</p>
                                <p class="text-gray-400 text-xs mt-1">Agrega niveles para habilitar el flujo de autorización.</p>
                            </div>

                            <div v-else class="space-y-4">
                                <div 
                                    v-for="(level, index) in levels" 
                                    :key="index"
                                    class="bg-gray-50 rounded-lg p-4 border border-gray-200 relative"
                                >
                                    <!-- Indicador de nivel -->
                                    <span class="absolute -top-2 -left-2 w-7 h-7 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow">
                                        {{ index + 1 }}
                                    </span>
                                    <button 
                                        @click="removeLevel(index)" 
                                        class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 shadow"
                                        title="Eliminar nivel"
                                    >
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>

                                    <div class="ml-5 space-y-3">
                                        <!-- Nombre del nivel -->
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre del Nivel</label>
                                            <el-input 
                                                v-model="level.name" 
                                                placeholder="Ej. Supervisor Directo"
                                                size="small"
                                                maxlength="100"
                                            />
                                        </div>

                                        <!-- Aprobadores del nivel -->
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                                Aprobadores 
                                                <span class="text-gray-400 font-normal">({{ level.approverIds.length }} seleccionados)</span>
                                            </label>
                                            <el-select 
                                                v-model="level.approverIds" 
                                                multiple 
                                                filterable 
                                                placeholder="Selecciona los aprobadores"
                                                class="w-full"
                                                size="small"
                                            >
                                                <el-option 
                                                    v-for="approver in eligibleApprovers" 
                                                    :key="approver.id" 
                                                    :label="`${approver.name} (${approver.department || 'Sin depto'})`" 
                                                    :value="approver.id"
                                                >
                                                    <div class="flex items-center gap-2">
                                                        <img :src="approver.profile_photo_url" class="w-5 h-5 rounded-full object-cover">
                                                        <span>{{ approver.name }}</span>
                                                        <span class="text-xs text-gray-400">{{ approver.department }}</span>
                                                    </div>
                                                </el-option>
                                            </el-select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 pt-4 border-t border-gray-100 flex justify-end">
                                <el-button 
                                    type="primary" 
                                    @click="saveLevels" 
                                    :loading="levelsForm.processing"
                                    class="!bg-indigo-600 !border-indigo-600"
                                >
                                    <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar Niveles
                                </el-button>
                            </div>
                        </div>

                    </div>

                    <!-- Columna Derecha: Resumen de Usuarios con Tiempo Extra -->
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-4">
                            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-users text-amber-600"></i> 
                                Usuarios con Tiempo Extra
                                <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">{{ usersWithExtraTime.length }}</span>
                            </h3>

                            <div v-if="usersWithExtraTime.length === 0" class="text-center py-6 text-gray-400 text-xs">
                                <i class="fa-solid fa-circle-check text-green-300 text-2xl mb-1"></i>
                                <p>Sin tiempo extra pendiente</p>
                            </div>

                            <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                                <div 
                                    v-for="user in usersWithExtraTime" 
                                    :key="user.id"
                                    class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100"
                                >
                                    <img :src="user.profile_photo_url" class="w-9 h-9 rounded-full object-cover border border-gray-200 flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ user.name }}</p>
                                        <p class="text-[10px] text-gray-400">{{ user.department || 'General' }}</p>
                                    </div>
                                    <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full whitespace-nowrap">
                                        {{ user.total_extra_formatted }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </AppLayout>
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
