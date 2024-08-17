<template>
    <PublicLayout :title="subcategory.name">
        <main class="px-2 lg:p-8 xl:px-48 py-7">
            <!-- Decorations  -->
            <figure class="*:z-0">
                <img class="hidden lg:block absolute top-40 left-0" src="@/../../public/images/home_decoration1.png" alt="">
                <!-- <img class="hidden lg:block absolute top-20 left-0" src="@/../../public/images/home_decoration2.png" alt=""> -->
                <img class="hidden lg:block absolute top-20 right-0" src="@/../../public/images/home_decoration3.png" alt="">
            </figure>
            <!-- ------------ -->

            <!-- bread crumbles -->
            <div class="flex items-center space-x-3 text-sm text-gray99 mb-5 mx-2 md:mx-6">
                <div class="flex items-center space-x-3 overflow-x-auto whitespace-nowrap scrollbar-hide w-[90%] md:w-full mx-auto">
                    <p class="cursor-pointer hover:text-primary" @click="$inertia.get(route('welcome'))">Inicio</p>
                    <i class="fa-solid fa-angle-right text-xs"></i>
                    <div class="flex items-center space-x-3" v-for="(subcategory, index) in breadCrumbles" :key="subcategory">
                        <p @click="index === 0 ? $inertia.get(route('public.show-category', subcategory.id)) : $inertia.get(route('public.show-subcategory', subcategory.id))"
                            class="cursor-pointer hover:text-primary" :class="subcategory.name === this.subcategory.name ? 'text-primary font-bold' : ''">{{ subcategory.name }}</p>
                        <i v-if="breadCrumbles.length !== (index + 1) " class="fa-solid fa-angle-right text-xs"></i>                    
                    </div>
                </div>
            </div>

            <Loading v-if="loadingProducts" class="mt-4 lg:mt-20" />

            <body v-else class="mx-2 md:mx-6">
                <div class="flex items-center justify-between">
                    <h1 class="font-bold text-lg mb-2">{{ subcategory.name }}</h1>
                    <p v-if="subcategoryProducts.length" class="text-[#6D6E72]">{{ subcategory?.products?.length }} Artículos</p>
                </div>

                <section>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-7 gap-5 py-1">
                        <PublicSubcategoryCard class="z-10" v-for="subcategory in handleSubcategoryArray" :key="subcategory" :subcategory="subcategory" />
                    </div>
                </section>

                <!-- En caso de haber productos en esta subcategoría -->
                <section class="space-y-3 mt-2" v-if="subcategoryProducts.length">
                    <PublicProductCard class="z-50" v-for="product in subcategoryProducts" :key="product" :product="product" />
                </section>
            </body>

        </main>
    </PublicLayout>
</template>

<script>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Loading from "@/Components/MyComponents/Loading.vue";
import PublicSubcategoryCard from '@/Components/MyComponents/PublicLayout/PublicSubcategoryCard.vue';
import PublicProductCard from '@/Components/MyComponents/PublicLayout/PublicProductCard.vue';
import axios from 'axios';

export default {
data() {
    return {
        breadCrumbles: [],
        subcategoryProducts: [], //productos recuperados en la petición del mounted
        loadingProducts: false
    }
},
components:{
    PublicSubcategoryCard,
    PublicProductCard,
    PublicLayout,
    Loading,
},
props:{
    subcategory: Object,
    total_products: Number // cantidad total de productos que contiene la subcategoría
},
methods:{
    saveBreadCrumbles() {
        let currentSubcategory = this.subcategory;

        // Recorre hacia atrás en los niveles de subcategorías
        while (1) {
            // Agrega el nombre de la subcategoría actual al arreglo
            this.breadCrumbles.unshift({name: currentSubcategory.name, id: currentSubcategory.id});

            // Verificar si la subcategoría actual es de nivel 1 o si no tiene un prev_subcategory_id
            if (currentSubcategory.level === 1 || !currentSubcategory.prev_subcategory_id) {
                break; // Salir del bucle si es nivel 1
            }

            // Encuentra la subcategoría del nivel anterior
            currentSubcategory = this.subcategory.category?.subcategories?.find(sb => sb.id === currentSubcategory.prev_subcategory_id);
        }

        // Finalmente, agrega el nombre de la categoría principal
        if (this.subcategory.category) {
            this.breadCrumbles.unshift({name: this.subcategory.category.name, id:this.subcategory.category.id});
        }
    },
    async fetchSubcategoryProducts() {
        this.loadingProducts = true;
        try {   
            const response = await axios.get(route('products.fetch-subcategory-products', this.subcategory.id));
            if ( response.status === 200 ) {
                this.subcategoryProducts = response.data.products;
            }
        } catch (error) {
            console.log(error);
            // toast
            this.$notify({
                title: "Error",
                message: "No se pudo completar la petición." + error,
                type: "error",
                position: "bottom-right",
            });
        } finally {
            this.loadingProducts = false;
        }
    }
},
computed:{
    handleSubcategoryArray() {
        //si la subcategoría es de nivel 1 recupera las subcategorías relacionadas a esa 1 nivel arriba
        if ( this.subcategory.level === 1 ) {
            return this.subcategory?.category?.subcategories?.filter(sb => sb.level === 2 && sb.prev_subcategory_id === this.subcategory.id)
        } else if ( this.subcategory.level === 2 ) { //si la subcategoría es de nivel 2 recupera las subcategorías relacionadas a esa 1 nivel arriba
            return this.subcategory?.category?.subcategories?.filter(sb => sb.level === 3 && sb.prev_subcategory_id === this.subcategory.id)
        } else if ( this.subcategory.level === 3 ) { //si la subcategoría es de nivel 3 recupera las subcategorías relacionadas a esa 1 nivel arriba
            return this.subcategory?.category?.subcategories?.filter(sb => sb.level === 4 && sb.prev_subcategory_id === this.subcategory.id)
        } else if ( this.subcategory.level === 4 ) { //si la subcategoría es de nivel 4 recupera las subcategorías relacionadas a esa 1 nivel arriba
            return this.subcategory?.category?.subcategories?.filter(sb => sb.level === 5 && sb.prev_subcategory_id === this.subcategory.id)
        }
    }
},
mounted() { 
    this.saveBreadCrumbles(); //guarda todas las subcategorias para el breas crumbles

    if ( this.total_products > 0 ) { //recupera los productos si la subcategoría contiene
        this.fetchSubcategoryProducts(); //Recupera los prosuctos de la subcategoría
    }
    
}
}
</script>

<style>
    /* Añade esto a tu archivo CSS */
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-hide {
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;  /* Firefox */
    }
</style>
