<script setup>
import { computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    Plus, 
    Delete, 
    Back, 
    CircleCheck,
    Connection
} from '@element-plus/icons-vue';

const props = defineProps({
    departments: Array
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

// Computed para sumar horas en tiempo real
const totalBudgetedHours = computed(() => {
    return form.tasks.reduce((sum, task) => sum + Number(task.hours || 0), 0);
});

// Métodos para gestión de tareas
const addTask = () => {
    form.tasks.push({ department_id: '', description: '', hours: 0 });
};

const removeTask = (index) => {
    if (form.tasks.length > 1) {
        form.tasks.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('projects.store'));
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
                        <p class="text-sm text-gray-500">Define los detalles generales y desglosa el presupuesto por tareas.</p>
                    </div>
                    <Link :href="route('projects.index')" class="text-[#1676A2] hover:underline text-sm font-medium flex items-center">
                        <el-icon class="mr-1"><Back /></el-icon> Volver al listado
                    </Link>
                </div>

                <el-form 
                    :model="form" 
                    @submit.prevent="submit" 
                    label-position="top"
                    class="space-y-6"
                >
                    
                    <!-- Sección 1: Detalles Generales -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-700 mb-4 border-b border-gray-100 pb-2 flex items-center">
                            <span class="w-1 h-6 bg-[#1676A2] rounded mr-2"></span>
                            Información General
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <el-form-item label="Nombre del Proyecto" :error="form.errors.name" required>
                                <el-input v-model="form.name" placeholder="Ej. SCADA" />
                            </el-form-item>

                            <!-- Cliente -->
                            <el-form-item label="Cliente" :error="form.errors.client" required>
                                <el-input v-model="form.client" placeholder="Ej. Grupo Modelo" />
                            </el-form-item>

                            <!-- Fechas -->
                            <el-form-item label="Fecha de Inicio" :error="form.errors.start_date" required>
                                <el-date-picker
                                    v-model="form.start_date"
                                    type="date"
                                    placeholder="Selecciona una fecha"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                    class="!w-full"
                                />
                            </el-form-item>

                            <el-form-item label="Fecha Estimada Fin" :error="form.errors.estimated_end_date">
                                <el-date-picker
                                    v-model="form.estimated_end_date"
                                    type="date"
                                    placeholder="Selecciona una fecha"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                    class="!w-full"
                                />
                            </el-form-item>

                            <!-- Descripción -->
                            <div class="md:col-span-2">
                                <el-form-item label="Descripción / Notas" :error="form.errors.description">
                                    <el-input
                                        v-model="form.description"
                                        type="textarea"
                                        :rows="3"
                                        placeholder="Detalles adicionales del proyecto..."
                                    />
                                </el-form-item>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Desglose de Tareas (Presupuesto) -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2">
                            <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                                <el-icon class="mr-2 text-[#1676A2]"><Connection /></el-icon>
                                Planificación de Tareas
                            </h2>
                            <div class="text-right bg-blue-50 px-3 py-1 rounded-lg border border-blue-100">
                                <span class="text-xs text-gray-500 uppercase font-bold block">Total Presupuestado</span>
                                <p class="text-lg font-bold text-[#1676A2]">{{ totalBudgetedHours.toFixed(1) }} hrs</p>
                            </div>
                        </div>

                        <!-- Headers Tabla (Solo Desktop) -->
                        <div class="hidden md:grid grid-cols-12 gap-4 text-xs font-bold text-gray-500 uppercase px-2 mb-2">
                            <div class="col-span-3">Departamento</div>
                            <div class="col-span-6">Tarea / Descripción</div>
                            <div class="col-span-2">Horas Est.</div>
                            <div class="col-span-1"></div>
                        </div>

                        <div class="space-y-3">
                            <!-- Filas de Tareas -->
                            <div 
                                v-for="(task, index) in form.tasks" 
                                :key="index" 
                                class="relative bg-gray-50 rounded-lg p-3 md:p-2 border border-gray-200 transition-all hover:border-[#1676A2]/30"
                            >
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start md:items-center">
                                    
                                    <!-- Departamento -->
                                    <div class="col-span-3">
                                        <el-form-item 
                                            :error="form.errors[`tasks.${index}.department_id`]" 
                                            class="!mb-0"
                                        >
                                            <el-select 
                                                v-model="task.department_id" 
                                                placeholder="Departamento" 
                                                class="!w-full"
                                                size="default"
                                            >
                                                <el-option 
                                                    v-for="dept in departments" 
                                                    :key="dept.id" 
                                                    :label="dept.name" 
                                                    :value="dept.id" 
                                                />
                                            </el-select>
                                        </el-form-item>
                                    </div>

                                    <!-- Descripción -->
                                    <div class="col-span-6">
                                        <el-form-item 
                                            :error="form.errors[`tasks.${index}.description`]"
                                            class="!mb-0"
                                        >
                                            <el-input 
                                                v-model="task.description" 
                                                placeholder="Ej. Instalación eléctrica" 
                                                size="default"
                                            />
                                        </el-form-item>
                                    </div>

                                    <!-- Horas -->
                                    <div class="col-span-2">
                                        <el-form-item 
                                            :error="form.errors[`tasks.${index}.hours`]"
                                            class="!mb-0"
                                        >
                                            <el-input-number 
                                                v-model="task.hours" 
                                                :min="0" 
                                                :step="0.5" 
                                                controls-position="right"
                                                class="!w-full"
                                                size="default"
                                            />
                                        </el-form-item>
                                    </div>

                                    <!-- Eliminar -->
                                    <div class="col-span-1 text-center flex justify-end">
                                        <el-button 
                                            type="danger" 
                                            link 
                                            :icon="Delete" 
                                            @click="removeTask(index)" 
                                            :disabled="form.tasks.length === 1"
                                            title="Eliminar fila"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botón Agregar Tarea -->
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
                        <el-button 
                            type="primary" 
                            @click="submit" 
                            :loading="form.processing"
                            color="#1676A2"
                        >
                            <el-icon class="mr-2"><CircleCheck /></el-icon> Crear Proyecto
                        </el-button>
                    </div>

                </el-form>
            </div>
        </main>
    </AppLayout>
</template>