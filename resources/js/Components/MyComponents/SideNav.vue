<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

// --- Importación de Íconos Limpios ---
import HomeIcon from '@/Components/MyComponents/Icons/HomeIcon.vue';
import DashboardIcon from '@/Components/MyComponents/Icons/DashboardIcon.vue';
import ProductIcon from '@/Components/MyComponents/Icons/ProductIcon.vue';
import ProjectIcon from '@/Components/MyComponents/Icons/ProjectIcon.vue';
import UserIcon from '@/Components/MyComponents/Icons/UserIcon.vue';
import IncidenceIcon from '@/Components/MyComponents/Icons/IncidenceIcon.vue';
import VacationRequestIcon from '@/Components/MyComponents/Icons/VacationRequestIcon.vue';
import HolidayIcon from '@/Components/MyComponents/Icons/HolidayIcon.vue';
import SettingIcon from '@/Components/MyComponents/Icons/SettingIcon.vue';

// --- Estado ---
const page = usePage();
const small = ref(true); // Controla si el menú está colapsado

// --- Métodos de Control ---
const updateSideNavSize = (is_small) => {
    small.value = is_small;
    localStorage.setItem('is_sidenav_small', is_small);
};

// Navegación manual para Element Plus
const handleSelect = (index) => {
    if (index) {
        router.visit(route(index));
    }
};

// --- Configuración del Menú ---
const menus = computed(() => [
    {
        label: 'Página principal',
        icon: HomeIcon,
        route: 'welcome',
        active: route().current('welcome'),
        show: true
    },
    {
        label: 'Panel de inicio',
        icon: DashboardIcon,
        route: 'dashboard',
        active: route().current('dashboard'),
        show: true
    },
    {
        label: 'Productos',
        icon: ProductIcon,
        route: 'products.index',
        active: route().current('products.*'),
        show: page.props.auth.user.permissions.includes('Ver productos')
    },
    {
        label: 'Proyectos',
        icon: ProjectIcon,
        route: 'projects.index',
        active: route().current('projects.*'),
        show: page.props.auth.user.permissions.includes('Ver proyectos')
    },
    {
        label: 'Usuarios',
        icon: UserIcon,
        route: 'users.index',
        active: route().current('users.*'),
        show: page.props.auth.user.permissions.includes('Ver usuarios')
    },
    {
        label: 'Incidencias',
        icon: IncidenceIcon,
        route: 'payrolls.index',
        active: route().current('payrolls.*'),
        show: page.props.auth.user.permissions.includes('Ver incidencias') || (page.props.auth.user.employees_in_charge && page.props.auth.user.employees_in_charge.length > 0)
    },
    {
        label: 'Solicitudes',
        icon: VacationRequestIcon,
        route: 'vacation-requests.index',
        active: route().current('vacation-requests.*'),
        show: page.props.auth.user.permissions.includes('Ver solicitudes de vacaciones') || (page.props.auth.user.employees_in_charge && page.props.auth.user.employees_in_charge.length > 0)
    },
    {
        label: 'Días festivos',
        icon: HolidayIcon,
        route: 'holidays.index',
        active: route().current('holidays.*'),
        show: page.props.auth.user.permissions.includes('Ver dias festivos')
    },
    {
        label: 'Configuraciones',
        icon: SettingIcon,
        active: route().current('settings.*'),
        show: page.props.auth.user?.permissions?.some(permission => {
            return ['Ver categorias', 'Ver roles', 'Ver permisos', 'Ver caracteristicas', 'Ver departamentos', 'Ver puestos'].includes(permission);
        }),
        children: [
            {
                label: 'Catálogos',
                route: 'settings.index',
                active: route().current('settings.index') && !route().current('settings.permissions') && !route().current('settings.general')
            },
            {
                label: 'Roles y Permisos',
                route: 'settings.permissions',
                active: route().current('settings.permissions')
            },
            {
                label: 'General',
                route: 'settings.general',
                active: route().current('settings.general')
            }
        ]
    },
]);

onMounted(() => {
    // Recuperar el estado del sidenav del local storage
    const is_small = localStorage.getItem('is_sidenav_small');
    if (is_small !== null) {
        small.value = is_small === 'true';
    }
});
</script>

<template>
    <div class="bg-white text-gray-700 shadow-xl min-h-screen transition-all duration-300 flex flex-col z-50 relative border-r border-gray-100"
        :class="small ? 'w-16' : 'w-64'">

        <!-- Header / Logo -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-gray-100 bg-gray-50/50">
            <Link :href="route('dashboard')" class="flex items-center space-x-3 overflow-hidden">
                <div class="w-12 h-12 rounded flex items-center justify-center text-white font-bold shrink-0">
                    <img class="w-7" src="/images/isologo.png" alt="Logo" />
                </div>
                <span v-if="!small"
                    class="font-bold text-lg tracking-tight text-gray-800 transition-opacity duration-200">
                    ERP ADTI
                </span>
            </Link>

            <!-- Toggle Button (Solo visible en desktop grande o si quieres) -->
            <button @click="updateSideNavSize(!small)"
                class="text-gray-400 hover:text-primary transition-colors focus:outline-none">
                <svg v-if="small" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                </svg>
            </button>
        </div>

        <!-- Menu Items (Scrollable) -->
        <el-scrollbar class="flex-1">
            <el-menu :default-active="route().current()" class="el-menu-vertical-demo border-none pt-4"
                :collapse="small" @select="handleSelect" text-color="#4B5563" active-text-color="#1676A2">
                <template v-for="(menu, index) in menus" :key="index">
                    <template v-if="menu.show">

                        <!-- CASO 1: Submenú Desplegable -->
                        <el-sub-menu v-if="menu.children" :index="menu.label">
                            <template #title>
                                <el-icon class="text-lg">
                                    <component :is="menu.icon" class="w-5 h-5" />
                                </el-icon>
                                <span>{{ menu.label }}</span>
                            </template>

                            <!-- Items Hijos -->
                            <el-menu-item v-for="(child, childIndex) in menu.children" :key="childIndex"
                                :index="child.route" :class="{ 'is-active': child.active }">
                                <template #title>
                                    <span>{{ child.label }}</span>
                                </template>
                            </el-menu-item>
                        </el-sub-menu>

                        <!-- CASO 2: Enlace Simple -->
                        <el-menu-item v-else :index="menu.route">
                            <el-icon class="text-lg">
                                <component :is="menu.icon" class="w-5 h-5" />
                            </el-icon>
                            <template #title>
                                <span>{{ menu.label }}</span>
                            </template>
                        </el-menu-item>

                    </template>
                </template>
            </el-menu>
        </el-scrollbar>

        <!-- Footer / Info Usuario (Opcional, para rellenar espacio si se desea) -->
        <div v-if="!small" class="p-4 text-xs text-gray-400 text-center border-t border-gray-100">
            &copy; {{ new Date().getFullYear() }} ERP System
        </div>
    </div>
</template>

<style scoped>
/* Ajustes finos para Element Plus */
:deep(.el-menu) {
    background-color: transparent;
}

:deep(.el-menu-item.is-active) {
    background-color: var(--el-color-primary-light-9);
    border-right: 3px solid var(--el-color-primary);
    color: var(--el-color-primary);
    font-weight: 600;
}

:deep(.el-menu-item:hover),
:deep(.el-sub-menu__title:hover) {
    background-color: #f3f4f6;
    /* Gray-100 de tailwind */
}

:deep(.el-menu-item) {
    height: 50px;
    line-height: 50px;
}
</style>