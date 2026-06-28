<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import { ElNotification as notify } from 'element-plus';

const props = defineProps({
    payroll: Object,
    costs: Array,
    approvalGroups: Array,
    eligibleApprovers: Array,
    eligibleEmployees: Array,
    usersWithExtraTime: Array,
    hasPreviousPayroll: Boolean,
});

// ─── SECCIÓN 1: Costos de hora extra ────────────────────────────
const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

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

const costsForm = useForm({ costs: [] });

const saveCosts = () => {
    const allCosts = [];
    if (weekdayCost.value > 0) {
        allCosts.push({ range_type: 'weekday', day_of_week: null, cost_per_hour: weekdayCost.value });
    }
    if (weekendCost.value > 0) {
        allCosts.push({ range_type: 'weekend', day_of_week: null, cost_per_hour: weekendCost.value });
    }
    specificCosts.value.filter(s => s.enabled && s.cost_per_hour > 0).forEach(s => {
        allCosts.push({ range_type: 'specific', day_of_week: s.day_of_week, cost_per_hour: s.cost_per_hour });
    });

    costsForm.costs = allCosts;
    costsForm.post(route('payrolls.extra-hours-costs.save', props.payroll.id), {
        preserveScroll: true,
        onSuccess: () => notify.success('Costos guardados correctamente'),
    });
};

// ─── SECCIÓN 2: Grupos de aprobación ────────────────────────────
const groups = ref([]);

// Inicializar grupos desde datos existentes
if (props.approvalGroups && props.approvalGroups.length > 0) {
    groups.value = props.approvalGroups.map(g => ({
        id: g.id,
        name: g.name || '',
        employeeIds: g.employee_ids || [],
        levels: (g.levels || []).map(l => ({
            id: l.id,
            name: l.name || '',
            approverIds: l.approver_ids || [],
        })),
    }));
}

const groupsForm = useForm({ groups: [] });

const addGroup = () => {
    groups.value.push({
        id: null,
        name: '',
        employeeIds: [],
        levels: [{ id: null, name: '', approverIds: [] }],
    });
};

const removeGroup = (index) => {
    groups.value.splice(index, 1);
};

const addLevel = (groupIndex) => {
    groups.value[groupIndex].levels.push({
        id: null,
        name: '',
        approverIds: [],
    });
};

const removeLevel = (groupIndex, levelIndex) => {
    groups.value[groupIndex].levels.splice(levelIndex, 1);
};

const saveGroups = () => {
    for (let gi = 0; gi < groups.value.length; gi++) {
        const g = groups.value[gi];
        if (!g.employeeIds || g.employeeIds.length === 0) {
            notify.error(`El grupo "${g.name || '#' + (gi + 1)}" debe tener al menos un empleado.`);
            return;
        }
        if (!g.levels || g.levels.length === 0) {
            notify.error(`El grupo "${g.name || '#' + (gi + 1)}" debe tener al menos un nivel.`);
            return;
        }
        for (let li = 0; li < g.levels.length; li++) {
            const l = g.levels[li];
            if (!l.approverIds || l.approverIds.length === 0) {
                notify.error(`El nivel "${l.name || '#' + (li + 1)}" del grupo "${g.name || '#' + (gi + 1)}" debe tener al menos un aprobador.`);
                return;
            }
        }
    }

    groupsForm.groups = groups.value.map(g => ({
        name: g.name,
        employee_ids: g.employeeIds,
        levels: g.levels.map(l => ({
            name: l.name,
            approver_ids: l.approverIds,
        })),
    }));

    groupsForm.post(route('payrolls.extra-hours-groups.save', props.payroll.id), {
        preserveScroll: true,
        onSuccess: () => notify.success('Grupos de autorización guardados correctamente'),
    });
};

// ─── Copia rápida ────────────────────────────────────────────────
const copyForm = useForm({});

const copyFromPrevious = () => {
    copyForm.post(route('payrolls.extra-hours-copy', props.payroll.id), {
        preserveScroll: true,
        onSuccess: () => {
            notify.success('Configuración copiada. Recarga la página para ver los cambios.');
            setTimeout(() => window.location.reload(), 1000);
        },
    });
};

// ─── Helpers ─────────────────────────────────────────────────────
const getApproverById = (id) => props.eligibleApprovers.find(a => a.id === id);
const getEmployeeById = (id) => props.eligibleEmployees.find(e => e.id === id);

const formatExtraTime = (minutes) => {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h ${m}m`;
};
</script>

<template>
    <AppLayout title="Configurar horas extra">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">

                <!-- Header -->
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <Back :route="route('payrolls.show', payroll.id)" />
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Configuración de horas extra</h1>
                            <p class="text-sm text-gray-500">
                                Nómina #{{ payroll.id }} | Catorcena {{ payroll.biweekly }}
                            </p>
                        </div>
                    </div>

                    <!-- Botón de copia rápida -->
                    <el-button
                        v-if="hasPreviousPayroll"
                        type="warning"
                        plain
                        @click="copyFromPrevious"
                        :loading="copyForm.processing"
                        class="!rounded-lg"
                    >
                        <i class="fa-solid fa-copy mr-2"></i> Copiar de catorcena anterior
                    </el-button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Columna izquierda: Costos + Grupos -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- ─── SECCIÓN: Costos por hora extra ─── -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h2 class="text-lg font-bold text-gray-800 mb-1 flex items-center gap-2">
                                <i class="fa-solid fa-dollar-sign text-green-600"></i> Costos por hora extra
                            </h2>
                            <p class="text-xs text-gray-500 mb-5">
                                Define el costo por hora extra. Los costos específicos por día tienen prioridad sobre los rangos generales.
                            </p>

                            <!-- Rangos generales -->
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                                    <label class="block text-sm font-bold text-blue-800 mb-2">
                                        <i class="fa-solid fa-calendar-day mr-1"></i> Entre semana (L-V)
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
                                        <i class="fa-solid fa-calendar-week mr-1"></i> Fin de semana (S-D)
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

                            <!-- Costos específicos por día (recuadros más grandes) -->
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                    <i class="fa-solid fa-sliders text-gray-500"></i> Costos específicos por día (opcional)
                                    <span class="text-[10px] text-gray-400 font-normal">— Anulan el costo por rango para ese día</span>
                                </h3>
                                <div class="grid grid-cols-4 gap-2">
                                    <div
                                        v-for="spec in specificCosts"
                                        :key="spec.day_of_week"
                                        class="text-center"
                                    >
                                        <div
                                            class="rounded-lg p-3 border-2 transition-all cursor-pointer min-h-[76px] flex flex-col items-center justify-center"
                                            :class="spec.enabled
                                                ? 'border-indigo-300 bg-indigo-50'
                                                : 'border-gray-200 bg-white hover:border-gray-300'"
                                            @click="spec.enabled = !spec.enabled"
                                        >
                                            <p class="text-xs font-bold mb-1.5"
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
                                                size="default"
                                                class="!w-full"
                                                controls-position="right"
                                                @click.stop
                                            />
                                            <span v-else class="text-xs text-gray-300">—</span>
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
                                    <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar costos
                                </el-button>
                            </div>
                        </div>

                        <!-- ─── SECCIÓN: Grupos de autorización ─── -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <div class="flex justify-between items-center mb-1">
                                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fa-solid fa-users-gear text-indigo-600"></i> Grupos de autorización
                                </h2>
                                <el-button size="small" @click="addGroup" class="!rounded-lg">
                                    <i class="fa-solid fa-plus mr-1"></i> Agregar grupo
                                </el-button>
                            </div>
                            <p class="text-xs text-gray-500 mb-5">
                                Cada grupo define un conjunto de empleados con su propia cadena de aprobación.
                                Todos los aprobadores de un nivel deben aprobar para pasar al siguiente.
                                Si alguien rechaza, el tiempo extra se considera rechazado.
                            </p>

                            <!-- Sin grupos -->
                            <div v-if="groups.length === 0" class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                                <i class="fa-solid fa-layer-group text-gray-300 text-3xl mb-2"></i>
                                <p class="text-gray-400 text-sm">No hay grupos configurados.</p>
                                <p class="text-gray-400 text-xs mt-1">Agrega grupos para definir cadenas de autorización por equipos.</p>
                            </div>

                            <!-- Lista de grupos -->
                            <div v-else class="space-y-6">
                                <div
                                    v-for="(group, gi) in groups"
                                    :key="gi"
                                    class="bg-gray-50 rounded-xl p-5 border border-gray-200 relative"
                                >
                                    <!-- Badge grupo -->
                                    <span class="absolute -top-2.5 -left-2.5 bg-indigo-600 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full shadow">
                                        Grupo {{ gi + 1 }}
                                    </span>
                                    <button
                                        @click="removeGroup(gi)"
                                        class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 shadow z-10"
                                        title="Eliminar grupo"
                                    >
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>

                                    <div class="space-y-4 mt-2">
                                        <!-- Nombre del grupo -->
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre del grupo</label>
                                            <el-input
                                                v-model="group.name"
                                                placeholder="Ej. equipo de almacén"
                                                size="small"
                                                maxlength="100"
                                            />
                                        </div>

                                        <!-- Empleados del grupo -->
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                                Empleados en este grupo
                                                <span class="text-gray-400 font-normal">({{ group.employeeIds.length }} seleccionados)</span>
                                            </label>
                                            <el-select
                                                v-model="group.employeeIds"
                                                multiple
                                                filterable
                                                placeholder="Selecciona los empleados de este grupo"
                                                class="w-full"
                                                size="small"
                                            >
                                                <el-option
                                                    v-for="emp in eligibleEmployees"
                                                    :key="emp.id"
                                                    :label="`${emp.name} (${emp.department || 'Sin depto'})`"
                                                    :value="emp.id"
                                                >
                                                    <div class="flex items-center gap-2">
                                                        <img :src="emp.profile_photo_url" class="w-5 h-5 rounded-full object-cover">
                                                        <span>{{ emp.name }}</span>
                                                        <span class="text-xs text-gray-400">{{ emp.department }}</span>
                                                    </div>
                                                </el-option>
                                            </el-select>
                                        </div>

                                        <!-- Niveles de aprobación del grupo -->
                                        <div class="border-t border-gray-200 pt-3">
                                            <div class="flex justify-between items-center mb-3">
                                                <span class="text-xs font-semibold text-gray-600">Niveles de aprobación</span>
                                                <el-button size="small" @click="addLevel(gi)" text class="!text-indigo-600 !text-xs">
                                                    <i class="fa-solid fa-plus mr-1"></i> Agregar nivel
                                                </el-button>
                                            </div>

                                            <div v-if="group.levels.length === 0" class="text-xs text-gray-400 italic py-2">
                                                Sin niveles. Agrega al menos uno.
                                            </div>

                                            <div v-else class="space-y-3">
                                                <div
                                                    v-for="(level, li) in group.levels"
                                                    :key="li"
                                                    class="bg-white rounded-lg p-3 border border-gray-200 relative"
                                                >
                                                    <!-- Indicador de nivel -->
                                                    <span class="absolute -top-2 -left-2 w-6 h-6 bg-amber-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow">
                                                        {{ li + 1 }}
                                                    </span>
                                                    <button
                                                        v-if="group.levels.length > 1"
                                                        @click="removeLevel(gi, li)"
                                                        class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-400 text-white rounded-full flex items-center justify-center text-[9px] hover:bg-red-500"
                                                        title="Quitar nivel"
                                                    >
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>

                                                    <div class="ml-4 space-y-2">
                                                        <div>
                                                            <label class="block text-[11px] font-semibold text-gray-500 mb-0.5">Nombre del nivel</label>
                                                            <el-input
                                                                v-model="level.name"
                                                                placeholder="Ej. supervisor directo"
                                                                size="small"
                                                                maxlength="100"
                                                            />
                                                        </div>
                                                        <div>
                                                            <label class="block text-[11px] font-semibold text-gray-500 mb-0.5">
                                                                Aprobadores
                                                                <span class="text-gray-400 font-normal">({{ level.approverIds.length }})</span>
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
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 pt-4 border-t border-gray-100 flex justify-end">
                                <el-button
                                    type="primary"
                                    @click="saveGroups"
                                    :loading="groupsForm.processing"
                                    class="!bg-indigo-600 !border-indigo-600"
                                >
                                    <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar grupos
                                </el-button>
                            </div>
                        </div>

                    </div>

                    <!-- Columna derecha: panel lateral -->
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-4">
                            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-users text-amber-600"></i>
                                Usuarios con tiempo extra
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
