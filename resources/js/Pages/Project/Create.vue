<script setup>
import { computed, ref } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus,
    Delete,
    Back,
    CircleCheck,
    Connection,
    Setting,
    Search,
    MagicStick,
    InfoFilled
} from '@element-plus/icons-vue';
import { ElNotification } from "element-plus";

const props = defineProps({
    departments: {
        type: Array,
        default: () => []
    },
    defaultTasks: { // Catálogo recibido del back
        type: Array,
        default: () => []
    }
});

const form = useForm({
    name: '',
    client: '',
    start_date: '',
    estimated_end_date: '',
    description: '',
    tasks: [
        { department_id: '', description: '', hours: 0 }
    ]
});

// --- Estado Modal Gestión Tareas ---
const showTaskCatalogModal = ref(false);
const catalogForm = useForm({
    name: '',
    department_id: null
});
const catalogSearch = ref('');
const quickFillDepartmentId = ref(null); // Estado para el selector de llenado rápido

// --- Computed ---
const totalBudgetedHours = computed(() => {
    return form.tasks.reduce((sum, task) => sum + Number(task.hours || 0), 0);
});

const filteredCatalog = computed(() => {
    if (!catalogSearch.value) return props.defaultTasks;
    const q = catalogSearch.value.toLowerCase();
    return props.defaultTasks.filter(t => t.name.toLowerCase().includes(q));
});

// --- Métodos Formulario Principal ---
const addTask = () => {
    form.tasks.push({ department_id: '', description: '', hours: 0 });
};

const removeTask = (index) => {
    if (form.tasks.length > 0) {
        form.tasks.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('projects.store'));
};

// --- Lógica de Llenado Rápido (NUEVO) ---
const handleQuickFill = () => {
    if (!quickFillDepartmentId.value) return;

    // 1. Buscar tareas del catálogo para este departamento
    const tasksToAdd = props.defaultTasks.filter(t => t.department_id === quickFillDepartmentId.value);

    if (tasksToAdd.length === 0) {
        ElNotification.warning('No hay tareas predefinidas en el catálogo para este departamento.');
        quickFillDepartmentId.value = null;
        return;
    }

    // 2. Si la lista tiene solo una fila vacía, la limpiamos antes de agregar
    if (form.tasks.length === 1 && !form.tasks[0].description && !form.tasks[0].department_id && form.tasks[0].hours === 0) {
        form.tasks = [];
    }

    // 3. Agregar tareas
    tasksToAdd.forEach(task => {
        form.tasks.push({
            department_id: task.department_id,
            description: task.name,
            hours: 0 // Se inicia en 0 para que el usuario defina el presupuesto
        });
    });

    ElNotification.success(`${tasksToAdd.length} tareas agregadas del catálogo.`);
    quickFillDepartmentId.value = null; // Resetear selector
};

// --- Lógica de Autocompletado ---
const querySearch = (queryString, cb, departmentId) => {
    let results = props.defaultTasks;

    if (departmentId) {
        const deptTasks = results.filter(t => t.department_id === departmentId);
        const otherTasks = results.filter(t => t.department_id !== departmentId);

        if (!queryString) {
            results = [...deptTasks, ...otherTasks];
        }
    }

    if (queryString) {
        results = results.filter(item =>
            item.name.toLowerCase().includes(queryString.toLowerCase())
        );
    }

    const suggestions = results.map(item => ({
        value: item.name,
        department: item.department?.name,
        id: item.id
    }));

    cb(suggestions);
};

const handleSelectTask = (item, index) => {
    const taskDef = props.defaultTasks.find(t => t.id === item.id);
    if (taskDef?.department_id && !form.tasks[index].department_id) {
        form.tasks[index].department_id = taskDef.department_id;
    }
};

// --- Métodos Catálogo ---
const addDefaultTask = () => {
    catalogForm.post(route('default-tasks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            ElNotification.success('Tarea agregada al catálogo');
            catalogForm.reset();
        },
        onError: () => ElNotification.error('Error al agregar tarea')
    });
};

const deleteDefaultTask = (id) => {
    router.delete(route('default-tasks.destroy', id), {
        preserveScroll: true,
        onSuccess: () => ElNotification.success('Tarea eliminada del catálogo')
    });
};
</script>

<template>
    <AppLayout title="Crear Proyecto">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">

                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Nuevo Proyecto</h1>
                        <p class="text-sm text-gray-500">Define los detalles generales y desglosa el presupuesto por
                            tareas.</p>
                    </div>
                    <Link :href="route('projects.index')"
                        class="text-[#1676A2] hover:underline text-sm font-medium flex items-center">
                        <el-icon class="mr-1">
                            <Back />
                        </el-icon> Volver al listado
                    </Link>
                </div>

                <el-form :model="form" @submit.prevent="submit" label-position="top" class="space-y-6">

                    <!-- Sección 1: Detalles Generales -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2
                            class="text-lg font-semibold text-gray-700 mb-4 border-b border-gray-100 pb-2 flex items-center">
                            <span class="w-1 h-6 bg-[#1676A2] rounded mr-2"></span>
                            Información General
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <el-form-item label="Nombre del Proyecto" :error="form.errors.name" required>
                                <el-input v-model="form.name" placeholder="Ej. Remodelación Oficinas" />
                            </el-form-item>

                            <el-form-item label="Cliente" :error="form.errors.client" required>
                                <el-input v-model="form.client" placeholder="Ej. Grupo Modelo" />
                            </el-form-item>

                            <el-form-item label="Fecha de Inicio" :error="form.errors.start_date">
                                <el-date-picker v-model="form.start_date" type="date" placeholder="Selecciona una fecha"
                                    format="DD/MM/YYYY" value-format="YYYY-MM-DD" class="!w-full" />
                            </el-form-item>

                            <el-form-item label="Fecha Estimada Fin" :error="form.errors.estimated_end_date">
                                <el-date-picker v-model="form.estimated_end_date" type="date"
                                    placeholder="Selecciona una fecha" format="DD/MM/YYYY" value-format="YYYY-MM-DD"
                                    class="!w-full" />
                            </el-form-item>

                            <div class="md:col-span-2">
                                <el-form-item label="Descripción / Notas" :error="form.errors.description">
                                    <el-input v-model="form.description" type="textarea" :rows="3"
                                        placeholder="Detalles adicionales del proyecto..." />
                                </el-form-item>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Desglose de Tareas (Presupuesto) -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div
                            class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 border-b border-gray-100 pb-2 gap-3">
                            <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                                <el-icon class="mr-2 text-[#1676A2]">
                                    <Connection />
                                </el-icon>
                                Planificación de Tareas
                            </h2>

                            <div class="flex items-center gap-3">
                                <div class="text-right bg-blue-50 px-3 py-1 rounded-lg border border-blue-100">
                                    <span class="text-xs text-gray-500 uppercase font-bold block">Total
                                        Presupuestado</span>
                                    <p class="text-lg font-bold text-[#1676A2]">{{ totalBudgetedHours.toFixed(1) }} hrs
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- BARRA DE HERRAMIENTAS DE TAREAS (NUEVO) -->
                        <div
                            class="bg-gray-50 p-3 rounded-lg border border-gray-200 mb-4 flex flex-col md:flex-row items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-gray-500 uppercase">Herramientas:</span>
                                <button type="button" @click="showTaskCatalogModal = true"
                                    class="text-xs text-[#1676A2] hover:underline flex items-center bg-white px-2 py-1 rounded border border-gray-200 hover:border-[#1676A2] transition-colors">
                                    <el-icon class="mr-1">
                                        <Setting />
                                    </el-icon> Gestionar Catálogo
                                </button>
                            </div>

                            <div class="flex items-center gap-2 w-full md:w-auto">
                                <span class="text-xs text-gray-500">Llenado rápido:</span>
                                <el-select v-model="quickFillDepartmentId" placeholder="Selecciona departamento..."
                                    size="small" class="!w-48" filterable @change="handleQuickFill">
                                    <template #prefix><el-icon class="text-[#1676A2]">
                                            <MagicStick />
                                        </el-icon></template>
                                    <el-option v-for="dept in departments" :key="dept.id" :label="dept.name"
                                        :value="dept.id" />
                                </el-select>
                                <el-tooltip
                                    content="Agrega automáticamente todas las tareas del catálogo asociadas al departamento seleccionado"
                                    placement="top">
                                    <el-icon class="text-gray-400 cursor-help">
                                        <InfoFilled />
                                    </el-icon>
                                </el-tooltip>
                            </div>
                        </div>

                        <div
                            class="hidden md:grid grid-cols-12 gap-4 text-xs font-bold text-gray-500 uppercase px-2 mb-2">
                            <div class="col-span-3">Departamento</div>
                            <div class="col-span-6">Tarea / Descripción</div>
                            <div class="col-span-2">Horas Est.</div>
                            <div class="col-span-1"></div>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(task, index) in form.tasks" :key="index"
                                class="relative bg-gray-50 rounded-lg p-3 md:p-2 border border-gray-200 transition-all hover:border-[#1676A2]/30">
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start md:items-center">

                                    <!-- Departamento -->
                                    <div class="col-span-3">
                                        <el-form-item :error="form.errors[`tasks.${index}.department_id`]"
                                            class="!mb-0">
                                            <el-select v-model="task.department_id" placeholder="Departamento"
                                                class="!w-full" filterable>
                                                <el-option v-for="dept in departments" :key="dept.id" :label="dept.name"
                                                    :value="dept.id" />
                                            </el-select>
                                        </el-form-item>
                                    </div>

                                    <!-- Descripción (AUTOCOMPLETE) -->
                                    <div class="col-span-6">
                                        <el-form-item :error="form.errors[`tasks.${index}.description`]" class="!mb-0">
                                            <el-autocomplete v-model="task.description"
                                                :fetch-suggestions="(qs, cb) => querySearch(qs, cb, task.department_id)"
                                                placeholder="Ej. Instalación eléctrica" class="!w-full" clearable
                                                @select="(item) => handleSelectTask(item, index)">
                                                <template #default="{ item }">
                                                    <div class="flex justify-between w-full">
                                                        <span>{{ item.value }}</span>
                                                        <span class="text-xs text-gray-400 ml-2"
                                                            v-if="item.department">{{ item.department }}</span>
                                                    </div>
                                                </template>
                                            </el-autocomplete>
                                        </el-form-item>
                                    </div>

                                    <!-- Horas -->
                                    <div class="col-span-2">
                                        <el-form-item :error="form.errors[`tasks.${index}.hours`]" class="!mb-0">
                                            <el-input-number v-model="task.hours" :min="0" :step="0.5"
                                                controls-position="right" class="!w-full" />
                                        </el-form-item>
                                    </div>

                                    <!-- Eliminar -->
                                    <div class="col-span-1 text-center flex justify-end">
                                        <el-button type="danger" link :icon="Delete" @click="removeTask(index)"
                                            title="Eliminar fila" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <el-button @click="addTask" plain type="primary" :icon="Plus" size="small">
                                Agregar otra tarea
                            </el-button>
                        </div>

                        <div v-if="form.errors.tasks" class="text-red-500 text-xs mt-2">
                            {{ form.errors.tasks }}
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex justify-end gap-3 pt-4">
                        <Link :href="route('projects.index')">
                            <el-button>Cancelar</el-button>
                        </Link>
                        <el-button type="primary" @click="submit" :loading="form.processing" color="#1676A2">
                            <el-icon class="mr-2">
                                <CircleCheck />
                            </el-icon> Crear Proyecto
                        </el-button>
                    </div>

                </el-form>
            </div>
        </main>

        <!-- MODAL GESTIÓN CATÁLOGO -->
        <el-dialog v-model="showTaskCatalogModal" title="Catálogo de tareas frecuentes" width="450px" align-center
            destroy-on-close class="!rounded-xl">
            <div class="space-y-4">
                <div class="flex gap-2">
                    <div class="flex-1 space-y-2">
                        <el-input v-model="catalogForm.name" placeholder="Nombre de la nueva tarea..." />
                        <el-select v-model="catalogForm.department_id" placeholder="Depto. (Opcional)" class="!w-full"
                            clearable>
                            <el-option v-for="dept in departments" :key="dept.id" :label="dept.name" :value="dept.id" />
                        </el-select>
                    </div>
                    <el-button type="primary" color="#1676A2" @click="addDefaultTask"
                        :disabled="!catalogForm.name || catalogForm.processing" class="h-auto">
                        <el-icon>
                            <Plus />
                        </el-icon>
                    </el-button>
                </div>
                <div v-if="catalogForm.errors.name" class="text-xs text-red-500">{{ catalogForm.errors.name }}</div>

                <div class="border-t border-gray-100 pt-4">
                    <el-input v-model="catalogSearch" placeholder="Buscar en catálogo..." size="small" class="mb-3">
                        <template #prefix><el-icon>
                                <Search />
                            </el-icon></template>
                    </el-input>

                    <div class="max-h-60 overflow-y-auto border border-gray-100 rounded-lg">
                        <div v-if="filteredCatalog.length === 0" class="p-4 text-center text-xs text-gray-400">
                            No se encontraron tareas.
                        </div>
                        <div v-for="item in filteredCatalog" :key="item.id"
                            class="flex justify-between items-center p-2 hover:bg-gray-50 border-b border-gray-50 last:border-0 text-sm">
                            <div>
                                <span class="text-gray-700">{{ item.name }}</span>
                                <span v-if="item.department"
                                    class="ml-2 text-[10px] bg-blue-50 text-[#1676A2] px-1.5 rounded">{{
                                    item.department.name
                                    }}</span>
                            </div>
                            <el-popconfirm title="¿Eliminar del catálogo?" confirm-button-text="Sí"
                                cancel-button-text="No" @confirm="deleteDefaultTask(item.id)">
                                <template #reference>
                                    <el-button type="danger" link size="small"><el-icon>
                                            <Delete />
                                        </el-icon></el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <el-button @click="showTaskCatalogModal = false">Cerrar</el-button>
            </template>
        </el-dialog>

    </AppLayout>
</template>