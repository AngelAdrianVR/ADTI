<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ElNotification } from "element-plus";
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    holidays: Array,
});

// --- Estado ---
const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const showModal = ref(false);
const editFlag = ref(false);
const currentHolidayId = ref(null);

// Formulario
const form = useForm({
    name: null,
    is_active: true,
    is_custom_date: false,
    date: null, 
    day: null, 
    month: null, 
    ordinal: null, 
    week_day: null, 
});

// --- Catálogos Estáticos ---
const months = [
    { value: 1, label: 'Enero' }, { value: 2, label: 'Febrero' }, { value: 3, label: 'Marzo' },
    { value: 4, label: 'Abril' }, { value: 5, label: 'Mayo' }, { value: 6, label: 'Junio' },
    { value: 7, label: 'Julio' }, { value: 8, label: 'Agosto' }, { value: 9, label: 'Septiembre' },
    { value: 10, label: 'Octubre' }, { value: 11, label: 'Noviembre' }, { value: 12, label: 'Diciembre' },
];

const weekDays = [
    { value: 0, label: 'Domingo' }, { value: 1, label: 'Lunes' }, { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' }, { value: 4, label: 'Jueves' }, { value: 5, label: 'Viernes' }, { value: 6, label: 'Sábado' },
];

const ordinals = [
    { value: 1, label: 'Primer' }, { value: 2, label: 'Segundo' }, { value: 3, label: 'Tercer' },
    { value: 4, label: 'Cuarto' }, { value: 5, label: 'Último' },
];

// --- Computed ---
const filteredHolidays = computed(() => {
    if (!search.value) return props.holidays;
    const lowerSearch = search.value.toLowerCase();
    
    return props.holidays.filter(item => 
        item.name.toLowerCase().includes(lowerSearch)
    );
});

const paginatedHolidays = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredHolidays.value.slice(start, end);
});

// --- Métodos ---

const formatHolidayDate = (row) => {
    if (row.is_custom_date) {
        const ord = ordinals.find(o => o.value === Number(row.ordinal))?.label || '';
        const day = weekDays.find(d => d.value === Number(row.week_day))?.label || '';
        const month = months.find(m => m.value === Number(row.month))?.label || '';
        return `${ord} ${day} de ${month}`;
    } else {
        if (!row.date) return '-';
        const dateObj = new Date(row.date);
        return format(dateObj, 'dd MMMM', { locale: es });
    }
};

const handlePageChange = (val) => {
    currentPage.value = val;
};

const openCreate = () => {
    editFlag.value = false;
    currentHolidayId.value = null;
    form.reset();
    form.is_active = true;
    showModal.value = true;
};

const openEdit = (item) => {
    editFlag.value = true;
    currentHolidayId.value = item.id;
    
    form.name = item.name;
    form.is_active = !!item.is_active;
    form.is_custom_date = !!item.is_custom_date;
    
    if (item.is_custom_date) {
        form.date = null;
        form.ordinal = item.ordinal;
        form.week_day = item.week_day;
        form.month = Number(item.month); // Asegurar número
    } else {
        form.date = item.date; 
        form.ordinal = null;
        form.week_day = null;
        form.month = null;
        // Opcional: si quieres pre-llenar day/month aunque uses date picker
        // const d = item.date.split('-');
        // form.month = parseInt(d[1]);
        // form.day = parseInt(d[2]);
    }
    
    showModal.value = true;
};

const submit = () => {
    // CORRECCIÓN: Preparar day y month si es fecha fija para que pase la validación
    if (!form.is_custom_date && form.date) {
        const [year, month, day] = form.date.split('-');
        form.day = parseInt(day);
        form.month = parseInt(month);
    }

    if (editFlag.value) {
        form.put(route('holidays.update', currentHolidayId.value), {
            onSuccess: () => {
                ElNotification.success('Día festivo actualizado');
                showModal.value = false;
                form.reset();
            },
            onError: () => ElNotification.error('Error al actualizar')
        });
    } else {
        form.post(route('holidays.store'), {
            onSuccess: () => {
                ElNotification.success('Día festivo creado');
                showModal.value = false;
                form.reset();
            },
            onError: () => ElNotification.error('Error al crear')
        });
    }
};

const deleteHoliday = (id) => {
    router.delete(route('holidays.destroy', id), {
        onSuccess: () => ElNotification.success('Eliminado correctamente'),
        onError: () => ElNotification.error('No se pudo eliminar')
    });
};
</script>

<template>
    <AppLayout title="Días festivos">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Días Festivos</h1>
                        <p class="text-xs text-gray-500 mt-1">Configuración del calendario laboral.</p>
                    </div>
                    
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <!-- Buscador -->
                        <div class="relative w-full sm:w-72">
                            <input 
                                v-model="search" 
                                type="text" 
                                placeholder="Buscar día festivo..." 
                                class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2] text-sm shadow-sm"
                            >
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                        </div>

                        <!-- Botón Crear -->
                        <button 
                            v-if="$page.props.auth.user.permissions?.includes('Crear dias festivos')"
                            @click="openCreate"
                            class="bg-[#1676A2] hover:bg-[#125d80] text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2 shrink-0"
                        >
                            <i class="fa-solid fa-plus"></i> Agregar
                        </button>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <el-table 
                        :data="paginatedHolidays" 
                        style="width: 100%"
                        :row-class-name="'hover:bg-gray-50 transition-colors'"
                    >
                        <el-table-column label="Nombre" min-width="200">
                            <template #default="scope">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center border border-purple-100">
                                        <i class="fa-regular fa-calendar-check text-xs"></i>
                                    </div>
                                    <span class="font-bold text-gray-700">{{ scope.row.name }}</span>
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column label="Fecha / Regla" min-width="200">
                            <template #default="scope">
                                <span v-if="scope.row.is_custom_date" class="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-1 rounded border border-gray-200">
                                    <i class="fa-solid fa-code-branch mr-1"></i> Dinámica
                                </span>
                                <span v-else class="text-xs font-mono text-[#1676A2] bg-blue-50 px-2 py-1 rounded border border-blue-100">
                                    <i class="fa-regular fa-calendar mr-1"></i> Fija
                                </span>
                                <span class="ml-2 text-sm text-gray-600">{{ formatHolidayDate(scope.row) }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column label="Estatus" width="120" align="center">
                            <template #default="scope">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="scope.row.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                >
                                    {{ scope.row.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </template>
                        </el-table-column>

                        <el-table-column align="right" width="120">
                            <template #default="scope">
                                <div class="flex items-center justify-end gap-1">
                                    <button 
                                        v-if="$page.props.auth.user.permissions?.includes('Editar dias festivos')"
                                        @click="openEdit(scope.row)" 
                                        class="p-2 text-gray-400 hover:text-[#1676A2] hover:bg-blue-50 rounded-full transition-colors"
                                        title="Editar"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <el-popconfirm 
                                        v-if="$page.props.auth.user.permissions?.includes('Eliminar dias festivos')"
                                        confirm-button-text="Sí, eliminar" 
                                        cancel-button-text="No" 
                                        icon-color="#DC2626" 
                                        title="¿Eliminar este día festivo?"
                                        @confirm="deleteHoliday(scope.row.id)"
                                    >
                                        <template #reference>
                                            <button class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-full transition-colors" title="Eliminar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </template>
                                    </el-popconfirm>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- Pagination -->
                    <div class="px-4 py-3 border-t border-gray-100 flex justify-end bg-gray-50">
                        <el-pagination 
                            layout="prev, pager, next" 
                            :total="filteredHolidays.length" 
                            :page-size="itemsPerPage"
                            @current-change="handlePageChange"
                            background
                            small
                        />
                    </div>
                </div>

            </div>
        </main>

        <!-- Modal Crear/Editar -->
        <DialogModal :show="showModal" @close="showModal = false" maxWidth="md">
            <template #title>
                <span class="font-bold text-gray-800">{{ editFlag ? 'Editar Día Festivo' : 'Nuevo Día Festivo' }}</span>
            </template>
            <template #content>
                <div class="space-y-4">
                    
                    <!-- Nombre -->
                    <div>
                        <InputLabel value="Nombre de la festividad *" />
                        <TextInput v-model="form.name" class="w-full mt-1" placeholder="Ej. Navidad" autofocus />
                        <InputError :message="form.errors.name" />
                    </div>

                    <!-- Tipo de Fecha -->
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <label class="flex items-center cursor-pointer mb-3">
                            <input type="checkbox" v-model="form.is_custom_date" class="rounded border-gray-300 text-[#1676A2] shadow-sm focus:ring-[#1676A2]">
                            <span class="ml-2 text-sm text-gray-700 font-medium">Es una regla dinámica (Ej. 3er Lunes de Nov)</span>
                        </label>

                        <!-- Opción A: Fecha Fija -->
                        <div v-if="!form.is_custom_date" class="animate-fade-in">
                            <InputLabel value="Fecha *" />
                            <TextInput type="date" v-model="form.date" class="w-full mt-1" />
                            <InputError :message="form.errors.date" />
                        </div>

                        <!-- Opción B: Regla Dinámica -->
                        <div v-else class="grid grid-cols-2 gap-3 animate-fade-in">
                            <div class="col-span-2">
                                <InputLabel value="Ocurre el: *" />
                                <div class="flex gap-2 mt-1">
                                    <select v-model="form.ordinal" class="w-1/2 border-gray-300 rounded-md text-sm focus:border-[#1676A2] focus:ring-[#1676A2]">
                                        <option :value="null" disabled>Orden</option>
                                        <option v-for="ord in ordinals" :key="ord.value" :value="ord.value">{{ ord.label }}</option>
                                    </select>
                                    <select v-model="form.week_day" class="w-1/2 border-gray-300 rounded-md text-sm focus:border-[#1676A2] focus:ring-[#1676A2]">
                                        <option :value="null" disabled>Día</option>
                                        <option v-for="day in weekDays" :key="day.value" :value="day.value">{{ day.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-span-2">
                                <InputLabel value="Del mes de: *" />
                                <select v-model="form.month" class="w-full mt-1 border-gray-300 rounded-md text-sm focus:border-[#1676A2] focus:ring-[#1676A2]">
                                    <option :value="null" disabled>Selecciona un mes</option>
                                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Estatus -->
                    <div>
                        <label class="flex items-center cursor-pointer">
                            <div class="relative">
                                <input type="checkbox" v-model="form.is_active" class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                            </div>
                            <span class="ml-3 text-sm font-medium text-gray-700">Activo (Aplica en nóminas)</span>
                        </label>
                    </div>

                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showModal = false" class="mr-2">Cancelar</SecondaryButton>
                <PrimaryButton @click="submit" :disabled="form.processing">
                    {{ editFlag ? 'Guardar Cambios' : 'Crear Festivo' }}
                </PrimaryButton>
            </template>
        </DialogModal>

    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>