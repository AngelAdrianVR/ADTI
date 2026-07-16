<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

// --- Íconos Limpios ---
import HomeIcon from '@/Components/MyComponents/Icons/HomeIcon.vue';
import DashboardIcon from '@/Components/MyComponents/Icons/DashboardIcon.vue';
import ProductIcon from '@/Components/MyComponents/Icons/ProductIcon.vue';
import ProjectIcon from '@/Components/MyComponents/Icons/ProjectIcon.vue';
import UserIcon from '@/Components/MyComponents/Icons/UserIcon.vue';
import IncidenceIcon from '@/Components/MyComponents/Icons/IncidenceIcon.vue';
import VacationRequestIcon from '@/Components/MyComponents/Icons/VacationRequestIcon.vue';
import HolidayIcon from '@/Components/MyComponents/Icons/HolidayIcon.vue';
import SettingIcon from '@/Components/MyComponents/Icons/SettingIcon.vue';

defineProps({
    show: Boolean,
    nextAttendance: String,
    isPaused: [Boolean, String],
    pendingRequests: {
        type: Number,
        default: 0
    }
});

defineEmits(['close', 'setPause', 'setAttendance']);

const showingSettingsSubmenu = ref(route().current('settings.*'));

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div>
        <!-- Drawer Mobile Overlay -->
        <div v-if="show" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm transition-opacity sm:hidden" @click="$emit('close')"></div>

        <!-- Drawer Mobile Menu -->
        <div class="fixed inset-y-0 right-0 z-50 w-72 bg-white shadow-2xl transform transition-transform duration-300 ease-in-out sm:hidden flex flex-col"
             :class="show ? 'translate-x-0' : 'translate-x-full'">
            
            <!-- Drawer Header: User Info -->
            <div class="bg-gradient-to-r from-gray-700 to-gray-900 p-6 text-white relative">
                <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-300 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex items-center space-x-3 mt-4">
                    <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0">
                        <img class="h-12 w-12 rounded-full object-cover border-2 border-white/20"
                            :src="$page.props.auth.user.profile_photo_url"
                            :alt="$page.props.auth.user.name">
                    </div>
                    <div>
                        <div class="font-bold text-lg leading-tight">
                            {{ $page.props.auth.user.name }}
                        </div>
                        <div class="text-xs text-gray-300 mt-1 truncate max-w-[160px]">
                            {{ $page.props.auth.user.org_props?.email ?? 'Sin correo' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer Body: Links -->
            <div class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
                
                <!-- SECCIÓN ASISTENCIA MÓVIL (HOME OFFICE) -->
                <div v-if="$page.props.auth.user.home_office" class="mb-6 mx-2 p-3 bg-blue-50/50 rounded-xl border border-blue-100 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-[#1676A2] uppercase tracking-wider flex items-center gap-1">
                            <i class="fa-solid fa-clock"></i> Control Asistencia
                        </span>
                        <span v-if="isPaused" class="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold animate-pulse border border-amber-200">
                            En Pausa
                        </span>
                    </div>

                    <div class="flex gap-2">
                        <!-- Botón Pausa -->
                        <el-popconfirm v-if="nextAttendance == 'Registrar salida'"
                            confirm-button-text="Sí" cancel-button-text="No" icon-color="#373737"
                            :title="isPaused ? '¿Reanudar?' : '¿Pausar tiempo?'" @confirm="$emit('setPause')" width="200">
                            <template #reference>
                                <button class="w-12 py-2 rounded-lg border border-gray-200 bg-white shadow-sm flex items-center justify-center text-gray-600 active:scale-95 transition-transform hover:bg-gray-50">
                                    <i v-if="isPaused" class="fa-solid fa-play text-green-600 text-lg"></i>
                                    <i v-else class="fa-solid fa-pause text-amber-500 text-lg"></i>
                                </button>
                            </template>
                        </el-popconfirm>

                        <!-- Botón Principal Asistencia -->
                        <div class="flex-grow">
                            <el-popconfirm v-if="nextAttendance != 'Día terminado'" 
                                confirm-button-text="Sí" cancel-button-text="No" icon-color="#373737" 
                                :title="'¿' + nextAttendance + '?'" @confirm="$emit('setAttendance')" width="200">
                                <template #reference>
                                    <button class="w-full py-2 rounded-lg font-bold text-sm shadow-sm active:scale-95 transition-transform flex items-center justify-center gap-2 border"
                                        :class="nextAttendance == 'Registrar entrada' 
                                            ? 'bg-[#1676A2] text-white border-[#1676A2]' 
                                            : 'bg-white text-red-500 border-red-100 hover:bg-red-50'">
                                        <i class="fa-solid" :class="nextAttendance == 'Registrar entrada' ? 'fa-stopwatch' : 'fa-right-from-bracket'"></i>
                                        {{ nextAttendance }}
                                    </button>
                                </template>
                            </el-popconfirm>
                            <div v-else class="w-full py-2 bg-green-100 text-green-700 rounded-lg text-sm font-bold text-center border border-green-200 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-check-circle"></i> Día terminado
                            </div>
                        </div>
                    </div>
                </div>
                
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
                
                <ResponsiveNavLink :href="route('welcome')" :active="route().current('welcome')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <HomeIcon class="size-5 text-gray-500" />
                        <span>Página principal</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <DashboardIcon class="size-5 text-gray-500" />
                        <span>Panel de inicio</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="$page.props.auth.user?.permissions?.includes('Ver productos')"
                    :href="route('products.index')" :active="route().current('products.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <ProductIcon class="size-5 text-gray-500" />
                        <span>Productos</span>
                    </div>
                </ResponsiveNavLink>
                
                <ResponsiveNavLink
                    :href="route('projects.index')" :active="route().current('projects.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <ProjectIcon class="size-5 text-gray-500" />
                        <span>Proyectos</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="$page.props.auth.user?.permissions?.includes('Ver usuarios')"
                    :href="route('users.index')" :active="route().current('users.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <UserIcon class="size-5 text-gray-500" />
                        <span>Usuarios</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="$page.props.auth.user?.permissions?.includes('Ver incidencias') || ($page.props.auth.user.employees_in_charge && $page.props.auth.user.employees_in_charge.length > 0)"
                    :href="route('payrolls.index')" :active="route().current('payrolls.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <IncidenceIcon class="size-5 text-gray-500" />
                        <span>Incidencias</span>
                    </div>
                </ResponsiveNavLink>

                <!-- MÓDULO: SOLICITUDES DE VACACIONES -->
                <ResponsiveNavLink v-if="$page.props.auth.user?.permissions?.includes('Ver solicitudes de vacaciones') || ($page.props.auth.user.employees_in_charge && $page.props.auth.user.employees_in_charge.length > 0)"
                    :href="route('vacation-requests.index')" :active="route().current('vacation-requests.*')" class="rounded-lg">
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center space-x-3">
                            <VacationRequestIcon class="size-5 text-gray-500" />
                            <span>Solicitudes de vacaciones</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full transition-colors" 
                              :class="pendingRequests > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500'">
                            {{ pendingRequests }}
                        </span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="$page.props.auth.user?.permissions?.includes('Ver dias festivos')"
                    :href="route('holidays.index')" :active="route().current('holidays.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <HolidayIcon class="size-5 text-gray-500" />
                        <span>Días festivos</span>
                    </div>
                </ResponsiveNavLink>

                <!-- Seccion Configuraciones Desplegable -->
                <div v-if="$page.props.auth.user?.permissions?.some(permission => ['Ver categorias', 'Ver roles', 'Ver permisos', 'Ver caracteristicas', 'Ver departamentos', 'Ver puestos'].includes(permission))" class="pt-1">
                    <button 
                        @click="showingSettingsSubmenu = !showingSettingsSubmenu"
                        class="w-full flex items-center justify-between px-4 py-2 text-base font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-lg transition duration-150 ease-in-out"
                        :class="{'bg-gray-50 text-[#1676A2] font-semibold': route().current('settings.*')}"
                    >
                        <div class="flex items-center space-x-3">
                            <SettingIcon class="size-5 text-gray-500" />
                            <span>Configuraciones</span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" :class="{'rotate-180': showingSettingsSubmenu}"></i>
                    </button>

                    <!-- Submenú -->
                    <div v-show="showingSettingsSubmenu" class="mt-1 space-y-1 pl-12 pr-2">
                        <ResponsiveNavLink 
                            :href="route('settings.index')" 
                            :active="route().current('settings.index')"
                            class="rounded-lg text-sm"
                        >
                            Catálogos
                        </ResponsiveNavLink>
                        
                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user?.permissions?.some(p => ['Ver roles', 'Ver permisos'].includes(p))"
                            :href="route('settings.permissions')" 
                            :active="route().current('settings.permissions')"
                            class="rounded-lg text-sm"
                        >
                            Roles y Permisos
                        </ResponsiveNavLink>

                        <ResponsiveNavLink 
                            v-if="$page.props.auth.user?.permissions?.some(p => ['Ver caracteristicas', 'Ver departamentos', 'Ver puestos'].includes(p))"
                            :href="route('settings.general')" 
                            :active="route().current('settings.general')"
                            class="rounded-lg text-sm"
                        >
                            General
                        </ResponsiveNavLink>
                    </div>
                </div>

            </div>

            <!-- Drawer Footer: Settings & Logout -->
            <div class="border-t border-gray-100 p-4 bg-gray-50">
                <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')" class="rounded-lg mb-2">
                    <span class="text-gray-600">Perfil</span>
                </ResponsiveNavLink>

                <ResponsiveNavLink :href="route('my-payrolls')" :active="route().current('my-payrolls')" class="rounded-lg mb-2">
                    <span class="text-gray-600">Mis nóminas / vacaciones</span>
                </ResponsiveNavLink>

                <form method="POST" @submit.prevent="logout">
                    <button class="w-full flex items-center justify-center space-x-2 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors text-sm font-medium">
                        <i class="fa-solid fa-arrow-right-from-bracket rotate-180"></i>
                        <span>Cerrar sesión</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>