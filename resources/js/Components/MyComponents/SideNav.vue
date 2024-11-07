<template>
    <!-- sidebar -->
    <div class="h-screen hidden md:block shadow-lg relative">
        <i @click="updateSideNavSize(false)" v-if="small"
            class="fa-solid fa-angle-right text-center text-xs pt-[2px] text-white rounded-full size-5 bg-primary absolute top-24 -right-3 cursor-pointer hover:scale-125 transition-transform ease-linear duration-150 z-10"></i>
        <i @click="updateSideNavSize(true)" v-else
            class="fa-solid fa-angle-left text-center text-xs pt-[2px] text-white rounded-full size-5 bg-primary absolute top-24 -right-3 cursor-pointer hover:scale-125 transition-transform ease-linear duration-150 z-10"></i>
        <div class="bg-[#dbdbdb] h-full overflow-auto">
            <!-- Logo -->
            <div class="flex items-center justify-center mt-7">
                <Link v-if="small" :href="route('dashboard')">
                <figure>
                    <img class="w-16 px-2 mb-[52px]" src="@/../../public/images/isologo.png" alt="logo">
                </figure>
                </Link>
                <Link v-else :href="route('dashboard')">
                <figure>
                    <img class="w-32 px-2 mb-[49px]" src="@/../../public/images/logo_colors.webp" alt="logo">
                </figure>
                </Link>
            </div>

            <nav class="pr-2 text-white">
                <!-- Con barra pequeña -->
                <section v-if="small">
                    <div v-for="(menu, index) in menus" :key="index">
                        <SideNavLink v-if="menu.show" :href="menu.route" :active="menu.active" :dropdown="menu.dropdown"
                            class="mb-px">
                            <template #trigger>
                                <button v-if="menu.show" :active="menu.active" :title="menu.label"
                                    class="w-full pl-2 rounded-lg my-[5px] size-10 transition ease-linear duration-200">
                                    <p :class="menu.active ? 'bg-[#c5c5c5] text-primary' : 'hover:text-primary hover:bg-[#c5c5c5] text-gray-700'"
                                        class="rounded-lg size-10 flex items-center justify-center" v-html="menu.icon"></p>
                                </button>
                                <i v-if="menu.notifications" class="fa-solid fa-circle fa-flip text-primary text-[10px] absolute bottom-7 right-1"></i>
                            </template>
                            <template #content>
                                <template v-for="option in menu.options" :key="option">
                                    <DropdownNavLink v-if="option.show" :href="option.route" :notifications="option.notifications" :active="option.active">
                                        {{ option.label }}
                                    </DropdownNavLink>
                                </template>
                            </template>
                        </SideNavLink>
                    </div>
                </section>

                <!-- Con barra grande -->
                <section v-else v-for="(menu, index) in menus" :key="index">
                    <!-- Con submenues -->
                    <div v-if="menu.show">
                        <Accordion v-if="menu.options.length" :icon="menu.icon" :active="menu.active" :title="menu.label" :id="index">
                            <!-- Opciones del menu -->
                            <template #content>
                                <div class="relative" v-for="(option, index2) in menu.options" :key="index2">
                                    <Link :href="option.route" class="flex items-center">
                                        <button v-if="option.show" :active="option.active" :title="option.label"
                                            class="w-full pl-2 ml-8 rounded-lg size-7 mb-1 transition ease-linear duration-200 text-left text-xs"
                                            :class="option.active ? 'bg-[#c5c5c5] text-primary' : 'hover:text-primary hover:bg-[#c5c5c5] text-gray-700'">
                                            <p class="truncate">{{ option.label }}</p>
                                        </button>
                                    
                                        <!-- Adorno lateral de subcategorias-->
                                        <i v-if="option.active" class="absolute left-[13px] fa-solid fa-circle text-[7px] z-10 bg-[#dbdbdb] text-gray-700 p-1"></i>
                                        <div class="border-l border-[#999999] absolute left-5 h-full"></div>
                                    </Link>

                                </div>
                            </template>
                        </Accordion>
                        
                        <!-- Sin submenues -->
                        <button v-else-if="menu.show" :active="menu.active"
                            :title="menu.label"
                            class="w-full pl-2 rounded-full my-1 size-10 transition ease-linear duration-200 relative">
                            <Link :href="menu.route" class="flex items-center rounded-lg"
                                :class="menu.active ? 'bg-[#c5c5c5] text-primary' : 'hover:text-primary hover:bg-[#c5c5c5] text-gray-700'">
                                <p class="rounded-lg size-10 flex items-center justify-center" v-html="menu.icon"></p>
                                <span class="font-bold text-sm mr-2">{{ menu.label }}</span>
                            </Link>
                            <i v-if="menu.notifications" class="fa-solid fa-circle fa-flip text-primary text-[10px] absolute bottom-7 right-1"></i>
                        </button>
                    </div>
                </section>
            </nav>
        </div>
    </div>
</template>

<script>
import Accordion from './Accordion.vue';
import SideNavLink from "@/Components/MyComponents/SideNavLink.vue";
import DropdownNavLink from "@/Components/MyComponents/DropdownNavLink.vue";
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link } from '@inertiajs/vue3';

export default {
    data() {
        return {
            small: true,
            collapsedMenu: null,
            routeToGo: null,
            menus: [
                {
                    label: 'Página principal',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>',
                    route: route('welcome'),
                    active: route().current('welcome'),
                    options: [],
                    dropdown: false,
                    show: true
                },
                {
                    label: 'Panel de inicio',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" /></svg>',
                    route: route('dashboard'),
                    active: route().current('dashboard'),
                    options: [],
                    dropdown: false,
                    show: true
                },
                {
                    label: 'Productos',
                    icon: '<svg width="20" height="20" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.25 8.38583C2.25 10.0434 2.90848 11.6331 4.08058 12.8053C5.25268 13.9774 6.8424 14.6358 8.5 14.6358C10.1576 14.6358 11.7473 13.9774 12.9194 12.8053C14.0915 11.6331 14.75 10.0434 14.75 8.38583M2.25 8.38583C2.25 6.72823 2.90848 5.13852 4.08058 3.96642C5.25268 2.79431 6.8424 2.13583 8.5 2.13583C10.1576 2.13583 11.7473 2.79431 12.9194 3.96642C14.0915 5.13852 14.75 6.72823 14.75 8.38583M2.25 8.38583H1M14.75 8.38583H16M14.75 8.38583H8.5L4.75 1.89M1.4525 10.95L2.6275 10.5225M14.3733 6.2475L15.5483 5.82M2.755 13.2067L3.71333 12.4033M13.2883 4.36833L14.2458 3.565M4.75083 14.8817L5.37583 13.7983L8.50167 8.38583M11.6258 2.97333L12.2508 1.89M7.19833 15.7717L7.415 14.5408M9.58583 2.23083L9.8025 1M9.8025 15.7717L9.58583 14.5408M7.415 2.23083L7.19833 1M12.25 14.8808L11.625 13.7983M14.245 13.2067L13.2875 12.4033M3.71333 4.3675L2.755 3.56417M15.5483 10.9508L14.3733 10.5233M2.62833 6.24833L1.45333 5.82" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                    route: route('products.index'),
                    active: route().current('products.*'),
                    options: [],
                    dropdown: false,
                    show: true
                },
                {
                    label: 'RRHH',
                    icon: '<svg width="20" height="20" viewBox="0 0 18 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.6258 14.44C12.3366 14.6464 13.0732 14.7508 13.8133 14.75C15.0037 14.7517 16.1785 14.4803 17.2475 13.9567C17.2791 13.2098 17.0664 12.4729 16.6415 11.8578C16.2166 11.2427 15.6028 10.7828 14.8931 10.5479C14.1834 10.313 13.4164 10.3159 12.7085 10.5562C12.0005 10.7964 11.3902 11.2609 10.97 11.8792M11.6258 14.44V14.4375C11.6258 13.51 11.3875 12.6375 10.97 11.8792M11.6258 14.44V14.5283C10.0221 15.4942 8.18468 16.0032 6.3125 16C4.37 16 2.5525 15.4625 1.00083 14.5283L1 14.4375C0.999361 13.2579 1.39134 12.1116 2.11415 11.1794C2.83696 10.2472 3.84948 9.58203 4.99207 9.28883C6.13467 8.99564 7.34235 9.09107 8.42472 9.56008C9.50709 10.0291 10.4026 10.845 10.97 11.8792M9.12583 3.8125C9.12583 4.55842 8.82952 5.27379 8.30207 5.80124C7.77463 6.32868 7.05926 6.625 6.31333 6.625C5.56741 6.625 4.85204 6.32868 4.3246 5.80124C3.79715 5.27379 3.50083 4.55842 3.50083 3.8125C3.50083 3.06658 3.79715 2.35121 4.3246 1.82376C4.85204 1.29632 5.56741 1 6.31333 1C7.05926 1 7.77463 1.29632 8.30207 1.82376C8.82952 2.35121 9.12583 3.06658 9.12583 3.8125ZM16.0008 5.6875C16.0008 6.26766 15.7704 6.82406 15.3601 7.2343C14.9499 7.64453 14.3935 7.875 13.8133 7.875C13.2332 7.875 12.6768 7.64453 12.2665 7.2343C11.8563 6.82406 11.6258 6.26766 11.6258 5.6875C11.6258 5.10734 11.8563 4.55094 12.2665 4.1407C12.6768 3.73047 13.2332 3.5 13.8133 3.5C14.3935 3.5 14.9499 3.73047 15.3601 4.1407C15.7704 4.55094 16.0008 5.10734 16.0008 5.6875Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                    active: route().current('users.*'),
                    options: [
                        {
                            label: 'Usuarios',
                            route: route('users.index'),
                            show: true,
                            active: route().current('users.*'),
                            notifications: false,
                        },
                        {
                            label: 'Incidencias',
                            route: route('login'),
                            show: true,
                            active: route().current('dasboard'),
                            notifications: false,
                        },
                        {
                            label: 'Días festivos',
                            route: route('login'),
                            show: true,
                            active: route().current('dasboard'),
                            notifications: false,
                        },
                        {
                            label: 'Bonos',
                            route: route('login'),
                            show: true,
                            active: route().current('dasboard'),
                            notifications: false,
                        },
                    ],
                    dropdown: true,
                    show: true
                },
                {
                    label: 'Configuraciones',
                    icon: '<svg width="20" height="20" viewBox="0 0 16 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.625 2.25H14.75M6.625 2.25C6.625 2.58152 6.4933 2.89946 6.25888 3.13388C6.02446 3.3683 5.70652 3.5 5.375 3.5C5.04348 3.5 4.72554 3.3683 4.49112 3.13388C4.2567 2.89946 4.125 2.58152 4.125 2.25M6.625 2.25C6.625 1.91848 6.4933 1.60054 6.25888 1.36612C6.02446 1.1317 5.70652 1 5.375 1C5.04348 1 4.72554 1.1317 4.49112 1.36612C4.2567 1.60054 4.125 1.91848 4.125 2.25M4.125 2.25H1M6.625 12.25H14.75M6.625 12.25C6.625 12.5815 6.4933 12.8995 6.25888 13.1339C6.02446 13.3683 5.70652 13.5 5.375 13.5C5.04348 13.5 4.72554 13.3683 4.49112 13.1339C4.2567 12.8995 4.125 12.5815 4.125 12.25M6.625 12.25C6.625 11.9185 6.4933 11.6005 6.25888 11.3661C6.02446 11.1317 5.70652 11 5.375 11C5.04348 11 4.72554 11.1317 4.49112 11.3661C4.2567 11.6005 4.125 11.9185 4.125 12.25M4.125 12.25H1M11.625 7.25H14.75M11.625 7.25C11.625 7.58152 11.4933 7.89946 11.2589 8.13388C11.0245 8.3683 10.7065 8.5 10.375 8.5C10.0435 8.5 9.72554 8.3683 9.49112 8.13388C9.2567 7.89946 9.125 7.58152 9.125 7.25M11.625 7.25C11.625 6.91848 11.4933 6.60054 11.2589 6.36612C11.0245 6.1317 10.7065 6 10.375 6C10.0435 6 9.72554 6.1317 9.49112 6.36612C9.2567 6.60054 9.125 6.91848 9.125 7.25M9.125 7.25H1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                    route: route('settings.index'),
                    active: route().current('settings.*') || route().current('categories.*'),
                    options: [],
                    dropdown: false,
                    show: true
                },

                //ejemplo para usar submenues
                //     label: 'Comunidad',
                //     icon: '<i class="fa-solid fa-people-roof text-sm mr-2"></i>',
                //     // route: route('posts.index'),
                //     active: route().current('posts.*') || route().current('community-events.*')|| route().current('neighbors.*'),
                //     options: [
                //         {
                //             label: 'Muro de noticias',
                //             route: route('posts.index'),
                //             show: true,
                //         },
                //         {
                //             label: 'Eventos',
                //             route: route('community-events.index'),
                //             show: true,
                //         },
                //         {
                //             label: 'Directorio de vecinos',
                //             route: route('neighbors.index'),
                //             show: true,
                //         },
                //     ],
                //     dropdown: true,
                //     show: true
                // },
            ],
        }
    },
    components: {
        DropdownNavLink,
        DropdownLink,
        SideNavLink,
        Accordion,
        Link,
    },
    methods: {
        updateSideNavSize(is_small){
            this.small = is_small;
            localStorage.setItem('is_sidenav_small', is_small);
        }
    },
    mounted() {
        const is_small = localStorage.getItem('is_sidenav_small');
        if (is_small !== null) {
            this.small = JSON.parse(is_small); // Convertirlo a booleano si es necesario
        }
    }
}
</script>