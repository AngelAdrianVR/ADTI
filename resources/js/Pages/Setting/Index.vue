<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import RolePermissions from './Tabs/RolePermissions.vue'; // Nuevo componente unificado
import Categories from './Tabs/Categories.vue';
import Features from './Tabs/Features.vue';
import Departments from './Tabs/Departments.vue';
import JobPositions from './Tabs/JobPositions.vue';

const props = defineProps({
    roles: Array,
    permissions: Object,
    features: Array,
    departments: Array,
    job_positions: Array,
    section: {
        type: String,
        required: true
    }
});

const activeTab = ref('1');

const handleClick = (tab) => {
    // Agrega la variable currentTab a la URL
    const currentURL = new URL(window.location.href);
    currentURL.searchParams.set('currentTab', tab.props.name);
    window.history.replaceState({}, document.title, currentURL.href);
};

onMounted(() => {
    // Recuperar la tab activa de la URL si existe (solo para sección 'general')
    const currentURL = new URL(window.location.href);
    const currentTabFromURL = currentURL.searchParams.get('currentTab');

    if (currentTabFromURL) {
        activeTab.value = currentTabFromURL;
    }
});
</script>

<template>
    <AppLayout title="Configuraciones">
        <header class="mb-6 mt-4 px-2 lg:px-14">
            <h1 class="text-lg font-bold">Configuraciones</h1>
            <p class="text-sm text-gray-500">
                <span v-if="section === 'categories'">Gestión de categorías</span>
                <span v-if="section === 'permissions'">Control de acceso y seguridad</span>
                <span v-if="section === 'general'">Variables generales del sistema</span>
            </p>
        </header>
        
        <main class="px-2 lg:px-14 pb-12">
            
            <!-- SECCIÓN 1: CATEGORÍAS -->
            <div v-if="section === 'categories'">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2 text-primary">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.907-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                        </svg>
                        Categorías y Subcategorías
                    </h2>
                    <Categories />
                </div>
            </div>

            <!-- SECCIÓN 2: ROLES Y PERMISOS (UNIFICADO) -->
            <div v-if="section === 'permissions'">
                <!-- Componente único que maneja sus propias pestañas internas -->
                <RolePermissions :roles="roles" :permissions="permissions" />
            </div>

            <!-- SECCIÓN 3: GENERAL -->
            <el-tabs v-if="section === 'general'" v-model="activeTab" @tab-click="handleClick" class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 pt-2">
                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver caracteristicas')" name="1">
                    <template #label>
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-list-check text-gray-500"></i>
                            <span>Características</span>
                        </div>
                    </template>
                    <Features :features="features" />
                </el-tab-pane>

                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver departamentos')" name="2">
                    <template #label>
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-building text-gray-500"></i>
                            <span>Departamentos</span>
                        </div>
                    </template>
                    <Departments :departments="departments" />
                </el-tab-pane>

                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver puestos')" name="3">
                    <template #label>
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-id-badge text-gray-500"></i>
                            <span>Puestos</span>
                        </div>
                    </template>
                    <JobPositions :job-positions="job_positions" />
                </el-tab-pane>
            </el-tabs>

        </main>
    </AppLayout>
</template>

<style scoped>
:deep(.el-tabs__nav-wrap::after) {
    background-color: #f3f4f6;
    height: 1px;
}
:deep(.el-tabs__item) {
    color: #6b7280;
    font-weight: 500;
}
:deep(.el-tabs__item.is-active) {
    color: var(--el-color-primary);
    font-weight: 700;
}
</style>