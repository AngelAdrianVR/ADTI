<template>
    <AppLayout title="Configuraciones">
        <header class="mb-6 mt-4 px-2 lg:px-14">
            <h1 class="text-lg font-bold">Configuraciones</h1>
        </header>
        <main class="px-2 lg:px-14">
            <el-tabs v-model="activeTab" @tab-click="handleClick">
                <el-tab-pane name="1">
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
                <el-tab-pane name="2">
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
                <el-tab-pane name="3">
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
            </el-tabs>
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import Roles from './Tabs/Roles.vue';
import Permissions from './Tabs/Permissions.vue';
import Categories from './Tabs/Categories.vue';

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
    },
    props: {
        roles: Array,
        permissions: Array,
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
