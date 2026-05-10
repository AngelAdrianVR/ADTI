<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import SideNav from '@/Components/MyComponents/SideNav.vue';
import Topbar from '@/Layouts/Topbar.vue';
import MobileMenu from '@/Layouts/MobileMenu.vue';
import ProjectTimer from '@/Layouts/ProjectTimer.vue';
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
        if (error?.response?.status === 422) {
            ElNotification.error({
                message: error.response.data.message,
                type: "error",
            });
        } else {
            ElNotification.error({
                message: 'Hubo un problema en el servidor, repórtalo con soporte',
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
        if (error?.response?.status === 422) {
            ElNotification.error({
                message: error.response.data.message,
                type: "error",
            });
        } else {
            ElNotification.error({
                message: 'Hubo un problema en el servidor, repórtalo con soporte',
                type: "error",
            });
        }
    }
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

        <!-- Componente Menú Móvil (Drawer) -->
        <MobileMenu
            :show="showingNavigationDropdown"
            :next-attendance="nextAttendance"
            :is-paused="isPaused"
            :pending-requests="$page.props.auth.user?.pendingVacationRequests || 0"
            @close="showingNavigationDropdown = false"
            @set-pause="setPause"
            @set-attendance="setAttendance"
        />

        <div class="overflow-hidden h-screen md:flex bg-white relative">
            <!-- Menú Lateral (Desktop) -->
            <aside class="col-span-2 w-auto hidden md:block z-30">
                <SideNav />
            </aside>

            <main class="w-full flex flex-col h-screen relative">
                <!-- Componente Barra Superior -->
                <Topbar
                    :next-attendance="nextAttendance"
                    :is-paused="isPaused"
                    :pending-requests="$page.props.auth.user?.pendingVacationRequests || 0"
                    @toggle-menu="showingNavigationDropdown = !showingNavigationDropdown"
                    @set-pause="setPause"
                    @set-attendance="setAttendance"
                />

                <div class="overflow-y-auto flex-1 bg-white relative">
                    <!-- Contenido principal inyectado -->
                    <slot />
                    
                    <!-- Componente Temporizador Flotante -->
                    <ProjectTimer />
                </div>
            </main>
        </div>
    </div>
</template>