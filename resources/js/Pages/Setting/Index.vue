<template>
    <AppLayout title="Configuraciones">
        <header class="mb-6 mt-4 px-2 lg:px-14">
            <h1 class="text-lg font-bold">Configuraciones</h1>
        </header>
        <main class="px-2 lg:px-14">
            <el-tabs v-model="activeTab" @tab-click="handleClick">
                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver roles')" name="1">
                    <template #label>
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                            </svg>
                            <span>Roles</span>
                        </div>
                    </template>
                    <Roles :roles="roles" :permissions="permissions" />
                </el-tab-pane>
                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver permisos')" name="2">
                    <template #label>
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            <span>Permisos</span>
                        </div>
                    </template>
                    <Permissions :permissions="permissions" />
                </el-tab-pane>
                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver categorias')" name="3">
                    <template #label>
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25" />
                            </svg>
                            <span>Categorías</span>
                        </div>
                    </template>
                    <Categories />
                </el-tab-pane>
                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver caracteristicas')" name="4">
                    <template #label>
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3" />
                            </svg>
                            <span>Características</span>
                        </div>
                    </template>
                    <Features :features="features" />
                </el-tab-pane>
                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver departamentos')" name="5">
                    <template #label>
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                            <span>Departamentos</span>
                        </div>
                    </template>
                    <Departments :departments="departments" />
                </el-tab-pane>
                <el-tab-pane v-if="$page.props.auth.user.permissions.includes('Ver puestos')" name="6">
                    <template #label>
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span>Puestos</span>
                        </div>
                    </template>
                    <JobPositions :jobPositions="job_positions" />
                </el-tab-pane>
            </el-tabs>
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import Roles from './Tabs/Roles.vue';
import Permissions from './Tabs/Permissions.vue';
import Categories from './Tabs/Categories.vue';
import Features from './Tabs/Features.vue';
import Departments from './Tabs/Departments.vue';
import JobPositions from './Tabs/JobPositions.vue';

export default {
    data() {
        return {
            activeTab: '1',
        }
    },
    components: {
        AppLayout,
        Roles,
        Permissions,
        Categories,
        Features,
        Departments,
        JobPositions,
    },
    props: {
        roles: Array,
        permissions: Object,
        features: Array,
        departments: Array,
        job_positions: Array,
    },
    methods: {
        handleClick(tab) {
            // Agrega la variable currentTab=tab.props.name a la URL para mejorar la navegacion al actalizar o cambiar de pagina
            const currentURL = new URL(window.location.href);
            currentURL.searchParams.set('currentTab', tab.props.name);
            // Actualiza la URL
            window.history.replaceState({}, document.title, currentURL.href);
        }
    },
    mounted() {
        // Obtener la URL actual
        const currentURL = new URL(window.location.href);
        // Extraer el valor de 'currentTab' de los parámetros de búsqueda
        const currentTabFromURL = currentURL.searchParams.get('currentTab');

        if (currentTabFromURL) {
            this.activeTab = currentTabFromURL;
        }
    },
}
</script>
