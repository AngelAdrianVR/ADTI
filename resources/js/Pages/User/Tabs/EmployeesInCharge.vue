<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    employees: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- Mensaje Informativo -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3 animate-fade-in-down">
            <i class="fa-solid fa-circle-info text-blue-600 mt-0.5 text-lg"></i>
            <div>
                <h3 class="font-bold text-blue-800 text-sm">Privilegios de Supervisión</h3>
                <p class="text-sm text-blue-700 mt-1 leading-relaxed">
                    Este usuario tiene acceso administrativo sobre el personal listado a continuación. 
                    Esto incluye la capacidad de <b>ver su historial</b>, <b>gestionar incidencias</b> (faltas, vacaciones, permisos) y <b>corregir registros de asistencia</b>.
                </p>
            </div>
        </div>

        <!-- Lista de Empleados -->
        <div>
            <h3 class="text-gray-700 font-bold mb-4 flex items-center gap-2">
                Personal Asignado <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">{{ employees.length }}</span>
            </h3>

            <div v-if="employees.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link 
                    v-for="employee in employees" 
                    :key="employee.id"
                    :href="route('users.show', employee.id)"
                    class="bg-white border border-gray-200 rounded-xl p-3 flex items-center gap-4 hover:shadow-md hover:border-indigo-300 transition-all group"
                >
                    <!-- Avatar -->
                    <div class="relative">
                        <img 
                            :src="employee.profile_photo_url" 
                            :alt="employee.name" 
                            class="w-12 h-12 rounded-full object-cover border border-gray-100 group-hover:scale-105 transition-transform"
                        >
                    </div>
                    
                    <!-- Info -->
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-gray-800 text-sm truncate group-hover:text-indigo-600 transition-colors">
                            {{ employee.name }}
                        </p>
                        <p class="text-xs text-gray-500 truncate">
                            {{ employee.org_props?.department || 'Sin departamento' }}
                        </p>
                        <p class="text-[10px] text-gray-400 mt-0.5 truncate uppercase tracking-wide">
                            {{ employee.org_props?.position || 'Sin puesto' }}
                        </p>
                    </div>

                    <!-- Icono flecha -->
                    <i class="fa-solid fa-chevron-right text-gray-300 text-xs group-hover:text-indigo-400"></i>
                </Link>
            </div>

            <!-- Estado Vacío -->
            <div v-else class="flex flex-col items-center justify-center py-12 bg-white border border-dashed border-gray-200 rounded-xl text-gray-400">
                <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                    <i class="fa-solid fa-users-slash text-xl"></i>
                </div>
                <p class="text-sm">No tiene personal asignado a su cargo actualmente.</p>
                <p class="text-xs mt-1">Edita el usuario para agregar colaboradores.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-fade-in-down {
    animation: fadeInDown 0.5s ease-out;
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>