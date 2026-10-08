<template>
    <PublicLayout :title="category?.name ?? 'Catálogo'">
        <main class="px-2 lg:p-8 xl:px-48 py-7">

            <!-- Estado de carga (la información se consulta al montar la vista) -->
            <Loading v-if="loading" class="mt-4 lg:mt-20" />

            <div v-else>
                <!-- bread crumbles -->
                <div class="flex items-center space-x-3 text-sm text-gray99 mb-5 mx-2 md:mx-6">
                    <p class="cursor-pointer hover:text-primary" @click="$inertia.get(route('welcome'))">Inicio</p>
                    <i class="fa-solid fa-angle-right text-xs"></i>
                    <p class="text-primary font-bold">{{ category?.name }}</p>
                </div>

                <body class="mx-2 md:mx-6">
                    <h1 class="font-bold text-lg mb-2">{{ category?.name }}</h1>

                    <section v-for="subcategory_1 in category?.subcategories?.filter(sb => sb.level === 1)" :key="subcategory_1">
                        <div class="bg-[#F2F2F2] text-[#6D6E72] flex items-center">
                            <i class="fa-solid fa-play text-2xl "></i>
                            <p class="py-1 pl-4">{{ subcategory_1.name }}</p>
                        </div>

                        <div class="grid grid-cols-3 lg:grid-cols-5 xl:grid-cols-7 gap-4 py-5">
                            <PublicSubcategoryCard class="z-10" v-for="subcategory in category?.subcategories?.filter(sb => sb.level === 2 && sb.prev_subcategory_id === subcategory_1.id)" :key="subcategory" 
                                :subcategory="subcategory"
                                :subcategories_lvl_3="category?.subcategories?.filter(sb => sb.level === 3)" />
                        </div>
                    </section>
                </body>
            </div>
        </main>
    </PublicLayout>
</template>

<script>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Loading from "@/Components/MyComponents/Loading.vue";
import PublicSubcategoryCard from '@/Components/MyComponents/PublicLayout/PublicSubcategoryCard.vue';
import axios from 'axios';

export default {
data() {
    return {
        category: null, //categoría recuperada en la petición del mounted
        loading: true
    }
},
components:{
    PublicLayout,
    PublicSubcategoryCard,
    Loading,
},
props:{
    category_id: Number
},
methods:{
    async fetchCategory() {
        this.loading = true;
        try {
            const response = await axios.get(route('categories.fetch-show', this.category_id));
            if ( response.status === 200 ) {
                this.category = response.data.category;
            }
        } catch (error) {
            console.log(error);
            this.$notify({
                title: "Error",
                message: "No se pudo cargar la categoría.",
                type: "error",
                position: "bottom-right",
            });
        } finally {
            this.loading = false;
        }
    }
},
mounted() {
    this.fetchCategory();
}
}
</script>
