<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import SideNav from '@/Components/MyComponents/SideNav.vue';

// --- NUEVOS ÍCONOS ---
import HomeIcon from '@/Components/MyComponents/Icons/HomeIcon.vue';
import DashboardIcon from '@/Components/MyComponents/Icons/DashboardIcon.vue';
import ProductIcon from '@/Components/MyComponents/Icons/ProductIcon.vue';
import ProjectIcon from '@/Components/MyComponents/Icons/ProjectIcon.vue';
import UserIcon from '@/Components/MyComponents/Icons/UserIcon.vue';
import IncidenceIcon from '@/Components/MyComponents/Icons/IncidenceIcon.vue';
import VacationRequestIcon from '@/Components/MyComponents/Icons/VacationRequestIcon.vue';
import HolidayIcon from '@/Components/MyComponents/Icons/HolidayIcon.vue';
import SettingIcon from '@/Components/MyComponents/Icons/SettingIcon.vue';

import axios from 'axios';
import { ElNotification } from "element-plus";

defineProps({
    title: String,
});

const page = usePage();
const showingNavigationDropdown = ref(false);
const showingSettingsSubmenu = ref(route().current('settings.*')); // Auto-expandir si estamos en configuraciones
const nextAttendance = ref("");
const isPaused = ref(false);
const isHidden = ref(false);

// --- Lógica del Timer de Proyecto ---
const activeEntry = computed(() => page.props.auth?.user?.active_entry);
const timerDisplay = ref('00:00:00');
let timerInterval = null;

const startLocalTimer = () => {
    if (!activeEntry.value) return;
    
    // Función para calcular diferencia
    const updateTimer = () => {
        // Asegurarse de que start_time existe antes de usarlo
        if (!activeEntry.value?.start_time) return;

        const start = new Date(activeEntry.value.start_time).getTime();
        const now = new Date().getTime();
        const diff = now - start;

        if (diff < 0) {
            timerDisplay.value = '00:00:00';
            return;
        }

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        timerDisplay.value = 
            `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    };

    updateTimer(); // Ejecutar inmediatamente
    timerInterval = setInterval(updateTimer, 1000); // Actualizar cada segundo
};

const stopWork = async () => {
    if (!activeEntry.value?.project?.id) return;

    try {
        const response = await axios.post(route('projects.stop', activeEntry.value.project.id));
        
        if (response.status === 200) {
            clearInterval(timerInterval);
            isHidden.value = true;

            ElNotification.success({
                title: "Tarea detenida",
                // Usar encadenamiento opcional para evitar error si project ya no existe en el objeto
                message: `Has dejado de trabajar en: ${activeEntry.value?.project?.name || 'Proyecto'}`,
            });
            
            // Recargar toda la página
            window.location.reload()
            // Recargar solo auth para actualizar el estado global sin recargar toda la página
            // router.reload({ only: ['auth'] });
        }
    } catch (error) {
        console.error(error);
        ElNotification.error({
            title: "Error",
            message: "No se pudo detener la tarea. Intenta de nuevo.",
        });
    }
};

watch(activeEntry, (newVal) => {
    if (newVal) {
        isHidden.value = false;
        startLocalTimer();
    } else {
        clearInterval(timerInterval);
        timerDisplay.value = '00:00:00';
    }
}, { immediate: true });

onUnmounted(() => {
    clearInterval(timerInterval);
});
// --------------------------------

const getAttendanceTextButton = async () => {
    try {
        const response = await axios.get(route("users.get-next-attendance"));
        nextAttendance.value = response.data.next;
    } catch (error) {
        console.error(error);
    }
};

const getPauseStatus = async () => {
    try {
        const response = await axios.get(route("users.get-pause-status"));
        isPaused.value = response.data.status;
    } catch (error) {
        console.error(error);
    }
};

const setPause = async () => {
    try {
        const response = await axios.get(route("users.set-pause"));
        if (response.status === 200) {
            isPaused.value = response.data.status;
            ElNotification.success({
                title: "Éxito",
                message: response.data.message,
            });
        }
    } catch (error) {
        console.error(error);
        if (error?.response.status === 422) {
            ElNotification.error({
                message: error.response.data.message,
                type: "error",
            });
        } else {
            ElNotification.error({
                message: 'Hubo algún problema en el servior, repórtalo con soporte',
                type: "error",
            });
        }
    }
};

const setAttendance = async () => {
    try {
        const response = await axios.post(route("users.set-attendance"));
        if (response.status === 200) {
            nextAttendance.value = response.data.next;
            isPaused.value = null;
            ElNotification.success({
                title: "Registro correcto",
                message: "",
            });
        }
    } catch (error) {
        console.error(error);
        if (error?.response.status === 422) {
            ElNotification.error({
                message: error.response.data.message,
                type: "error",
            });
        } else {
            ElNotification.error({
                message: 'Hubo algún problema en el servior, repórtalo con soporte',
                type: "error",
            });
        }
    }
};

const logout = () => {
    router.post(route('logout'));
};

onMounted(() => {
    getAttendanceTextButton();
    getPauseStatus();
});
</script>

<template>
    <div>
        <Head :title="title" />
        <Banner />

        <!-- Drawer Mobile Overlay -->
        <div v-if="showingNavigationDropdown" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm transition-opacity sm:hidden" @click="showingNavigationDropdown = false"></div>

        <!-- Drawer Mobile Menu -->
        <div class="fixed inset-y-0 right-0 z-50 w-72 bg-white shadow-2xl transform transition-transform duration-300 ease-in-out sm:hidden flex flex-col"
             :class="showingNavigationDropdown ? 'translate-x-0' : 'translate-x-full'">
            
            <!-- Drawer Header: User Info -->
            <div class="bg-gradient-to-r from-gray-700 to-gray-900 p-6 text-white relative">
                <button @click="showingNavigationDropdown = false" class="absolute top-4 right-4 text-gray-300 hover:text-white">
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
                            :title="isPaused ? '¿Reanudar?' : '¿Pausar tiempo?'" @confirm="setPause" width="200">
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
                                :title="'¿' + nextAttendance + '?'" @confirm="setAttendance" width="200">
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

                <ResponsiveNavLink v-if="$page.props.auth.user?.permissions?.includes('Ver incidencias')"
                    :href="route('payrolls.index')" :active="route().current('payrolls.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <IncidenceIcon class="size-5 text-gray-500" />
                        <span>Incidencias</span>
                    </div>
                </ResponsiveNavLink>

                <!-- NUEVO MÓDULO: SOLICITUDES DE VACACIONES -->
                <ResponsiveNavLink v-if="$page.props.auth.user?.permissions?.includes('Ver solicitudes de vacaciones') || ($page.props.auth.user.employees_in_charge && $page.props.auth.user.employees_in_charge.length > 0)"
                    :href="route('vacation-requests.index')" :active="route().current('vacation-requests.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <VacationRequestIcon class="size-5 text-gray-500" />
                        <span>Solicitudes</span>
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

                <!-- BOTÓN AGREGADO: VISTA MÓVIL -->
                <ResponsiveNavLink :href="route('my-payrolls')" :active="route().current('my-payrolls')" class="rounded-lg mb-2">
                    <span class="text-gray-600">Mis Nóminas</span>
                </ResponsiveNavLink>

                <form method="POST" @submit.prevent="logout">
                    <button class="w-full flex items-center justify-center space-x-2 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors text-sm font-medium">
                        <i class="fa-solid fa-arrow-right-from-bracket rotate-180"></i>
                        <span>Cerrar sesión</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="overflow-hidden h-screen md:flex bg-white relative">

            <!-- sidenav (Desktop) -->
            <aside class="col-span-2 w-auto hidden md:block z-30">
                <SideNav />
            </aside>

            <!-- resto de pagina -->
            <main class="w-full flex flex-col h-screen relative">
                <nav class="bg-white border-b border-grayD9 shrink-0">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-12">
                            <div class="flex">
                                <!-- Logo -->
                                <div class="md:hidden shrink-0 flex items-center">
                                    <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-11 w-auto" />
                                    </Link>
                                </div>
                            </div>
                            <div class="hidden sm:flex sm:items-center sm:ms-6">
                                <!-- registro asistencia -->
                                <section v-if="$page.props.auth.user.home_office"
                                    class="mr-4 flex items-center space-x-2">
                                    <!-- pausa -->
                                    <p v-if="isPaused" class="text-xs mt-1">Pausaste a las {{ isPaused }}</p>
                                    <el-popconfirm v-if="isPaused !== false && nextAttendance == 'Registrar salida'"
                                        confirm-button-text="Si" cancel-button-text="No" icon-color="#373737"
                                        :title="isPaused ? '¿Reanudar?' : '¿Pausar tiempo?'" @confirm="setPause">
                                        <template #reference>
                                            <button v-if="nextAttendance == 'Registrar salida'"
                                                class="size-7 text-xs rounded-full text-primary bg-[#DBF0FA]">
                                                <i v-if="isPaused" class="fa-solid fa-play"></i>
                                                <i v-else class="fa-solid fa-pause"></i>
                                            </button>
                                        </template>
                                    </el-popconfirm>
                                    <el-popconfirm v-if="nextAttendance != 'Día terminado'" confirm-button-text="Si"
                                        cancel-button-text="No" icon-color="#373737" :title="'¿Continuar?'"
                                        @confirm="setAttendance()">
                                        <template #reference>
                                            <button v-if="nextAttendance == 'Registrar entrada'"
                                                class="flex items-center space-x-2 text-primary bg-[#DBF0FA] text-sm rounded-full px-3 py-1 lg:mr-4">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor" class="size-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                                </svg>
                                                <span>Registrar entrada</span>
                                            </button>
                                            <button v-else-if="nextAttendance == 'Registrar salida'"
                                                class="flex items-center space-x-2 text-primary bg-[#DBF0FA] text-sm rounded-full px-3 py-1 lg:mr-4">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor" class="size-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                                                </svg>
                                                <span>Registrar salida</span>
                                            </button>
                                        </template>
                                    </el-popconfirm>
                                    <p v-else
                                        class="flex items-center space-x-2 text-[#179E15] bg-[#C8FEC7] text-sm rounded-full px-3 py-1 lg:mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <span>Días terminado</span>
                                    </p>
                                </section>
                                <!-- Settings Dropdown -->
                                <div class="ms-3 relative">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button v-if="$page.props.jetstream.managesProfilePhotos"
                                                class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                                <img class="h-8 w-8 rounded-full object-cover"
                                                    :src="$page.props.auth.user.profile_photo_url"
                                                    :alt="$page.props.auth.user.name">
                                            </button>

                                            <span v-else class="inline-flex items-center rounded-md">
                                                <button type="button"
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                    <svg width="10" height="13" viewBox="0 0 12 15" fill="none"
                                                        class="mr-2" xmlns="http://www.w3.org/2000/svg">
                                                        <path
                                                            d="M8.50033 3.50033C8.50033 4.16346 8.23691 4.79943 7.768 5.26834C7.2991 5.73724 6.66313 6.00067 6 6.00067C5.33687 6.00067 4.7009 5.73724 4.232 5.26834C3.76309 4.79943 3.49967 4.16346 3.49967 2.8372 3.76309 2.20123 4.232 1.73233C4.7009 1.26343 5.33687 1 6 1C6.66313 1 7.2991 1.26343 7.768 1.73233C8.23691 2.20123 8.50033 2.8372 8.50033 3.50033ZM1 12.9136C1.02143 11.6016 1.55763 10.3507 2.49298 9.43049C3.42833 8.51029 4.68788 7.99458 6 7.99458C7.31212 7.99458 8.57166 8.51029 9.50702 9.43049C10.4424 10.3507 10.9786 11.6016 11 12.9136C9.43138 13.6329 7.72566 14.0041 6 14.0017C4.21576 14.0017 2.5222 13.6123 1 12.9136Z"
                                                            stroke="#6D6E72" stroke-width="1.5" stroke-linecap="round"
                                                            stroke-linejoin="round" />
                                                    </svg>
                                                    {{ $page.props.auth.user.name }}
                                                    <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                    </svg>
                                                </button>
                                            </span>
                                        </template>

                                        <template #content>
                                            <!-- Account Management -->
                                            <div class="block px-4 py-2 text-xs text-gray-400">
                                                Cuenta
                                            </div>

                                            <DropdownLink :href="route('profile.show')">
                                                Perfil
                                            </DropdownLink>

                                            <!-- BOTÓN AGREGADO: VISTA ESCRITORIO -->
                                            <DropdownLink :href="route('my-payrolls')">
                                                Mis Nóminas
                                            </DropdownLink>

                                            <div class="border-t border-gray-200" />

                                            <!-- Authentication -->
                                            <form @submit.prevent="logout">
                                                <DropdownLink as="button">
                                                    Cerrar sesión
                                                </DropdownLink>
                                            </form>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                            
                            <!-- Hamburger (Updated for Drawer) -->
                            <div class="-me-2 flex items-center sm:hidden">
                                <button
                                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                                    @click="showingNavigationDropdown = !showingNavigationDropdown">
                                    <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="overflow-y-auto flex-1 bg-white relative">
                    <slot />
                    
                    <!-- Timer Flotante -->
                    <div v-if="activeEntry && !isHidden" 
                         class="fixed bottom-0 right-0 left-0 sm:left-auto sm:bottom-6 sm:right-6 z-50 bg-gray-800 text-white shadow-lg sm:rounded-lg overflow-hidden flex items-center justify-between transition-all duration-300 transform translate-y-0">
                        
                        <!-- Barra de progreso indeterminada superior -->
                        <div class="absolute top-0 left-0 w-full h-0.5 bg-gray-700 overflow-hidden">
                            <div class="h-full bg-primary animate-progress"></div>
                        </div>

                        <div class="flex items-center px-4 py-3 gap-4">
                            <!-- Icono y Texto -->
                            <div class="flex items-center gap-3">
                                <div class="relative flex h-3 w-3">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] uppercase text-gray-400 font-bold tracking-wider leading-none mb-1">En curso</span>
                                    <span class="text-sm font-semibold truncate max-w-[150px] sm:max-w-[200px]" :title="activeEntry.project.name">
                                        {{ activeEntry.project.name }}
                                    </span>
                                </div>
                            </div>

                            <!-- Timer Display -->
                            <div class="font-mono text-lg font-bold text-gray-100 tabular-nums">
                                {{ timerDisplay }}
                            </div>

                            <!-- Separador Vertical -->
                            <div class="h-6 w-px bg-gray-600"></div>

                            <!-- Botón Stop -->
                            <button @click="stopWork" 
                                    class="text-red-400 hover:text-white hover:bg-red-600 p-1.5 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-red-500"
                                    title="Detener tarea">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                  <path fill-rule="evenodd" d="M4.5 7.5a3 3 0 013-3h9a3 3 0 013 3v9a3 3 0 01-3 3h-9a3 3 0 01-3-3v-9z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style>
/* Animación para la barra de progreso */
@keyframes progress {
  0% { width: 0%; margin-left: 0%; }
  50% { width: 50%; margin-left: 25%; }
  100% { width: 100%; margin-left: 100%; }
}
.animate-progress {
  animation: progress 2s infinite linear;
}
</style>