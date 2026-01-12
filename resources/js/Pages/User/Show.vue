<script setup>
import { ref, onMounted } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Back from "@/Components/MyComponents/Back.vue";
import General from "./Tabs/General.vue";
import DigitalDocuments from "./Tabs/DigitalDocuments.vue";
import axios from "axios";
import { ElNotification } from "element-plus";

const props = defineProps({
    user: Object,
    users: Array,
    vacations: Array,
});

// State
const loading = ref(false);
const selectedItem = ref(props.user.id);
const activeTab = ref('1');

// Methods
const handleClickInTab = (tab) => {
    const currentURL = new URL(window.location.href);
    currentURL.searchParams.set('currentTab', tab.props.name);
    window.history.replaceState({}, document.title, currentURL.href);
};

const setTabFromUrl = () => {
    const currentURL = new URL(window.location.href);
    const currentTabFromURL = currentURL.searchParams.get('currentTab');
    if (currentTabFromURL) {
        activeTab.value = currentTabFromURL;
    }
};

const resetPassword = async () => {
    try {
        loading.value = true;
        const response = await axios.put(route('users.reset-password', props.user.id));

        if (response.status === 200) {
            ElNotification({
                title: "Correcto",
                message: "Se ha reseteado la contraseña a '123456'",
                type: "success",
            });
        }
    } catch (error) {
        console.error(error);
        ElNotification({
            title: "Error",
            message: "No se pudo resetear la contraseña.",
            type: "error",
        });
    } finally {
        loading.value = false;
    }
};

const navigateToUser = (id) => {
    router.visit(route('users.show', id));
};

onMounted(() => {
    setTabFromUrl();
});
</script>

<template>
    <AppLayout :title="`Perfil - ${user.name}`">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Encabezado y Navegación -->
                <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                    <div class="flex items-center self-start md:self-auto">
                        <Back :route="route('users.index')" class="mr-4" />
                        <h1 class="text-2xl font-bold text-gray-800 hidden md:block">Expediente de Usuario</h1>
                    </div>

                    <!-- Selector de Usuario y Acciones -->
                    <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
                        <div class="w-full md:w-64">
                            <el-select 
                                v-model="selectedItem" 
                                @change="navigateToUser"
                                placeholder="Buscar usuario..." 
                                filterable
                                class="w-full"
                            >
                                <template #prefix>
                                    <i class="fa-solid fa-user-magnifying-glass text-gray-400"></i>
                                </template>
                                <el-option v-for="item in users" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                        </div>

                        <div class="flex items-center gap-2 self-end md:self-auto">
                            <!-- Botón Editar -->
                            <PrimaryButton 
                                v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                                @click="router.visit(route('users.edit', user.id))" 
                                :disabled="loading"
                                class="!py-2"
                            >
                                <i class="fa-solid fa-pen-to-square mr-2"></i> Editar
                            </PrimaryButton>

                            <!-- Botón Reset Password -->
                            <el-popconfirm 
                                v-if="$page.props.auth.user.permissions.includes('Resetear contraseñas')"
                                confirm-button-text="Si" 
                                cancel-button-text="No" 
                                icon-color="#F59E0B"
                                title="¿Resetear contraseña a '123456'?"
                                @confirm="resetPassword"
                            >
                                <template #reference>
                                    <button class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 hover:text-red-600 px-3 py-2 rounded-md shadow-sm transition-colors text-sm font-medium" :disabled="loading" title="Resetear contraseña">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                </template>
                            </el-popconfirm>

                            <!-- Botón Nuevo -->
                            <button 
                                @click="router.visit(route('users.create'))" 
                                class="bg-indigo-600 text-white hover:bg-indigo-700 px-3 py-2 rounded-md shadow-sm transition-colors"
                                title="Crear nuevo usuario"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Grid Principal -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
                    
                    <!-- Columna Izquierda: Tarjeta de Perfil -->
                    <div class="lg:col-span-4 xl:col-span-3">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col items-center text-center sticky top-24">
                            
                            <!-- Foto -->
                            <div class="relative mb-4 group">
                                <img :src="user.profile_photo_url" :alt="user.name" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-md">
                                <div v-if="!user.is_active" class="absolute bottom-1 right-1 bg-red-100 text-red-600 p-1.5 rounded-full border-2 border-white" title="Usuario Inactivo">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                </div>
                                <div v-else class="absolute bottom-1 right-1 bg-green-100 text-green-600 p-1.5 rounded-full border-2 border-white" title="Usuario Activo">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            </div>

                            <h2 class="text-xl font-bold text-gray-800">{{ user.name }}</h2>
                            <p class="text-sm text-gray-500 font-medium mb-4">{{ user.org_props?.position || 'Sin puesto definido' }}</p>

                            <!-- Badge Pausa -->
                            <div v-if="user.paused" class="bg-amber-50 text-amber-700 px-3 py-1 rounded-full text-xs font-bold border border-amber-100 flex items-center gap-1 mb-4">
                                <i class="fa-solid fa-pause"></i> En pausa: {{ user.paused }}
                            </div>

                            <div class="w-full border-t border-gray-100 my-4"></div>

                            <!-- Info Rápida -->
                            <div class="w-full space-y-3 text-sm text-left">
                                <div class="flex items-center text-gray-600">
                                    <div class="w-8 flex justify-center"><i class="fa-solid fa-id-card text-gray-400"></i></div>
                                    <span class="font-mono text-gray-800">{{ user.code || 'N/A' }}</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <div class="w-8 flex justify-center"><i class="fa-solid fa-envelope text-gray-400"></i></div>
                                    <span class="truncate" :title="user.email">{{ user.email }}</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <div class="w-8 flex justify-center"><i class="fa-solid fa-phone text-gray-400"></i></div>
                                    <span>{{ user.phone || 'N/A' }}</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <div class="w-8 flex justify-center"><i class="fa-solid fa-building text-gray-400"></i></div>
                                    <span>{{ user.org_props?.department || 'N/A' }}</span>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Columna Derecha: Tabs -->
                    <div class="lg:col-span-8 xl:col-span-9">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden min-h-[600px]">
                            <el-tabs v-model="activeTab" class="user-tabs px-6 pt-4" @tab-click="handleClickInTab">
                                
                                <el-tab-pane name="1">
                                    <template #label>
                                        <span class="flex items-center gap-2">
                                            <i class="fa-regular fa-id-badge"></i> Información General
                                        </span>
                                    </template>
                                    <div class="py-6 animate-fade-in">
                                        <General :user="user" :vacations="vacations" />
                                    </div>
                                </el-tab-pane>

                                <el-tab-pane name="2">
                                    <template #label>
                                        <span class="flex items-center gap-2">
                                            <i class="fa-regular fa-folder-open"></i> Expediente Digital
                                        </span>
                                    </template>
                                    <div class="py-6 animate-fade-in">
                                        <DigitalDocuments :user="user" />
                                    </div>
                                </el-tab-pane>

                            </el-tabs>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
/* Personalización de Tabs similar a ProductShow */
:deep(.el-tabs__nav-wrap::after) {
    background-color: #f3f4f6;
    height: 1px;
}
:deep(.el-tabs__item) {
    font-size: 0.95rem;
    color: #6b7280;
    transition: all 0.3s;
    padding-bottom: 12px !important; 
    padding-top: 12px !important;
}
:deep(.el-tabs__item.is-active) {
    color: #4f46e5;
    font-weight: 600;
}
:deep(.el-tabs__active-bar) {
    background-color: #4f46e5;
    height: 3px;
    border-radius: 3px;
}

.animate-fade-in {
    animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>