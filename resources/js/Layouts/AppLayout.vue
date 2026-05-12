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

// --- NUEVA LÓGICA DE GEOLOCALIZACIÓN ---
const handleAttendanceRequest = () => {
    // Verificar si el dispositivo soporta geolocalización
    if (!("geolocation" in navigator)) {
        setAttendance('GPS No Soportado');
        return;
    }

    // Notificar al usuario que estamos validando ubicación
    ElNotification.info({
        title: "Validando Ubicación",
        message: "Por favor, permite el acceso a tu ubicación si el navegador lo solicita. Cargando GPS...",
        duration: 3000
    });

    navigator.geolocation.getCurrentPosition(
        (position) => {
            // Éxito: Enviar las coordenadas
            const locationString = `${position.coords.latitude}, ${position.coords.longitude}`;
            setAttendance(locationString);
        },
        (error) => {
            // Si el usuario deniega o falla, enviamos el motivo del fallo
            console.warn("No se pudo obtener GPS:", error);
            let errorMsg = 'Ubicación denegada/no disponible';
            if (error.code === error.PERMISSION_DENIED) errorMsg = 'Permiso denegado por el usuario';
            else if (error.code === error.POSITION_UNAVAILABLE) errorMsg = 'Ubicación no disponible';
            else if (error.code === error.TIMEOUT) errorMsg = 'Tiempo de espera agotado';

            setAttendance(errorMsg);
        },
        {
            enableHighAccuracy: true, // Forzar GPS de alta precisión
            timeout: 10000,           // 10 segundos para encontrarla
            maximumAge: 0             // No usar caché, forzar ubicación actual
        }
    );
};

const setAttendance = async (locationData = null) => {
    try {
        const response = await axios.post(route("users.set-attendance"), {
            location: locationData // Enviamos las coordenadas (o el mensaje de fallo)
        });
        
        if (response.status === 200) {
            nextAttendance.value = response.data.next;
            isPaused.value = null;
            ElNotification.success({
                title: "Registro correcto",
                message: locationData && !locationData.includes('denegada') && !locationData.includes('Soportado') 
                    ? "Ubicación validada y guardada exitosamente." 
                    : "Asistencia registrada sin ubicación GPS.",
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
            @set-attendance="handleAttendanceRequest"
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
                    @set-attendance="handleAttendanceRequest"
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