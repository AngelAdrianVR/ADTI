<script setup>
import { Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

defineProps({
    nextAttendance: String,
    isPaused: [Boolean, String],
    pendingRequests: {
        type: Number,
        default: 0
    }
});

defineEmits(['toggleMenu', 'setPause', 'setAttendance']);

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <nav class="bg-white border-b border-grayD9 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-12">
                <div class="flex">
                    <!-- Logo para móviles -->
                    <div class="md:hidden shrink-0 flex items-center">
                        <Link :href="route('dashboard')">
                            <ApplicationMark class="block h-11 w-auto" />
                        </Link>
                    </div>
                </div>

                <!-- LADO DERECHO: Notificaciones + Acciones -->
                <div class="flex items-center">
                    
                    <!-- Notificaciones de Solicitudes Pendientes (Popover - VISIBLE EN MÓVIL Y ESCRITORIO) -->
                    <div v-if="$page.props.auth.user?.permissions?.includes('Ver solicitudes de vacaciones') || ($page.props.auth.user.employees_in_charge && $page.props.auth.user.employees_in_charge.length > 0)" class="relative mx-1 sm:mx-2 flex items-center justify-center">
                        <Dropdown align="right" width="52">
                            <template #trigger>
                                <button 
                                    class="relative p-2 text-gray-400 hover:text-[#1676A2] hover:bg-gray-50 rounded-full transition-colors focus:outline-none flex items-center justify-center"
                                    title="Notificaciones"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                    </svg>

                                    <!-- Indicador Numérico (Badge) siempre visible -->
                                    <span class="absolute top-0 right-0 flex items-center justify-center w-4 h-4 text-[9px] font-bold text-white border-2 border-white rounded-full transition-colors"
                                          :class="pendingRequests > 0 ? 'bg-red-500' : 'bg-gray-400'">
                                        {{ pendingRequests > 9 ? '9+' : pendingRequests }}
                                    </span>
                                </button>
                            </template>
                            
                            <template #content>
                                <div class="block px-4 py-2 text-xs text-gray-400 border-b border-gray-100">
                                    Notificaciones
                                </div>
                                <DropdownLink :href="route('vacation-requests.index')">
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span>Solicitudes por revisar</span>
                                        <span class="font-bold" :class="pendingRequests > 0 ? 'text-red-500' : 'text-gray-400'">{{ pendingRequests }}</span>
                                    </div>
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>

                    <!-- SECCIÓN ESCRITORIO (Oculta en móvil) -->
                    <div class="hidden sm:flex sm:items-center sm:ms-2">
                        <!-- registro asistencia -->
                        <section v-if="$page.props.auth.user.home_office" class="mr-4 flex items-center space-x-2">
                            <!-- pausa -->
                            <p v-if="isPaused" class="text-xs mt-1">Pausaste a las {{ isPaused }}</p>
                            <el-popconfirm v-if="isPaused !== false && nextAttendance == 'Registrar salida'"
                                confirm-button-text="Si" cancel-button-text="No" icon-color="#373737"
                                :title="isPaused ? '¿Reanudar?' : '¿Pausar tiempo?'" @confirm="$emit('setPause')">
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
                                @confirm="$emit('setAttendance')">
                                <template #reference>
                                    <button v-if="nextAttendance == 'Registrar entrada'"
                                        class="flex items-center space-x-2 text-primary bg-[#DBF0FA] text-sm rounded-full px-3 py-1 lg:mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                        </svg>
                                        <span>Registrar entrada</span>
                                    </button>
                                    <button v-else-if="nextAttendance == 'Registrar salida'"
                                        class="flex items-center space-x-2 text-primary bg-[#DBF0FA] text-sm rounded-full px-3 py-1 lg:mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                                        </svg>
                                        <span>Registrar salida</span>
                                    </button>
                                </template>
                            </el-popconfirm>
                            <p v-else class="flex items-center space-x-2 text-[#179E15] bg-[#C8FEC7] text-sm rounded-full px-3 py-1 lg:mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
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
                                            <svg width="10" height="13" viewBox="0 0 12 15" fill="none" class="mr-2" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M8.50033 3.50033C8.50033 4.16346 8.23691 4.79943 7.768 5.26834C7.2991 5.73724 6.66313 6.00067 6 6.00067C5.33687 6.00067 4.7009 5.73724 4.232 5.26834C3.76309 4.79943 3.49967 4.16346 3.49967 2.8372 3.76309 2.20123 4.232 1.73233C4.7009 1.26343 5.33687 1 6 1C6.66313 1 7.2991 1.26343 7.768 1.73233C8.23691 2.20123 8.50033 2.8372 8.50033 3.50033ZM1 12.9136C1.02143 11.6016 1.55763 10.3507 2.49298 9.43049C3.42833 8.51029 4.68788 7.99458 6 7.99458C7.31212 7.99458 8.57166 8.51029 9.50702 9.43049C10.4424 10.3507 10.9786 11.6016 11 12.9136C9.43138 13.6329 7.72566 14.0041 6 14.0017C4.21576 14.0017 2.5222 13.6123 1 12.9136Z" stroke="#6D6E72" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            {{ $page.props.auth.user.name }}
                                            <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        Cuenta
                                    </div>
                                    <DropdownLink :href="route('profile.show')">
                                        Perfil
                                    </DropdownLink>
                                    <DropdownLink :href="route('my-payrolls')">
                                        Mis nóminas / vacaciones
                                    </DropdownLink>
                                    <div class="border-t border-gray-200" />
                                    <form @submit.prevent="logout">
                                        <DropdownLink as="button">
                                            Cerrar sesión
                                        </DropdownLink>
                                    </form>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                    
                    <!-- Hamburger (Mobile Toggle) -->
                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                            @click="$emit('toggleMenu')">
                            <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </nav>
</template>