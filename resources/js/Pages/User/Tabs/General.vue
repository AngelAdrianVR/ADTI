<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElNotification } from 'element-plus';

const props = defineProps({
    user: Object,
    vacations: Array,
});

const form = useForm({
    vacations: props.user.org_props?.vacations || 0,
});

// Variable local para el switch (convertimos a Boolean para evitar disparos falsos)
const localHomeOffice = ref(Boolean(props.user.home_office));

// Sincronizar si la prop cambia externamente
watch(() => props.user.home_office, (newVal) => {
    localHomeOffice.value = Boolean(newVal);
});

const editVacations = ref(false);
const defaultProps = {
    children: 'children',
    label: 'label',
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
};

const formatCurrency = (value) => {
    if (!value) return '$0.00';
    return '$' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
};

const updateVacations = () => {
    form.put(route('users.update-vacations', props.user.id), {
        onSuccess: () => {
            ElNotification({
                title: 'Correcto',
                message: 'Vacaciones actualizadas correctamente',
                type: 'success',
            });
            editVacations.value = false;
        },
        onError: () => {
            ElNotification({
                title: 'Error',
                message: 'No se pudo actualizar',
                type: 'error',
            });
        }
    });
};

const toggleHomeOffice = () => {
    // Guardamos el estado actual por si hay error
    const previousState = !localHomeOffice.value;

    router.put(route('users.toggle-home-office', props.user.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            ElNotification.success('Estatus de Home Office actualizado');
        },
        onError: () => {
            // Revertir cambio visual si falla
            localHomeOffice.value = previousState;
            ElNotification.error('No se pudo actualizar el estatus');
        }
    });
};
</script>

<template>
    <div class="space-y-8 px-2">
        
        <!-- Sección 1: Datos Laborales -->
        <section>
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-2">
                <i class="fa-solid fa-briefcase text-primary mr-2"></i> Información Laboral
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 text-sm">
                <!-- Estatus -->
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Estatus</span>
                    <div>
                        <span v-if="user.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Activo
                        </span>
                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Inactivo
                        </span>
                    </div>
                </div>

                <!-- Fecha Ingreso -->
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Fecha de Ingreso</span>
                    <span class="text-gray-900 font-medium">{{ user.org_props?.entry_date ? formatDate(user.org_props.entry_date) : '-' }}</span>
                </div>

                <!-- Departamento & Puesto -->
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Departamento</span>
                    <span class="text-gray-900">{{ user.org_props?.department || '-' }}</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Puesto</span>
                    <span class="text-gray-900 font-semibold">{{ user.org_props?.position || '-' }}</span>
                </div>

                <!-- Correo Empresa -->
                <div class="flex flex-col md:col-span-2">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Email Empresarial</span>
                    <span class="text-gray-900 font-mono text-xs md:text-sm">{{ user.org_props?.email || '-' }}</span>
                </div>

                <!-- Home Office Switch -->
                <div class="flex flex-col justify-center">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-2">Acceso Remoto</span>
                    <div class="flex items-center">
                        <el-switch
                            v-model="localHomeOffice"
                            @change="toggleHomeOffice"
                            active-text="Habilitado"
                            inactive-text="Deshabilitado"
                            style="--el-switch-on-color: #4f46e5;"
                        />
                    </div>
                </div>

                <!-- Gestión de Vacaciones -->
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-2">Vacaciones</span>
                    <el-dropdown trigger="click" placement="bottom-start">
                        <button class="flex items-center justify-between w-full md:w-auto px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg transition-colors border border-indigo-200">
                            <span class="font-bold mr-2">{{ Math.floor(user.org_props?.vacations || 0) }} días disponibles</span>
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </button>
                        
                        <template #dropdown>
                            <el-dropdown-menu class="!p-4 min-w-[300px]">
                                <h4 class="font-bold text-gray-700 text-sm mb-3">Gestión de Vacaciones</h4>
                                
                                <div class="mb-4">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs text-gray-500">Saldo actual</span>
                                        <button v-if="!editVacations" @click="editVacations = true" class="text-xs text-indigo-600 hover:underline">Modificar</button>
                                    </div>
                                    
                                    <div v-if="editVacations" class="flex items-center gap-2">
                                        <el-input 
                                            v-model="form.vacations" 
                                            size="small" 
                                            type="number"
                                            class="!w-24"
                                        />
                                        <button @click="updateVacations" class="text-green-600 hover:bg-green-50 p-1 rounded"><i class="fa-solid fa-check"></i></button>
                                        <button @click="editVacations = false; form.reset()" class="text-red-500 hover:bg-red-50 p-1 rounded"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                    <p v-else class="text-lg font-bold text-gray-800">{{ Math.floor(user.org_props?.vacations || 0) }} días</p>
                                </div>

                                <div class="border-t border-gray-100 pt-3">
                                    <span class="text-xs font-bold text-gray-400 uppercase">Historial de uso</span>
                                    <div class="mt-2 max-h-40 overflow-y-auto">
                                        <el-tree :data="vacations" :props="defaultProps" empty-text="Sin registros recientes" />
                                    </div>
                                </div>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </div>
        </section>

        <!-- Sección 2: Información Financiera (Visible solo con permisos) -->
        <section v-if="$page.props.auth.user.permissions.includes('Ver sueldos')">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-2">
                <i class="fa-solid fa-money-check-dollar text-emerald-500 mr-2"></i> Información Financiera
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-xs text-gray-500 mb-1">Sueldo Neto</p>
                    <p class="text-lg font-bold text-gray-800">{{ formatCurrency(user.org_props?.net_salary) }}</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-xs text-gray-500 mb-1">Comp. Catorcenal</p>
                    <p class="text-lg font-bold text-gray-800">{{ formatCurrency(user.org_props?.biweekly_complement) }}</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-xs text-gray-500 mb-1">Comp. Mensual</p>
                    <p class="text-lg font-bold text-gray-800">{{ formatCurrency(user.org_props?.month_complement) }}</p>
                </div>
            </div>
        </section>

        <!-- Sección 3: Datos Personales -->
        <section>
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-2">
                <i class="fa-solid fa-user text-primary mr-2"></i> Datos Personales
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 text-sm">
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Fecha Nacimiento</span>
                    <span class="text-gray-900">{{ user.birthdate ? formatDate(user.birthdate) : "-" }}</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Estado Civil</span>
                    <span class="text-gray-900">{{ user.civil_state || '-' }}</span>
                </div>
                
                <div class="flex flex-col md:col-span-2">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Domicilio</span>
                    <span class="text-gray-900">{{ user.address || '-' }}</span>
                </div>

                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">RFC</span>
                    <span class="text-gray-900 font-mono">{{ user.rfc || '-' }}</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">CURP</span>
                    <span class="text-gray-900 font-mono">{{ user.curp || '-' }}</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">NSS</span>
                    <span class="text-gray-900 font-mono">{{ user.ssn || '-' }}</span>
                </div>
            </div>
        </section>

    </div>
</template>