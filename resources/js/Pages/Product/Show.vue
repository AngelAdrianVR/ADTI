<template>
    <AppLayout :title="product.name">
        <main class="px-2 lg:px-10 py-7">
            <!-- header botones -->
            <div class="lg:flex justify-between items-center mx-3">
                <h1 class="font-bold text-lg">Detalles del producto</h1>
                <div class="flex items-center space-x-2 my-2 lg:my-0">
                    <ThirthButton v-if="isInventoryOn" @click="openEntryModal">
                        Entrada de producto
                    </ThirthButton>
                    <PrimaryButton @click="$inertia.get(route('products.edit', product.id))">
                        <p class="flex items-center space-x-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Editar
                        </p>
                    </PrimaryButton>
                    <PrimaryButton class="!bg-grayED !px-3" @click="$inertia.get(route('products.create'))">
                        <i class="fa-solid fa-plus py-1  text-primary"></i>
                    </PrimaryButton>
                </div>
            </div>

            <div class="lg:w-1/4 relative">
                <input v-model="searchQuery" @focus="searchFocus = true" @blur="handleBlur" @input="searchProducts"
                    class="input w-full pl-9" placeholder="Buscar código o nombre de producto" type="search">
                <i class="fa-solid fa-magnifying-glass text-xs text-gray99 absolute top-[10px] left-4"></i>
                <!-- Resultados de la búsqueda -->
                <div v-if="searchFocus && searchQuery"
                    class="absolute mt-1 bg-white border border-gray-300 rounded shadow-lg w-full max-h-40 overflow-auto">
                    <Loading2 v-if="searchLoading" class="my-3" />
                    <ul v-else-if="productsFound?.length > 0">
                        <li @click.stop="handleProductSelected(product)" v-for="(product, index) in productsFound"
                            :key="index" class="hover:bg-gray-100 cursor-default text-sm px-5 py-2">{{
                                product.global_product_id ? product.global_product?.name : product.name }}</li>
                    </ul>
                    <p v-else class="text-center text-sm text-gray-600 px-5 py-2">No se encontraron coincidencias</p>
                </div>
            </div>

            <div class="mt-5">
                <Back />
            </div>

            <!-- Info de producto -->
            <div class="md:grid grid-cols-2 xl:grid-cols-3 gap-x-10 mx-2 md:mx-6">
                <!-- fotografia de producto -->
                <section class="mt-7">
                    <figure class="border h-64 md:h-96 border-grayD9 rounded-lg flex justify-center items-center">
                        <img v-if="product.media?.length" class="h-52 md:h-80 mx-auto object-contain"
                            :src="product.media[0]?.original_url" alt="product's image cover">
                        <div v-else>
                            <i class="fa-regular fa-image text-9xl text-gray-200"></i>
                            <p class="text-sm text-gray-300">Imagen no disponible</p>
                        </div>
                    </figure>
                </section>

                <!-- informacion de producto -->
                <section class="xl:col-span-2 my-3 lg:my-0 xl:mr-28">
                    <!-- Pestañas -->
                    <el-tabs v-model="activeTab" @tab-click="updateURL">
                        <el-tab-pane name="1">
                            <template #label>
                                <svg class="mr-2" width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 1H10.625M1 4.0625H5.8125M1 7.125H10.625" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg> Información General
                            </template>
                            <ProductInfo :product="product" />
                        </el-tab-pane>
                        <!-- <el-tab-pane name="2">
                            <template #label>
                                <svg class="mr-2" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 2.3125C1 1.9644 1.13828 1.63056 1.38442 1.38442C1.63056 1.13828 1.9644 1 2.3125 1H3.625C3.9731 1 4.30694 1.13828 4.55308 1.38442C4.79922 1.63056 4.9375 1.9644 4.9375 2.3125V3.625C4.9375 3.9731 4.79922 4.30694 4.55308 4.55308C4.30694 4.79922 3.9731 4.9375 3.625 4.9375H2.3125C1.9644 4.9375 1.63056 4.79922 1.38442 4.55308C1.13828 4.30694 1 3.9731 1 3.625V2.3125ZM1 8C1 7.6519 1.13828 7.31806 1.38442 7.07192C1.63056 6.82578 1.9644 6.6875 2.3125 6.6875H3.625C3.9731 6.6875 4.30694 6.82578 4.55308 7.07192C4.79922 7.31806 4.9375 7.6519 4.9375 8V9.3125C4.9375 9.6606 4.79922 9.99444 4.55308 10.2406C4.30694 10.4867 3.9731 10.625 3.625 10.625H2.3125C1.9644 10.625 1.63056 10.4867 1.38442 10.2406C1.13828 9.99444 1 9.6606 1 9.3125V8ZM6.6875 2.3125C6.6875 1.9644 6.82578 1.63056 7.07192 1.38442C7.31806 1.13828 7.6519 1 8 1H9.3125C9.6606 1 9.99444 1.13828 10.2406 1.38442C10.4867 1.63056 10.625 1.9644 10.625 2.3125V3.625C10.625 3.9731 10.4867 4.30694 10.2406 4.55308C9.99444 4.79922 9.6606 4.9375 9.3125 4.9375H8C7.6519 4.9375 7.31806 4.79922 7.07192 4.55308C6.82578 4.30694 6.6875 3.9731 6.6875 3.625V2.3125ZM6.6875 8C6.6875 7.6519 6.82578 7.31806 7.07192 7.07192C7.31806 6.82578 7.6519 6.6875 8 6.6875H9.3125C9.6606 6.6875 9.99444 6.82578 10.2406 7.07192C10.4867 7.31806 10.625 7.6519 10.625 8V9.3125C10.625 9.6606 10.4867 9.99444 10.2406 10.2406C9.99444 10.4867 9.6606 10.625 9.3125 10.625H8C7.6519 10.625 7.31806 10.4867 7.07192 10.2406C6.82578 9.99444 6.6875 9.6606 6.6875 9.3125V8Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg> 
                                Historial de movimientos
                            </template>
                            <ProductInfo :product="product.data" />
                        </el-tab-pane> -->
                    </el-tabs>
                </section>
            </div>

        </main>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Loading2 from '@/Components/MyComponents/Loading2.vue';
import ProductInfo from './Tabs/ProductInfo.vue';
import ThirthButton from '@/Components/MyComponents/ThirthButton.vue';
import Back from "@/Components/MyComponents/Back.vue";
import axios from 'axios';

export default {
data() {
    return {
        //buscador
        searchQuery: null,
        searchFocus: false,
        productsFound: null,
        searchLoading: false,

        //tabs
        activeTab: '1',
    }
},
components:{
    AppLayout,
    PrimaryButton,
    ThirthButton,
    ProductInfo,
    Loading2,
    Back
},
props:{
    product: Object
},
methods:{
    handleBlur() {
        // Introducir un retraso para dar tiempo al evento click de ejecutarse antes del blur
        setTimeout(() => {
            this.searchFocus = false;
        }, 100);
    },
    async searchProducts() {
        this.searchLoading = true;
        try {
            const response = await axios.get(route('products.search'), { params: { query: this.searchQuery } });
            if (response.status === 200) {
                this.productsFound = response.data.items;
            }

        } catch (error) {
            console.log(error);
            // toast
            this.$notify({
                title: "Server error",
                message: "No se pudo encontrar ningun producto.",
                type: "error",
                position: "bottom-right",
            });
        } finally {
            this.searchLoading = false;
        }
    },
}
}
</script>
