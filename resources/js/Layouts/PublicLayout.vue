<template>
    <div>
        <Head :title="title" />

        <div class="overflow-hidden h-screen bg-white">
            <!-- resto de pagina -->
            <main class="w-full">
                <nav class="bg-white border-b border-gray-200">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-20 space-x-4 borde items-center">
                            <!-- Logo -->
                            <Link :href="'/'">
                                <ApplicationMark class="md:w-32 w-20" />
                            </Link>

                            <!-- buscador de productos -->
                            <div class="relative md:w-96 w-64">
                                <input v-model="searchQuery" @focus="searchFocus = true" @blur="handleBlur"
                                    @input="searchProducts" ref="searchInput" class="input w-full pl-9 md:pr-16"
                                    placeholder="Buscar por nombre o número de parte" type="search">
                                <PrimaryButton :disabled="!searchQuery" class="!py-[7px] absolute top-[2px] right-[2px]" @click="searchProducts">Buscar</PrimaryButton>
                                <i class="fa-solid fa-magnifying-glass text-xs text-gray99 absolute top-[10px] left-4"></i>
                                <!-- Resultados de la búsqueda -->
                                <div v-if="searchFocus && searchQuery"
                                    class="absolute mt-1 bg-white border border-gray-300 rounded shadow-lg w-full z-50 max-h-48 overflow-auto">
                                    <ul v-if="productsFound?.length > 0 && !loading">
                                        <li @click="product.global_product_id ? $inertia.get(route('online-sales.show-global-product', product.id))
                                            : $inertia.get(route('online-sales.show-local-product', product.id))"
                                            v-for="(product, index) in productsFound" :key="index"
                                            class="hover:bg-gray-200 cursor-default text-sm px-5 py-2">{{
                                                product.global_product_id ?
                                                    product.global_product?.name : product.name }}</li>
                                    </ul>
                                    <p v-else-if="!loading" class="text-center text-sm text-gray-600 px-5 py-2">No se
                                        encontraron
                                        coincidencias
                                    </p>
                                    <!-- estado de carga -->
                                    <div v-if="loading" class="flex flex-col justify-center items-center py-10">
                                        <i class="fa-solid fa-square fa-spin text-4xl text-primary mb-2"></i>
                                        <span class="text-primary text-xs">Cargando...</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Login y navegación -->
                            <div class="lg:flex items-center space-x-9 hidden">
                                <button>Inicio</button>
                                <button>Productos</button>
                                <PrimaryButton @click="$inertia.visit(route('login'))">Iniciar sesión</PrimaryButton>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- footer -->
                <div class="overflow-y-auto h-[calc(100vh-5rem)] flex flex-col justify-between bg-white">
                    <slot />
                    <!-- <footer class="flex justify-between items-center bg-[#232323] p-3 h-[72px] md:h-20 md:px-7">
                    </footer> -->
                </div>
            </main>
        </div>
    </div>
</template>

<script>
import ApplicationMark from '@/Components/ApplicationMark.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
// import emitter from '@/eventBus.js';

export default {
    data() {
        return {
            searchQuery: null, //buscador. Palabras escritas en el buscador
            searchFocus: false, //buscador. Bandera de enfoque para el buscador
            productsFound: null, //buscador. productos encontrados.
            loading: false
        }
    },
    components: {
        ApplicationMark,
        PrimaryButton,
        Head,
        Link
    },
    props: {
        title: String,
    },
    methods: {
        handleBlur() {
            // Introducir un retraso para dar tiempo al evento click de ejecutarse antes del blur
            setTimeout(() => {
                this.searchFocus = false;
            }, 100);
        },
        async searchProducts() {
            try {
                this.loading = true;
                const response = await axios.get(route('online-sales.search-products', this.storeId), { params: { query: this.searchQuery } });
                if (response.status === 200) {
                    this.productsFound = response.data.items;
                    this.loading = false;
                }
            } catch (error) {
                console.log(error);
            }
        },
    },
}
</script>
