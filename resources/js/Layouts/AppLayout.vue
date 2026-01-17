<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import SideNav from '@/Components/MyComponents/SideNav.vue';
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
    try {
        const response = await axios.post(route('projects.stop', activeEntry.value.project.id));
        
        if (response.status === 200) {
            clearInterval(timerInterval);
            isHidden.value = true;

            ElNotification.success({
                title: "Tarea detenida",
                message: `Has dejado de trabajar en: ${activeEntry.value.project.name}`,
            });
            router.reload({ only: ['auth'] });
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
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-gray-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                        </svg>
                        <span>Página principal</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-gray-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                        </svg>
                        <span>Panel de inicio</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="$page.props.auth.user.permissions.includes('Ver productos')"
                    :href="route('products.index')" :active="route().current('products.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <svg width="20" height="20" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-gray-500">
                            <path d="M2.25 8.38583C2.25 10.0434 2.90848 11.6331 4.08058 12.8053C5.25268 13.9774 6.8424 14.6358 8.5 14.6358C10.1576 14.6358 11.7473 13.9774 12.9194 12.8053C14.0915 11.6331 14.75 10.0434 14.75 8.38583M2.25 8.38583C2.25 6.72823 2.90848 5.13852 4.08058 3.96642C5.25268 2.79431 6.8424 2.13583 8.5 2.13583C10.1576 2.13583 11.7473 2.79431 12.9194 3.96642C14.0915 5.13852 14.75 6.72823 14.75 8.38583M2.25 8.38583H1M14.75 8.38583H16M14.75 8.38583H8.5L4.75 1.89M1.4525 10.95L2.6275 10.5225M14.3733 6.2475L15.5483 5.82M2.755 13.2067L3.71333 12.4033M13.2883 4.36833L14.2458 3.565M4.75083 14.8817L5.37583 13.7983L8.50167 8.38583M11.6258 2.97333L12.2508 1.89M7.19833 15.7717L7.415 14.5408M9.58583 2.23083L9.8025 1M9.8025 15.7717L9.58583 14.5408M7.415 2.23083L7.19833 1M12.25 14.8808L11.625 13.7983M14.245 13.2067L13.2875 12.4033M3.71333 4.3675L2.755 3.56417M15.5483 10.9508L14.3733 10.5233M2.62833 6.24833L1.45333 5.82" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Productos</span>
                    </div>
                </ResponsiveNavLink>
                
                <ResponsiveNavLink
                    :href="route('projects.index')" :active="route().current('projects.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" class="text-gray-500" stroke-width="1.9" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>
                        <span>Proyectos</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="$page.props.auth.user.permissions.includes('Ver usuarios')"
                    :href="route('users.index')" :active="route().current('users.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <svg width="20" height="20" viewBox="0 0 18 17" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-gray-500">
                            <path d="M11.6258 14.44C12.3366 14.6464 13.0732 14.7508 13.8133 14.75C15.0037 14.7517 16.1785 14.4803 17.2475 13.9567C17.2791 13.2098 17.0664 12.4729 16.6415 11.8578C16.2166 11.2427 15.6028 10.7828 14.8931 10.5479C14.1834 10.313 13.4164 10.3159 12.7085 10.5562C12.0005 10.7964 11.3902 11.2609 10.97 11.8792M11.6258 14.44V14.4375C11.6258 13.51 11.3875 12.6375 10.97 11.8792M11.6258 14.44V14.5283C10.0221 15.4942 8.18468 16.0032 6.3125 16C4.37 16 2.5525 15.4625 1.00083 14.5283L1 14.4375C0.999361 13.2579 1.39134 12.1116 2.11415 11.1794C2.83696 10.2472 3.84948 9.58203 4.99207 9.28883C6.13467 8.99564 7.34235 9.09107 8.42472 9.56008C9.50709 10.0291 10.4026 10.845 10.97 11.8792M9.12583 3.8125C9.12583 4.55842 8.82952 5.27379 8.30207 5.80124C7.77463 6.32868 7.05926 6.625 6.31333 6.625C5.56741 6.625 4.85204 6.32868 4.3246 5.80124C3.79715 5.27379 3.50083 4.55842 3.50083 3.8125C3.50083 3.06658 3.79715 2.35121 4.3246 1.82376C4.85204 1.29632 5.56741 1 6.31333 1C7.05926 1 7.77463 1.29632 8.30207 1.82376C8.82952 2.35121 9.12583 3.06658 9.12583 3.8125ZM16.0008 5.6875C16.0008 6.26766 15.7704 6.82406 15.3601 7.2343C14.9499 7.64453 14.3935 7.875 13.8133 7.875C13.2332 7.875 12.6768 7.64453 12.2665 7.2343C11.8563 6.82406 11.6258 6.26766 11.6258 5.6875C11.6258 5.10734 11.8563 4.55094 12.2665 4.1407C12.6768 3.73047 13.2332 3.5 13.8133 3.5C14.3935 3.5 14.9499 3.73047 15.3601 4.1407C15.7704 4.55094 16.0008 5.10734 16.0008 5.6875Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Usuarios</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="$page.props.auth.user.permissions.includes('Ver incidencias')"
                    :href="route('payrolls.index')" :active="route().current('payrolls.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-gray-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125-1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" />
                        </svg>
                        <span>Incidencias</span>
                    </div>
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="$page.props.auth.user.permissions.includes('Ver dias festivos')"
                    :href="route('holidays.index')" :active="route().current('holidays.*')" class="rounded-lg">
                    <div class="flex items-center space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-gray-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
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
                            <svg width="20" height="20" viewBox="0 0 16 15" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-gray-500">
                                <path d="M6.625 2.25H14.75M6.625 2.25C6.625 2.58152 6.4933 2.89946 6.25888 3.13388C6.02446 3.3683 5.70652 3.5 5.375 3.5C5.04348 3.5 4.72554 3.3683 4.49112 3.13388C4.2567 2.89946 4.125 2.58152 4.125 2.25M6.625 2.25C6.625 1.91848 6.4933 1.60054 6.25888 1.36612C6.02446 1.1317 5.70652 1 5.375 1C5.04348 1 4.72554 1.1317 4.49112 1.36612C4.2567 1.60054 4.125 1.91848 4.125 2.25M4.125 2.25H1M6.625 12.25H14.75M6.625 12.25C6.625 12.5815 6.4933 12.8995 6.25888 13.1339C6.02446 13.3683 5.70652 13.5 5.375 13.5C5.04348 13.5 4.72554 13.3683 4.49112 13.1339C4.2567 12.8995 4.125 12.5815 4.125 12.25M6.625 12.25C6.625 11.9185 6.4933 11.6005 6.25888 11.3661C6.02446 11.1317 5.70652 11 5.375 11C5.04348 11 4.72554 11.1317 4.49112 11.3661C4.2567 11.6005 4.125 11.9185 4.125 12.25M4.125 12.25H1M11.625 7.25H14.75M11.625 7.25C11.625 7.58152 11.4933 7.89946 11.2589 8.13388C11.0245 8.3683 10.7065 8.5 10.375 8.5C10.0435 8.5 9.72554 8.3683 9.49112 8.13388C9.2567 7.89946 9.125 7.58152 9.125 7.25M11.625 7.25C11.625 6.91848 11.4933 6.60054 11.2589 6.36612C11.0245 6.1317 10.7065 6 10.375 6C10.0435 6 9.72554 6.1317 9.49112 6.36612C9.2567 6.60054 9.125 6.91848 9.125 7.25M9.125 7.25H1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
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
                                        <span>Día terminado</span>
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
                                                            d="M8.50033 3.50033C8.50033 4.16346 8.23691 4.79943 7.768 5.26834C7.2991 5.73724 6.66313 6.00067 6 6.00067C5.33687 6.00067 4.7009 5.73724 4.232 5.26834C3.76309 4.79943 3.49967 4.16346 3.49967 3.50033C3.49967 2.8372 3.76309 2.20123 4.232 1.73233C4.7009 1.26343 5.33687 1 6 1C6.66313 1 7.2991 1.26343 7.768 1.73233C8.23691 2.20123 8.50033 2.8372 8.50033 3.50033ZM1 12.9136C1.02143 11.6016 1.55763 10.3507 2.49298 9.43049C3.42833 8.51029 4.68788 7.99458 6 7.99458C7.31212 7.99458 8.57166 8.51029 9.50702 9.43049C10.4424 10.3507 10.9786 11.6016 11 12.9136C9.43138 13.6329 7.72566 14.0041 6 14.0017C4.21576 14.0017 2.5222 13.6123 1 12.9136Z"
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