<script setup>
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ActiveUsers from './Tabs/ActiveUsers.vue';
import DisabledUsers from './Tabs/DisabledUsers.vue';

const props = defineProps({
    users: Array,
});

const activeTab = ref('1');

const handleClick = (tab) => {
    const currentURL = new URL(window.location.href);
    currentURL.searchParams.set('currentTab', tab.props.name);
    window.history.replaceState({}, document.title, currentURL.href);
};

onMounted(() => {
    const currentURL = new URL(window.location.href);
    const currentTabFromURL = currentURL.searchParams.get('currentTab');
    if (currentTabFromURL) {
        activeTab.value = currentTabFromURL;
    }
});
</script>

<template>
    <AppLayout title="Usuarios">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Directorio de Usuarios</h1>
                        <p class="text-xs text-gray-500 mt-1">Gestión de personal, cuentas y accesos.</p>
                    </div>
                    
                    <PrimaryButton 
                        v-if="$page.props.auth.user.permissions?.includes('Crear usuarios')"
                        @click="router.visit(route('users.create'))"
                        class="!bg-[#1676A2] hover:!bg-[#125d80]"
                    >
                        <i class="fa-solid fa-user-plus mr-2"></i> Crear Usuario
                    </PrimaryButton>
                </div>

                <!-- Contenido Principal -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden min-h-[600px]">
                    <el-tabs v-model="activeTab" @tab-click="handleClick" class="px-6 pt-4 user-tabs">
                        
                        <!-- Tab Activos -->
                        <el-tab-pane name="1">
                            <template #label>
                                <span class="flex items-center gap-2">
                                    <i class="fa-solid fa-users text-green-600"></i>
                                    Activos
                                    <span class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                        {{ users.filter(u => u.is_active).length }}
                                    </span>
                                </span>
                            </template>
                            <div class="py-4 animate-fade-in">
                                <ActiveUsers :users="users.filter(user => user.is_active)" />
                            </div>
                        </el-tab-pane>

                        <!-- Tab Inactivos -->
                        <el-tab-pane name="2">
                            <template #label>
                                <span class="flex items-center gap-2">
                                    <i class="fa-solid fa-user-slash text-gray-400"></i>
                                    Inactivos
                                    <span class="bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                        {{ users.filter(u => !u.is_active).length }}
                                    </span>
                                </span>
                            </template>
                            <div class="py-4 animate-fade-in">
                                <DisabledUsers :users="users.filter(user => !user.is_active)" />
                            </div>
                        </el-tab-pane>

                    </el-tabs>
                </div>

            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
/* Personalización de Tabs */
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
    color: #1676A2;
    font-weight: 600;
}
:deep(.el-tabs__active-bar) {
    background-color: #1676A2;
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