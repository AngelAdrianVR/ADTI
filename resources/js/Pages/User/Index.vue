<template>
    <AppLayout title="Usuarios">
        <main class="px-2 lg:px-10 py-2">
            <section class="md:flex justify-between items-center">
                <h1 class="font-bold my-3 ml-4 text-lg">Usuarios</h1>
                <!-- <article class="flex items-center space-x-5 lg:w-1/3">
                    <div class="relative mb-3 md:mb-0 w-full">
                        <input v-model="searchQuery" @keydown.enter="handleSearch" class="input w-full pl-9"
                            placeholder="Buscar por nombre, puesto, correo o teléfono" type="search" ref="searchInput" />
                        <i class="fa-solid fa-magnifying-glass text-xs text-gray99 absolute top-[10px] left-4"></i>
                    </div>
                    <el-tag @close="closedTag" v-if="searchedWord" closable type="primary">
                        {{ searchedWord }}
                    </el-tag>
                </article> -->
                <!-- buttons -->
                <div class="flex items-center space-x-1">
                    <div class="my-4 lg:my-0 flex items-center justify-end space-x-3">
                        <PrimaryButton v-if="$page.props.auth.user.permissions?.includes('Crear usuarios')"
                            @click="$inertia.get(route('users.create'))">Crear usuario</PrimaryButton>
                    </div>
                </div>
            </section>

            <!-- Tabs -->
            <el-tabs v-model="activeTab" class="mx-5" @tab-click="handleClick">
                <el-tab-pane label="Ingresos" name="1">
                    <template #label>
                        <div class="flex items-center space-x-2">
                            <svg class="size-4" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <mask id="mask0_14784_96" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="14" height="14">
                                    <rect width="14" height="14" fill="#D9D9D9"/>
                                </mask>
                                <g mask="url(#mask0_14784_96)">
                                    <path d="M10.2401 7.00065L8.16927 4.92982L9.00052 4.11315L10.2401 5.35273L12.7193 2.87357L13.5359 3.70482L10.2401 7.00065ZM5.2526 7.00065C4.61094 7.00065 4.06163 6.77218 3.60469 6.31523C3.14774 5.85829 2.91927 5.30898 2.91927 4.66732C2.91927 4.02565 3.14774 3.47635 3.60469 3.0194C4.06163 2.56246 4.61094 2.33398 5.2526 2.33398C5.89427 2.33398 6.44358 2.56246 6.90052 3.0194C7.35747 3.47635 7.58594 4.02565 7.58594 4.66732C7.58594 5.30898 7.35747 5.85829 6.90052 6.31523C6.44358 6.77218 5.89427 7.00065 5.2526 7.00065ZM0.585938 11.6673V10.034C0.585938 9.70343 0.671007 9.39961 0.841146 9.12253C1.01128 8.84544 1.23733 8.63398 1.51927 8.48815C2.12205 8.18676 2.73455 7.96072 3.35677 7.81003C3.97899 7.65933 4.61094 7.58398 5.2526 7.58398C5.89427 7.58398 6.52622 7.65933 7.14844 7.81003C7.77066 7.96072 8.38316 8.18676 8.98594 8.48815C9.26788 8.63398 9.49392 8.84544 9.66406 9.12253C9.8342 9.39961 9.91927 9.70343 9.91927 10.034V11.6673H0.585938ZM1.7526 10.5007H8.7526V10.034C8.7526 9.92704 8.72587 9.82982 8.6724 9.74232C8.61892 9.65482 8.54844 9.58676 8.46094 9.53815C7.93594 9.27565 7.40608 9.07878 6.87136 8.94753C6.33663 8.81628 5.79705 8.75065 5.2526 8.75065C4.70816 8.75065 4.16858 8.81628 3.63385 8.94753C3.09913 9.07878 2.56927 9.27565 2.04427 9.53815C1.95677 9.58676 1.88628 9.65482 1.83281 9.74232C1.77934 9.82982 1.7526 9.92704 1.7526 10.034V10.5007ZM5.2526 5.83398C5.57344 5.83398 5.84809 5.71975 6.07656 5.49128C6.30504 5.2628 6.41927 4.98815 6.41927 4.66732C6.41927 4.34648 6.30504 4.07183 6.07656 3.84336C5.84809 3.61489 5.57344 3.50065 5.2526 3.50065C4.93177 3.50065 4.65712 3.61489 4.42865 3.84336C4.20017 4.07183 4.08594 4.34648 4.08594 4.66732C4.08594 4.98815 4.20017 5.2628 4.42865 5.49128C4.65712 5.71975 4.93177 5.83398 5.2526 5.83398Z" fill="currentColor"/>
                                </g>
                            </svg>
                            <p>Activos</p>
                        </div>
                    </template>
                    <ActiveUsers :users="users.filter(user => user.is_active)" />
                </el-tab-pane>
                <el-tab-pane label="Ingresos recurrentes" name="2">
                    <template #label>
                        <div class="flex items-center space-x-2">
                            <svg class="size-4" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <mask id="mask0_14810_295" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="14" height="14">
                                    <rect width="14" height="14" fill="#D9D9D9"/>
                                </mask>
                                <g mask="url(#mask0_14810_295)">
                                    <path d="M11.538 13.1974L10.0068 11.6661H2.33594V10.0328C2.33594 9.70226 2.42101 9.39844 2.59115 9.12135C2.76128 8.84427 2.98733 8.63281 3.26927 8.48698C3.70677 8.26337 4.15156 8.08351 4.60365 7.9474C5.05573 7.81128 5.5151 7.7092 5.98177 7.64115L0.804688 2.46406L1.63594 1.63281L12.3693 12.3661L11.538 13.1974ZM3.5026 10.4995H8.8401L7.0901 8.74948H7.0026C6.45816 8.74948 5.91858 8.8151 5.38385 8.94635C4.84913 9.0776 4.31927 9.27448 3.79427 9.53698C3.70677 9.58559 3.63628 9.65365 3.58281 9.74115C3.52934 9.82865 3.5026 9.92587 3.5026 10.0328V10.4995ZM10.7359 8.48698C11.0179 8.62309 11.2415 8.82969 11.4068 9.10677C11.572 9.38385 11.6595 9.68281 11.6693 10.0036L9.7151 8.04948C9.8901 8.11753 10.0627 8.18559 10.2328 8.25365C10.403 8.3217 10.5707 8.39948 10.7359 8.48698ZM8.28594 6.62031L7.42552 5.7599C7.64913 5.6724 7.82899 5.52899 7.9651 5.32969C8.10122 5.13038 8.16927 4.9092 8.16927 4.66615C8.16927 4.34531 8.05503 4.07066 7.82656 3.84219C7.59809 3.61372 7.32344 3.49948 7.0026 3.49948C6.75955 3.49948 6.53837 3.56753 6.33906 3.70365C6.13976 3.83976 5.99635 4.01962 5.90885 4.24323L5.04844 3.38281C5.27205 3.05226 5.55399 2.79462 5.89427 2.6099C6.23455 2.42517 6.60399 2.33281 7.0026 2.33281C7.64427 2.33281 8.19358 2.56128 8.65052 3.01823C9.10746 3.47517 9.33594 4.02448 9.33594 4.66615C9.33594 5.06476 9.24358 5.4342 9.05885 5.77448C8.87413 6.11476 8.61649 6.3967 8.28594 6.62031Z" fill="currentColor"/>
                                </g>
                            </svg>
                            <p>Inactivos</p>
                        </div>
                    </template>
                    <DisabledUsers :users="users.filter(user => !user.is_active)" />
                </el-tab-pane>
            </el-tabs>
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import ActiveUsers from './Tabs/ActiveUsers.vue';
import DisabledUsers from './Tabs/DisabledUsers.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

export default {
    data() {
        return {
            // buscador
            // search: '',
            // searchQuery: null,
            // searchedWord: null,
            // Tabs
            activeTab: '1',
            // pagination
            itemsPerPage: 10,
            start: 0,
            end: 10,
        }
    },
    components: {
        AppLayout,
        ActiveUsers,
        DisabledUsers,
        PrimaryButton,
    },
    props: {
        users: Array
    },
    methods: {
        // handleSearch() {
        //     this.search = this.searchQuery;
        //     this.searchedWord = this.searchQuery;
        //     this.searchQuery = null;
        // },
        handleClick(tab) {
            // Obtén la URL actual
            const currentURL = new URL(window.location.href);

            // Agrega la variable currentTab=tab.props.name a la URL
            currentURL.searchParams.set('currentTab', tab.props.name);

            //revisa si existe una variable de paginacion
            const paginationURL = currentURL.searchParams.get('page');

            // Elimina el parámetro de paginación "page"
            currentURL.searchParams.delete('page');

            // Actualiza la URL sin recargar la página
            window.history.replaceState({}, document.title, currentURL.href);

            //actualiza la pagina si se cambio paginacion en alguna pestaña para no afectar la otra
            if ( paginationURL ) {
                location.reload();
            }

            // Cierra las búsquedas de la otra pestaña
            // this.closedTag();
        },
        // closedTag() {
        //     this.search = null
        //     this.searchedWord = null;
        // },
        inputFocus() {
            this.$nextTick(() => {
                this.$refs.searchInput.focus();
            });
        },
    },
    // computed: {
    //     filteredTableData() {
    //         if (!this.search) {
    //             // return this.users.filter((item, index) => index >= this.start && index < this.end);
    //             return this.users;
    //         } else {
    //             return this.users.filter(
    //                 (user) =>
    //                     user.name?.toLowerCase().includes(this.search.toLowerCase()) ||
    //                     user.email?.toLowerCase().includes(this.search.toLowerCase()) ||
    //                     user.phone?.toLowerCase().includes(this.search.toLowerCase()) ||
    //                     user.org_props?.position?.toLowerCase().includes(this.search.toLowerCase())
    //             )
    //         }
    //     }
    // },
    mounted() {
        this.inputFocus();
        // Obtener la URL actual
        const currentURL = new URL(window.location.href);
        // Extraer el valor de 'currentTab' de los parámetros de búsqueda
        const currentTabFromURL = currentURL.searchParams.get('currentTab');

        if (currentTabFromURL) {
            this.activeTab = currentTabFromURL;
        }
    }
}
</script>
