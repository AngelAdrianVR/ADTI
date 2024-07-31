<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import SideNav from '@/Components/MyComponents/SideNav.vue';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="overflow-hidden h-screen md:flex bg-white">

            <!-- sidenav -->
            <aside class="col-span-2 w-auto">
                <SideNav />
            </aside>

            <!-- resto de pagina -->
            <main class="w-full">
                <nav class="bg-white border-b border-grayD9">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-12">
                            <div class="flex">
                                <!-- Logo -->
                                <div class="md:hidden shrink-0 flex items-center">
                                    <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-11 w-auto" />
                                    </Link>
                                </div>
                            </div>
                            <div class="hidden sm:flex sm:items-center sm:ms-6">

                                <!-- Settings Dropdown -->
                                <div class="ms-3 relative">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button v-if="$page.props.jetstream.managesProfilePhotos"
                                                class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                                <img class="h-8 w-8 rounded-full object-cover"
                                                    :src="$page.props.auth.user.profile_photo_url"
                                                    :alt="$page.props.auth.user.name">
                                            </button>

                                            <span v-else class="inline-flex items-center rounded-md">
                                                <button type="button"
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                    <svg width="10" height="13" viewBox="0 0 12 15" fill="none" class="mr-2" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M8.50033 3.50033C8.50033 4.16346 8.23691 4.79943 7.768 5.26834C7.2991 5.73724 6.66313 6.00067 6 6.00067C5.33687 6.00067 4.7009 5.73724 4.232 5.26834C3.76309 4.79943 3.49967 4.16346 3.49967 3.50033C3.49967 2.8372 3.76309 2.20123 4.232 1.73233C4.7009 1.26343 5.33687 1 6 1C6.66313 1 7.2991 1.26343 7.768 1.73233C8.23691 2.20123 8.50033 2.8372 8.50033 3.50033ZM1 12.9136C1.02143 11.6016 1.55763 10.3507 2.49298 9.43049C3.42833 8.51029 4.68788 7.99458 6 7.99458C7.31212 7.99458 8.57166 8.51029 9.50702 9.43049C10.4424 10.3507 10.9786 11.6016 11 12.9136C9.43138 13.6329 7.72566 14.0041 6 14.0017C4.21576 14.0017 2.5222 13.6123 1 12.9136Z" stroke="#6D6E72" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                    {{ $page.props.auth.user.name }}
                                                    <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                    </svg>
                                                </button>
                                            </span>
                                        </template>

                                        <template #content>
                                            <!-- Account Management -->
                                            <div class="block px-4 py-2 text-xs text-gray-400">
                                                Cuenta
                                            </div>

                                            <DropdownLink :href="route('profile.show')">
                                                Perfil
                                            </DropdownLink>

                                            <div class="border-t border-gray-200" />

                                            <!-- Authentication -->
                                            <form @submit.prevent="logout">
                                                <DropdownLink as="button">
                                                    Cerrar sesión
                                                </DropdownLink>
                                            </form>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>

                            <!-- Hamburger -->
                            <div class="-me-2 flex items-center sm:hidden">
                                <button
                                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                                    @click="showingNavigationDropdown = !showingNavigationDropdown">
                                    <svg class="size-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path
                                            :class="{ 'hidden': showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 6h16M4 12h16M4 18h16" />
                                        <path
                                            :class="{ 'hidden': !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Responsive Navigation Menu -->
                    <div :class="{ 'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown }"
                        class="sm:hidden bg-[#232323]">
                        <div class="pt-2 pb-3 space-y-px">
                            <!-- <ResponsiveNavLink :href="route('sales.point')" :active="route().current('sales.point')">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="-0.855 -0.855 24 24" height="16" width="16" id="Shopping-Basket-2--Streamline-Core"><desc>Shopping Basket 2 Streamline Icon: https://streamlinehq.com</desc><g id="shopping-basket-2--shopping-basket"><path id="Vector" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M19.625389714285713 7.932533357142858H2.6646102857142857C2.4237190714285712 7.927597714285714 2.184897642857143 7.976954142857143 1.9656595714285712 8.076940714285715C1.7464214999999998 8.176927285714285 1.5524984999999998 8.32499657142857 1.398219857142857 8.510162785714286C1.2439412142857142 8.695169785714285 1.1332872857142857 8.9126565 1.0745372142857144 9.146223857142857C1.0157871428571428 9.379950428571428 1.0102146428571428 9.623866714285715 1.0584565714285714 9.859822285714285L2.8252574999999998 18.693667714285713C2.900406642857143 19.061771142857143 3.1021311428571425 19.392140785714286 3.3957222857142857 19.626822642857142C3.689154214285714 19.861663714285715 4.0556655 19.986169285714286 4.431411214285714 19.978527H17.858588785714286C18.234493714285712 19.986169285714286 18.601005 19.861663714285715 18.894436928571427 19.626822642857142C19.187868857142856 19.392140785714286 19.38975257142857 19.061771142857143 19.4647425 18.693667714285713L21.231543428571428 9.859822285714285C21.279785357142856 9.623866714285715 21.274212857142857 9.379950428571428 21.215462785714287 9.146223857142857C21.156712714285714 8.9126565 21.046058785714283 8.695169785714285 20.891780142857144 8.510162785714286C20.7375015 8.32499657142857 20.5435785 8.176927285714285 20.32434042857143 8.076940714285715S19.866280928571427 7.927597714285714 19.625389714285713 7.932533357142858Z" stroke-width="1.71"></path><path id="Vector_2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M14.357307428571428 2.3111545714285713L17.569614857142856 7.932533357142858" stroke-width="1.71"></path><path id="Vector_3" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M4.720385142857142 7.932533357142858L7.932692571428571 2.3111545714285713" stroke-width="1.71"></path></g></svg>
                                <span>Punto de venta</span>
                            </ResponsiveNavLink>
¿                            <ResponsiveNavLink :href="route('settings.index')" :active="route().current('settings.*')">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                <span>Configuraciones</span>
                            </ResponsiveNavLink> -->
                        </div>

                        <!-- Responsive Settings Options -->
                        <div class="pt-4 pb-1 border-t border-gray-200">
                            <div class="flex items-center px-4">
                                <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                    <img class="h-10 w-10 rounded-full object-cover"
                                        :src="$page.props.auth.user.profile_photo_url"
                                        :alt="$page.props.auth.user.name">
                                </div>

                                <div>
                                    <div class="font-medium text-base text-white">
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div class="font-medium text-sm text-gray-500">
                                        {{ $page.props.auth.user.email }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 space-y-1">
                                <ResponsiveNavLink :href="route('profile.show')"
                                    :active="route().current('profile.show')">
                                    Perfil
                                </ResponsiveNavLink>

                                <!-- Authentication -->
                                <form method="POST" @submit.prevent="logout">
                                    <ResponsiveNavLink as="button">
                                        <i class="fa-solid fa-arrow-right-from-bracket text-xs text-red-600 rotate-180"></i>
                                        <span class="text-red-600">Cerrar sesión</span>
                                    </ResponsiveNavLink>
                                </form>

                            </div>
                        </div>
                    </div>
                </nav>

                <div class="overflow-y-auto h-[calc(100vh-3rem)] bg-white">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
