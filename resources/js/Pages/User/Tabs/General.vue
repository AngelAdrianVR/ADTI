<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElNotification } from 'element-plus';

const props = defineProps({
    user: Object,
    vacations: Array,
});


// Variable local para el switch (convertimos a Boolean para evitar disparos falsos)
const localHomeOffice = ref(Boolean(props.user.home_office));

// Sincronizar si la prop cambia externamente
watch(() => props.user.home_office, (newVal) => {
    localHomeOffice.value = Boolean(newVal);
});


const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
};

const formatCurrency = (value) => {
    if (!value) return '$0.00';
    return '$' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
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

// Helper para diseño de turnos (Consistencia con la tabla)
const getShiftBadgeStyle = (shift) => {
    if (!shift) {
        return {
            class: 'bg-gray-50 text-gray-500 border border-gray-200',
            icon: 'fa-solid fa-circle-question'
        };
    }
    if (shift.startsWith('Turno 1')) {
        return {
            class: 'bg-orange-50 text-orange-600 border border-orange-200',
            icon: 'fa-solid fa-sun'
        };
    }
    if (shift.startsWith('Turno 2')) {
        return {
            class: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
            icon: 'fa-solid fa-moon'
        };
    }
    if (shift.startsWith('Turno 3')) {
        return {
            class: 'bg-blue-50 text-blue-600 border border-blue-200',
            icon: 'fa-solid fa-clock'
        };
    }
    // Fallback para valores antiguos como "Diurno" o "Nocturno"
    if (shift === 'Diurno') {
        return {
            class: 'bg-orange-50 text-orange-600 border border-orange-200',
            icon: 'fa-solid fa-sun'
        };
    }
    return {
        class: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        icon: 'fa-solid fa-moon'
    };
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

                <!-- Departamento -->
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Departamento</span>
                    <span class="text-gray-900">{{ user.org_props?.department || '-' }}</span>
                </div>
                
                <!-- Puesto -->
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Puesto</span>
                    <span class="text-gray-900 font-semibold">{{ user.org_props?.position || '-' }}</span>
                </div>

                <!-- Turno de Trabajo -->
                <div class="flex flex-col">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Turno</span>
                    <div>
                        <span 
                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold tracking-wide"
                            :class="getShiftBadgeStyle(user.org_props?.work_shift).class"
                        >
                            <i :class="getShiftBadgeStyle(user.org_props?.work_shift).icon"></i>
                            {{ user.org_props?.work_shift || 'Diurno' }}
                        </span>
                    </div>
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
                            style="--el-switch-on-color: #1676A2;"
                        />
                    </div>
                </div>

                <!-- Correo Empresa -->
                <div class="flex flex-col md:col-span-2">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Email Empresarial</span>
                    <span class="text-gray-900 font-mono text-xs md:text-sm">{{ user.org_props?.email || '-' }}</span>
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