<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ElNotification } from "element-plus";

const page = usePage();

// --- Lógica del Timer de Proyecto ---
const activeEntry = computed(() => page.props.auth?.user?.active_entry);
const timerDisplay = ref('00:00:00');
let timerInterval = null;
const isHidden = ref(false);

const startLocalTimer = () => {
    if (!activeEntry.value) return;
    
    const updateTimer = () => {
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

    updateTimer(); 
    timerInterval = setInterval(updateTimer, 1000); 
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
                message: `Has dejado de trabajar en: ${activeEntry.value?.project?.name || 'Proyecto'}`,
            });
            
            window.location.reload();
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
</script>

<template>
    <div v-if="activeEntry && !isHidden" 
         class="fixed bottom-0 right-0 left-0 sm:left-auto sm:bottom-6 sm:right-6 z-50 bg-gray-800 text-white shadow-lg sm:rounded-lg overflow-hidden flex items-center justify-between transition-all duration-300 transform translate-y-0">
        
        <!-- Barra de progreso indeterminada superior -->
        <div class="absolute top-0 left-0 w-full h-0.5 bg-gray-700 overflow-hidden">
            <div class="h-full bg-[#1676A2] animate-progress"></div>
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
</template>

<style scoped>
@keyframes progress {
  0% { width: 0%; margin-left: 0%; }
  50% { width: 50%; margin-left: 25%; }
  100% { width: 100%; margin-left: 100%; }
}
.animate-progress {
  animation: progress 2s infinite linear;
}
</style>