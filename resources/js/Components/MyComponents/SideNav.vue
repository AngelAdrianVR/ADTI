<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

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
        // Icono Home
        icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>',
        route: 'welcome',
        active: route().current('welcome'),
        show: true
    },
    {
        label: 'Panel de inicio',
        // Icono Dashboard
        icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" /></svg>',
        route: 'dashboard',
        active: route().current('dashboard'),
        show: true
    },
    {
        label: 'Productos',
        // Icono Productos
        icon: '<svg width="24" height="24" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.25 8.38583C2.25 10.0434 2.90848 11.6331 4.08058 12.8053C5.25268 13.9774 6.8424 14.6358 8.5 14.6358C10.1576 14.6358 11.7473 13.9774 12.9194 12.8053C14.0915 11.6331 14.75 10.0434 14.75 8.38583M2.25 8.38583C2.25 6.72823 2.90848 5.13852 4.08058 3.96642C5.25268 2.79431 6.8424 2.13583 8.5 2.13583C10.1576 2.13583 11.7473 2.79431 12.9194 3.96642C14.0915 5.13852 14.75 6.72823 14.75 8.38583M2.25 8.38583H1M14.75 8.38583H16M14.75 8.38583H8.5L4.75 1.89M1.4525 10.95L2.6275 10.5225M14.3733 6.2475L15.5483 5.82M2.755 13.2067L3.71333 12.4033M13.2883 4.36833L14.2458 3.565M4.75083 14.8817L5.37583 13.7983L8.50167 8.38583M11.6258 2.97333L12.2508 1.89M7.19833 15.7717L7.415 14.5408M9.58583 2.23083L9.8025 1M9.8025 15.7717L9.58583 14.5408M7.415 2.23083L7.19833 1M12.25 14.8808L11.625 13.7983M14.245 13.2067L13.2875 12.4033M3.71333 4.3675L2.755 3.56417M15.5483 10.9508L14.3733 10.5233M2.62833 6.24833L1.45333 5.82" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        route: 'products.index',
        active: route().current('products.*'),
        show: page.props.auth.user.permissions.includes('Ver productos')
    },
    {
        label: 'Proyectos',
        // Icono Maletin
        icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" /></svg>',
        route: 'projects.index',
        active: route().current('projects.*'),
        show: page.props.auth.user.permissions.includes('Ver proyectos')
    },
    {
        label: 'Usuarios',
        // Icono Usuarios
        icon: '<svg width="24" height="24" viewBox="0 0 18 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.6258 14.44C12.3366 14.6464 13.0732 14.7508 13.8133 14.75C15.0037 14.7517 16.1785 14.4803 17.2475 13.9567C17.2791 13.2098 17.0664 12.4729 16.6415 11.8578C16.2166 11.2427 15.6028 10.7828 14.8931 10.5479C14.1834 10.313 13.4164 10.3159 12.7085 10.5562C12.0005 10.7964 11.3902 11.2609 10.97 11.8792M11.6258 14.44V14.4375C11.6258 13.51 11.3875 12.6375 10.97 11.8792M11.6258 14.44V14.5283C10.0221 15.4942 8.18468 16.0032 6.3125 16C4.37 16 2.5525 15.4625 1.00083 14.5283L1 14.4375C0.999361 13.2579 1.39134 12.1116 2.11415 11.1794C2.83696 10.2472 3.84948 9.58203 4.99207 9.28883C6.13467 8.99564 7.34235 9.09107 8.42472 9.56008C9.50709 10.0291 10.4026 10.845 10.97 11.8792M9.12583 3.8125C9.12583 4.55842 8.82952 5.27379 8.30207 5.80124C7.77463 6.32868 7.05926 6.625 6.31333 6.625C5.56741 6.625 4.85204 6.32868 4.3246 5.80124C3.79715 5.27379 3.50083 4.55842 3.50083 3.8125C3.50083 3.06658 3.79715 2.35121 4.3246 1.82376C4.85204 1.29632 5.56741 1 6.31333 1C7.05926 1 7.77463 1.29632 8.30207 1.82376C8.82952 2.35121 9.12583 3.06658 9.12583 3.8125ZM16.0008 5.6875C16.0008 6.26766 15.7704 6.82406 15.3601 7.2343C14.9499 7.64453 14.3935 7.875 13.8133 7.875C13.2332 7.875 12.6768 7.64453 12.2665 7.2343C11.8563 6.82406 11.6258 6.26766 11.6258 5.6875C11.6258 5.10734 11.8563 4.55094 12.2665 4.1407C12.6768 3.73047 13.2332 3.5 13.8133 3.5C14.3935 3.5 14.9499 3.73047 15.3601 4.1407C15.7704 4.55094 16.0008 5.10734 16.0008 5.6875Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        route: 'users.index',
        active: route().current('users.*'),
        show: page.props.auth.user.permissions.includes('Ver usuarios')
    },
    {
        label: 'Incidencias',
        // Icono Incidencias
        icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125-1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" /></svg>',
        route: 'payrolls.index',
        active: route().current('payrolls.*'),
        show: page.props.auth.user.permissions.includes('Ver incidencias') || (page.props.auth.user.employees_in_charge && page.props.auth.user.employees_in_charge.length > 0)
    },
    {
        label: 'Días festivos',
        // Icono Días festivos
        icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>',
        route: 'holidays.index',
        active: route().current('holidays.*'),
        show: page.props.auth.user.permissions.includes('Ver dias festivos')
    },
    {
        label: 'Configuraciones',
        // Icono Settings (Tuerca)
        icon: '<svg width="24" height="24" viewBox="0 0 16 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.625 2.25H14.75M6.625 2.25C6.625 2.58152 6.4933 2.89946 6.25888 3.13388C6.02446 3.3683 5.70652 3.5 5.375 3.5C5.04348 3.5 4.72554 3.3683 4.49112 3.13388C4.2567 2.89946 4.125 2.58152 4.125 2.25M6.625 2.25C6.625 1.91848 6.4933 1.60054 6.25888 1.36612C6.02446 1.1317 5.70652 1 5.375 1C5.04348 1 4.72554 1.1317 4.49112 1.36612C4.2567 1.60054 4.125 1.91848 4.125 2.25M4.125 2.25H1M6.625 12.25H14.75M6.625 12.25C6.625 12.5815 6.4933 12.8995 6.25888 13.1339C6.02446 13.3683 5.70652 13.5 5.375 13.5C5.04348 13.5 4.72554 13.3683 4.49112 13.1339C4.2567 12.8995 4.125 12.5815 4.125 12.25M6.625 12.25C6.625 11.9185 6.4933 11.6005 6.25888 11.3661C6.02446 11.1317 5.70652 11 5.375 11C5.04348 11 4.72554 11.1317 4.49112 11.3661C4.2567 11.6005 4.125 11.9185 4.125 12.25M4.125 12.25H1M11.625 7.25H14.75M11.625 7.25C11.625 7.58152 11.4933 7.89946 11.2589 8.13388C11.0245 8.3683 10.7065 8.5 10.375 8.5C10.0435 8.5 9.72554 8.3683 9.49112 8.13388C9.2567 7.89946 9.125 7.58152 9.125 7.25M11.625 7.25C11.625 6.91848 11.4933 6.60054 11.2589 6.36612C11.0245 6.1317 10.7065 6 10.375 6C10.0435 6 9.72554 6.1317 9.49112 6.36612C9.2567 6.60054 9.125 6.91848 9.125 7.25M9.125 7.25H1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        // No tiene ruta padre directa, solo hijas
        active: route().current('settings.*'),
        show: page.props.auth.user?.permissions?.some(permission => {
            return ['Ver categorias', 'Ver roles', 'Ver permisos', 'Ver caracteristicas', 'Ver departamentos', 'Ver puestos'].includes(permission);
        }),
        children: [
            {
                label: 'Catálogos',
                route: 'settings.index', // Ruta para categorías
                active: route().current('settings.index') && !route().current('settings.permissions') && !route().current('settings.general')
            },
            {
                label: 'Roles y Permisos',
                route: 'settings.permissions', // Ruta para permisos
                active: route().current('settings.permissions')
            },
            {
                label: 'General',
                route: 'settings.general', // Ruta para features, depts, positions
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
                                    <div v-html="menu.icon" class="w-5 h-5 fill-current"></div>
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
                                <div v-html="menu.icon" class="w-5 h-5 fill-current"></div>
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