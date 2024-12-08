<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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

const showingNavigationDropdown = ref(false);
const nextAttendance = ref("");
const isPaused = ref(false);

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
            // resetear si habia alguna pausa
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

        <div class="overflow-hidden h-screen md:flex bg-white">

            <!-- sidenav -->
            <aside class="col-span-2 w-auto">
                <SideNav />
            </aside>

            <!-- resto de pagina -->
            <main class="w-full">
                <nav class="bg-white border-b border-grayD9">
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
                            <!-- Hamburger -->
                            <div class="-me-2 flex items-center sm:hidden">
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
                                <button
                                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                                    @click="showingNavigationDropdown = !showingNavigationDropdown">
                                    <svg class="size-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path
                                            :class="{ 'hidden': showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 6h16M4 12h16M4 18h16" />
                                        <path
                                            :class="{ 'hidden': !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Responsive Navigation Menu -->
                    <div :class="{ 'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown }"
                        class="sm:hidden bg-gradient-to-r from-gray-600 to-secondary">
                        <div class="pt-2 pb-3 space-y-px">
                            <ResponsiveNavLink :href="route('welcome')" :active="route().current('welcome')">
                                <div class="flex items-center space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                                    </svg>
                                    <span class="text-xs">Página principal</span>
                                </div>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                <div class="flex items-center space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                                    </svg>
                                    <span class="text-xs">Panel de inicio</span>
                                </div>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="$page.props.auth.user.permissions.includes('Ver productos')"
                                :href="route('products.index')" :active="route().current('products.*')">
                                <div class="flex items-center space-x-2">
                                    <svg width="13" height="13" viewBox="0 0 17 17" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M2.25 8.38583C2.25 10.0434 2.90848 11.6331 4.08058 12.8053C5.25268 13.9774 6.8424 14.6358 8.5 14.6358C10.1576 14.6358 11.7473 13.9774 12.9194 12.8053C14.0915 11.6331 14.75 10.0434 14.75 8.38583M2.25 8.38583C2.25 6.72823 2.90848 5.13852 4.08058 3.96642C5.25268 2.79431 6.8424 2.13583 8.5 2.13583C10.1576 2.13583 11.7473 2.79431 12.9194 3.96642C14.0915 5.13852 14.75 6.72823 14.75 8.38583M2.25 8.38583H1M14.75 8.38583H16M14.75 8.38583H8.5L4.75 1.89M1.4525 10.95L2.6275 10.5225M14.3733 6.2475L15.5483 5.82M2.755 13.2067L3.71333 12.4033M13.2883 4.36833L14.2458 3.565M4.75083 14.8817L5.37583 13.7983L8.50167 8.38583M11.6258 2.97333L12.2508 1.89M7.19833 15.7717L7.415 14.5408M9.58583 2.23083L9.8025 1M9.8025 15.7717L9.58583 14.5408M7.415 2.23083L7.19833 1M12.25 14.8808L11.625 13.7983M14.245 13.2067L13.2875 12.4033M3.71333 4.3675L2.755 3.56417M15.5483 10.9508L14.3733 10.5233M2.62833 6.24833L1.45333 5.82"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                    <span class="text-xs">Productos</span>
                                </div>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="$page.props.auth.user.permissions.includes('Ver usuarios')"
                                :href="route('users.index')" :active="route().current('users.*')">
                                <div class="flex items-center space-x-2">
                                    <svg width="13" height="13" viewBox="0 0 18 17" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M11.6258 14.44C12.3366 14.6464 13.0732 14.7508 13.8133 14.75C15.0037 14.7517 16.1785 14.4803 17.2475 13.9567C17.2791 13.2098 17.0664 12.4729 16.6415 11.8578C16.2166 11.2427 15.6028 10.7828 14.8931 10.5479C14.1834 10.313 13.4164 10.3159 12.7085 10.5562C12.0005 10.7964 11.3902 11.2609 10.97 11.8792M11.6258 14.44V14.4375C11.6258 13.51 11.3875 12.6375 10.97 11.8792M11.6258 14.44V14.5283C10.0221 15.4942 8.18468 16.0032 6.3125 16C4.37 16 2.5525 15.4625 1.00083 14.5283L1 14.4375C0.999361 13.2579 1.39134 12.1116 2.11415 11.1794C2.83696 10.2472 3.84948 9.58203 4.99207 9.28883C6.13467 8.99564 7.34235 9.09107 8.42472 9.56008C9.50709 10.0291 10.4026 10.845 10.97 11.8792M9.12583 3.8125C9.12583 4.55842 8.82952 5.27379 8.30207 5.80124C7.77463 6.32868 7.05926 6.625 6.31333 6.625C5.56741 6.625 4.85204 6.32868 4.3246 5.80124C3.79715 5.27379 3.50083 4.55842 3.50083 3.8125C3.50083 3.06658 3.79715 2.35121 4.3246 1.82376C4.85204 1.29632 5.56741 1 6.31333 1C7.05926 1 7.77463 1.29632 8.30207 1.82376C8.82952 2.35121 9.12583 3.06658 9.12583 3.8125ZM16.0008 5.6875C16.0008 6.26766 15.7704 6.82406 15.3601 7.2343C14.9499 7.64453 14.3935 7.875 13.8133 7.875C13.2332 7.875 12.6768 7.64453 12.2665 7.2343C11.8563 6.82406 11.6258 6.26766 11.6258 5.6875C11.6258 5.10734 11.8563 4.55094 12.2665 4.1407C12.6768 3.73047 13.2332 3.5 13.8133 3.5C14.3935 3.5 14.9499 3.73047 15.3601 4.1407C15.7704 4.55094 16.0008 5.10734 16.0008 5.6875Z"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                    <span class="text-xs">Usuarios</span>
                                </div>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="$page.props.auth.user.permissions.includes('Ver incidencias')"
                                :href="route('payrolls.index')" :active="route().current('payrolls.*')">
                                <div class="flex items-center space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" />
                                    </svg>
                                    <span class="text-xs">Incidencias</span>
                                </div>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="$page.props.auth.user.permissions.includes('Ver dias festivos')"
                                :href="route('holidays.index')" :active="route().current('holidays.*')">
                                <div class="flex items-center space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    <span class="text-xs">Días festivos</span>
                                </div>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="$page.props.auth.user?.permissions?.some(permission => {
                                return ['Ver categorias', 'Ver roles', 'Ver permisos', 'Ver caracteristicas', 'Ver departamentos', 'Ver puestos'].includes(permission);
                            })" :href="route('settings.index')" :active="route().current('settings.*')">
                                <div class="flex items-center space-x-2">
                                    <svg width="13" height="13" viewBox="0 0 16 15" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M6.625 2.25H14.75M6.625 2.25C6.625 2.58152 6.4933 2.89946 6.25888 3.13388C6.02446 3.3683 5.70652 3.5 5.375 3.5C5.04348 3.5 4.72554 3.3683 4.49112 3.13388C4.2567 2.89946 4.125 2.58152 4.125 2.25M6.625 2.25C6.625 1.91848 6.4933 1.60054 6.25888 1.36612C6.02446 1.1317 5.70652 1 5.375 1C5.04348 1 4.72554 1.1317 4.49112 1.36612C4.2567 1.60054 4.125 1.91848 4.125 2.25M4.125 2.25H1M6.625 12.25H14.75M6.625 12.25C6.625 12.5815 6.4933 12.8995 6.25888 13.1339C6.02446 13.3683 5.70652 13.5 5.375 13.5C5.04348 13.5 4.72554 13.3683 4.49112 13.1339C4.2567 12.8995 4.125 12.5815 4.125 12.25M6.625 12.25C6.625 11.9185 6.4933 11.6005 6.25888 11.3661C6.02446 11.1317 5.70652 11 5.375 11C5.04348 11 4.72554 11.1317 4.49112 11.3661C4.2567 11.6005 4.125 11.9185 4.125 12.25M4.125 12.25H1M11.625 7.25H14.75M11.625 7.25C11.625 7.58152 11.4933 7.89946 11.2589 8.13388C11.0245 8.3683 10.7065 8.5 10.375 8.5C10.0435 8.5 9.72554 8.3683 9.49112 8.13388C9.2567 7.89946 9.125 7.58152 9.125 7.25M11.625 7.25C11.625 6.91848 11.4933 6.60054 11.2589 6.36612C11.0245 6.1317 10.7065 6 10.375 6C10.0435 6 9.72554 6.1317 9.49112 6.36612C9.2567 6.60054 9.125 6.91848 9.125 7.25M9.125 7.25H1"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                    <span class="text-xs">Configuraciones</span>
                                </div>
                            </ResponsiveNavLink>
                        </div>

                        <!-- Responsive Settings Options -->
                        <div class="pt-4 pb-1 border-t border-gray-200">
                            <div class="flex items-center px-4">
                                <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                    <img class="h-10 w-10 rounded-full object-cover"
                                        :src="$page.props.auth.user.profile_photo_url"
                                        :alt="$page.props.auth.user.name">
                                </div>

                                <div>
                                    <div class="font-medium text-base text-white">
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div class="font-medium text-sm text-gray-100">
                                        {{ $page.props.auth.user.org_props?.email ?? '*Sin correo empresarial' }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 space-y-1">
                                <ResponsiveNavLink :href="route('profile.show')"
                                    :active="route().current('profile.show')">
                                    Perfil
                                </ResponsiveNavLink>

                                <!-- Authentication -->
                                <form method="POST" @submit.prevent="logout">
                                    <ResponsiveNavLink as="button">
                                        <i
                                            class="fa-solid fa-arrow-right-from-bracket text-xs text-red-600 rotate-180 mr-2"></i>
                                        <span class="text-red-600">Cerrar sesión</span>
                                    </ResponsiveNavLink>
                                </form>

                            </div>
                        </div>
                    </div>
                </nav>

                <div class="overflow-y-auto h-[calc(100vh-3rem)] bg-white">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
