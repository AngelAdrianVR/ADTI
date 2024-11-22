<template>
    <AppLayout title="Detalles usuario">
        <header class="lg:px-9 px-1 mt-3">
            <h1 class="font-bold text-base mt-2">Detalles del usuario</h1>
            <section class="md:flex items-center justify-between mt-2">
                <!-- buscador -->
                <el-select @change="$inertia.get(route('users.show', selectedItem))" v-model="selectedItem"
                    class="w-full lg:!w-1/4 mt-2" placeholder="Buscar usuario" filterable
                    no-data-text="No hay más usuarios registrados" no-match-text="No se encontraron coincidencias">
                    <el-option v-for="item in users" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
                <div class="flex items-center space-x-2 mt-3 md:mt-0">
                    <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                        @click="$inertia.get(route('users.edit', user.id))" :disabled="loading">Editar</PrimaryButton>
                    <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Resetear contraseñas')"
                        confirm-button-text="Si" cancel-button-text="No" icon-color="#6D6E72"
                        title="La contraseña del usuario será reseteada a '123456' ¿Desea continuar?" @confirm="resetPassword()">
                        <template #reference>
                            <SecondaryButton class="!rounded-full" :disabled="loading">
                                Resetear contraseña
                            </SecondaryButton>
                        </template>
                    </el-popconfirm>
                    <el-tooltip content="Crear nuevo usuario" placement="top">
                        <SecondaryButton @click="$inertia.get(route('users.create'))" class="!rounded-full !p-2"
                            :disabled="loading"><i class="fa-solid fa-plus text-xs size-4"></i></SecondaryButton>
                    </el-tooltip>
                </div>
            </section>
        </header>
        <section class="relative mt-10">
            <div class="bg-[#0B3B51] h-44 pt-px">
                <button @click="$inertia.get(route('users.index'))"
                    class="flex justify-center items-center rounded-full size-5 focus:outline-none bg-grayED text-primary ml-3 mt-3">
                    <i class="fa-solid fa-angle-left text-xs"></i>
                </button>
            </div>
            <svg class="hidden lg:block absolute top-1 left-12" width="533" height="169" viewBox="0 0 533 169" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path d="M41.0025 168.5C-115 -99.5 212.506 253.5 532.003 1" stroke="#D9D9D9" />
            </svg>
            <svg class="absolute top-[75px] right-12" width="426" height="102" viewBox="0 0 426 102" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path d="M335 101.001C587.5 -66.501 258 7.5 0.5 101.001" stroke="#D9D9D9" />
            </svg>
            <figure
                class="size-32 lg:size-40 rounded-[5px] bg-gray-500 absolute top-20 left-[calc(50%-5rem)] shadow-lg">
                <img :src="user.profile_photo_url" :alt="user.name"
                    class="size-32 lg:size-40 object-cover rounded-[5px]">
            </figure>
            <h1 class="font-bold text-center mt-20">{{ user.name }}</h1>
        </section>
        <!-- Tabs -->
        <el-tabs v-model="activeTab" class="mx-5" @tab-click="handleClickInTab">
            <el-tab-pane name="1">
                <template #label>
                    <div class="flex items-center space-x-2">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"
                            class="size-4">
                            <mask id="mask0_14787_313" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0"
                                width="14" height="14">
                                <rect width="14" height="14" fill="currentColor" />
                            </mask>
                            <g mask="url(#mask0_14787_313)">
                                <path
                                    d="M2.91667 12.2507C2.59583 12.2507 2.32118 12.1364 2.09271 11.9079C1.86424 11.6795 1.75 11.4048 1.75 11.084V2.91732C1.75 2.59648 1.86424 2.32183 2.09271 2.09336C2.32118 1.86489 2.59583 1.75065 2.91667 1.75065H5.36667C5.50278 1.40065 5.71667 1.11871 6.00833 0.904818C6.3 0.690929 6.63056 0.583984 7 0.583984C7.36944 0.583984 7.7 0.690929 7.99167 0.904818C8.28333 1.11871 8.49722 1.40065 8.63333 1.75065H11.0833C11.4042 1.75065 11.6788 1.86489 11.9073 2.09336C12.1358 2.32183 12.25 2.59648 12.25 2.91732V11.084C12.25 11.4048 12.1358 11.6795 11.9073 11.9079C11.6788 12.1364 11.4042 12.2507 11.0833 12.2507H2.91667ZM7 2.47982C7.12639 2.47982 7.2309 2.4385 7.31354 2.35586C7.39618 2.27322 7.4375 2.16871 7.4375 2.04232C7.4375 1.91593 7.39618 1.81141 7.31354 1.72878C7.2309 1.64614 7.12639 1.60482 7 1.60482C6.87361 1.60482 6.7691 1.64614 6.68646 1.72878C6.60382 1.81141 6.5625 1.91593 6.5625 2.04232C6.5625 2.16871 6.60382 2.27322 6.68646 2.35586C6.7691 2.4385 6.87361 2.47982 7 2.47982ZM2.91667 10.4132C3.44167 9.89787 4.05174 9.49197 4.74688 9.19544C5.44201 8.89892 6.19306 8.75065 7 8.75065C7.80694 8.75065 8.55799 8.89892 9.25312 9.19544C9.94826 9.49197 10.5583 9.89787 11.0833 10.4132V2.91732H2.91667V10.4132ZM7 7.58398C7.56389 7.58398 8.04514 7.38468 8.44375 6.98607C8.84236 6.58746 9.04167 6.10621 9.04167 5.54232C9.04167 4.97843 8.84236 4.49718 8.44375 4.09857C8.04514 3.69996 7.56389 3.50065 7 3.50065C6.43611 3.50065 5.95486 3.69996 5.55625 4.09857C5.15764 4.49718 4.95833 4.97843 4.95833 5.54232C4.95833 6.10621 5.15764 6.58746 5.55625 6.98607C5.95486 7.38468 6.43611 7.58398 7 7.58398ZM4.08333 11.084H9.91667V10.9382C9.50833 10.5979 9.05625 10.3427 8.56042 10.1725C8.06458 10.0024 7.54444 9.91732 7 9.91732C6.45556 9.91732 5.93542 10.0024 5.43958 10.1725C4.94375 10.3427 4.49167 10.5979 4.08333 10.9382V11.084ZM7 6.41732C6.75694 6.41732 6.55035 6.33225 6.38021 6.16211C6.21007 5.99197 6.125 5.78537 6.125 5.54232C6.125 5.29926 6.21007 5.09267 6.38021 4.92253C6.55035 4.75239 6.75694 4.66732 7 4.66732C7.24306 4.66732 7.44965 4.75239 7.61979 4.92253C7.78993 5.09267 7.875 5.29926 7.875 5.54232C7.875 5.78537 7.78993 5.99197 7.61979 6.16211C7.44965 6.33225 7.24306 6.41732 7 6.41732Z"
                                    fill="currentColor" />
                            </g>
                        </svg>
                        <p>Información general</p>
                    </div>
                </template>
                <General :user="user" :vacations="vacations" />
            </el-tab-pane>
            <el-tab-pane name="2">
                <template #label>
                    <div class="flex items-center space-x-2">
                        <svg class="size-4" width="14" height="14" viewBox="0 0 14 14" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <mask id="mask0_14787_310" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0"
                                width="14" height="14">
                                <rect width="14" height="14" fill="currentColor" />
                            </mask>
                            <g mask="url(#mask0_14787_310)">
                                <path
                                    d="M6.18333 9.36315L10.2958 5.25065L9.47917 4.43398L6.18333 7.72982L4.52083 6.06732L3.70417 6.88399L6.18333 9.36315ZM2.91667 12.2507C2.59583 12.2507 2.32118 12.1364 2.09271 11.9079C1.86424 11.6795 1.75 11.4048 1.75 11.084V2.91732C1.75 2.59648 1.86424 2.32183 2.09271 2.09336C2.32118 1.86489 2.59583 1.75065 2.91667 1.75065H5.36667C5.49306 1.40065 5.70451 1.11871 6.00104 0.904818C6.29757 0.690929 6.63056 0.583984 7 0.583984C7.36944 0.583984 7.70243 0.690929 7.99896 0.904818C8.29549 1.11871 8.50694 1.40065 8.63333 1.75065H11.0833C11.4042 1.75065 11.6788 1.86489 11.9073 2.09336C12.1358 2.32183 12.25 2.59648 12.25 2.91732V11.084C12.25 11.4048 12.1358 11.6795 11.9073 11.9079C11.6788 12.1364 11.4042 12.2507 11.0833 12.2507H2.91667ZM2.91667 11.084H11.0833V2.91732H2.91667V11.084ZM7 2.47982C7.12639 2.47982 7.2309 2.4385 7.31354 2.35586C7.39618 2.27322 7.4375 2.16871 7.4375 2.04232C7.4375 1.91593 7.39618 1.81141 7.31354 1.72878C7.2309 1.64614 7.12639 1.60482 7 1.60482C6.87361 1.60482 6.7691 1.64614 6.68646 1.72878C6.60382 1.81141 6.5625 1.91593 6.5625 2.04232C6.5625 2.16871 6.60382 2.27322 6.68646 2.35586C6.7691 2.4385 6.87361 2.47982 7 2.47982Z"
                                    fill="currentColor" />
                            </g>
                        </svg>
                        <p>Expediente digital</p>
                    </div>
                </template>
                <DigitalDocuments :user="user" />
            </el-tab-pane>
        </el-tabs>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Back from "@/Components/MyComponents/Back.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import axios from "axios";
import General from "./Tabs/General.vue";
import DigitalDocuments from "./Tabs/DigitalDocuments.vue";

export default {
    data() {
        return {
            loading: false,
            selectedItem: this.user.id,
            activeTab: '1',
        }
    },
    components: {
        AppLayout,
        PrimaryButton,
        Back,
        SecondaryButton,
        General,
        DigitalDocuments,
    },
    props: {
        user: Object,
        users: Array,
        vacations: Array,
    },
    methods: {
        handleClickInTab(tab) {
            // Agrega la variable currentTab=tab.props.name a la URL para mejorar la navegacion al actalizar o cambiar de pagina
            const currentURL = new URL(window.location.href);
            currentURL.searchParams.set('currentTab', tab.props.name);
            // Actualiza la URL
            window.history.replaceState({}, document.title, currentURL.href);
        },
        setTabFromUrl() {
            // Obtener la URL actual
            const currentURL = new URL(window.location.href);
            // Extraer el valor de 'currentTab' de los parámetros de búsqueda
            const currentTabFromURL = currentURL.searchParams.get('currentTab');

            if (currentTabFromURL) {
                this.activeTab = currentTabFromURL;
            }
        },
        async resetPassword() {
            try {
                this.loading = true;
                const response = await axios.put(route('users.reset-password', this.user.id));

                if (response.status === 200) {
                    this.$notify({
                        title: "Correcto",
                        message: "Se ha reseteado la contraseña a 123456",
                        type: "success",
                    });
                }
            } catch (error) {
                console.log(error)
            } finally {
                this.loading = false;
            }
        },
    },
    mounted() {
        this.setTabFromUrl();
    }
}
</script>