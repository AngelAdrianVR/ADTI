<template>
    <PublicLayout :title="'Detalles de producto'">
        <main class="px-2 lg:p-8 xl:px-48 py-7 mx-auto">
                        
            <!-- Decorations  -->
            <figure>
                <img class="absolute top-40 left-0 z-0" src="@/../../public/images/home_decoration1.png" alt="">
                <!-- <img class="hidden md:block absolute top-20 left-0 z-0" src="@/../../public/images/home_decoration2.png" alt=""> -->
                <img class="absolute top-20 right-0 z-0" src="@/../../public/images/home_decoration3.png" alt="">
            </figure>
            <!-- ------------ -->

            <!-- bread crumbles -->
            <div class="flex items-center space-x-3 text-sm text-gray99 mb-9 mx-2 md:mx-6">
                <p class="cursor-pointer hover:text-primary" @click="$inertia.get(route('welcome'))">Inicio</p>
                <i class="fa-solid fa-angle-right text-xs"></i>
                <p class="cursor-pointer hover:text-primary" @click="$inertia.get(route('public.show-category', product.subcategory?.category?.id))">{{ product.subcategory?.category?.name }}</p>
                <i class="fa-solid fa-angle-right text-xs"></i>
                <div class="flex items-center space-x-3" v-for="subcategory in product.bread_crumbles" :key="subcategory">
                    <p @click="getSubcategoryRoute(subcategory)" class="cursor-pointer hover:text-primary">{{ subcategory }}</p>
                    <i class="fa-solid fa-angle-right text-xs"></i>
                </div>
                <p class='text-primary font-bold'>{{ product.name }}</p>
            </div>

            <div class="my-2 -ml-2">
                <Back />
            </div>

            <!-- Info de producto -->
            <div class="md:grid xl:grid-cols-2 gap-x-28 mx-2 md:mx-6">
                <!-- fotografia de producto -->
                <section>
                    <figure class="border h-64 md:h-[420px] md:w-[500px] border-grayD9 rounded-lg flex justify-center items-center bg-white z-50">
                        <img v-if="product.media?.find(img => img.collection_name === 'imageCover')" class="h-52 md:h-96 mx-auto object-contain"
                            :src="product.media?.find(img => img.collection_name === 'imageCover')?.original_url" alt="product's image cover">
                        <div class="bg-white z-50" v-else>
                            <i class="fa-regular fa-image text-9xl text-gray-200"></i>
                            <p class="text-sm text-gray-300">Imagen no disponible</p>
                        </div>
                    </figure>
                </section>

                <!-- informacion de producto -->
                <section class="my-3 lg:my-5">
                    <p class="text-primary">Número de parte: {{ product.part_number }}</p>
                    <p class="text-primary text-3xl font-bold mt-1">{{ product.name }}</p>
                    <p class="text-[#8B8B8B] my-4">UBICACIÓN ALMACÉN: {{ product.location }}</p>
                    <p class="text-[#6D6E72] my-4 text-lg font-bold">{{ product.subcategory?.name }}</p>
                    <p class="text-[#6D6E72] text-sm">{{ product.description }}</p>
                    <p class="text-[#6D6E72] text-sm mt-3 mb-1">Características</p>
                    
                    <!-- tabla de caracteristicas -->
                    <div v-if="product.features?.length" class="border border-gray-300 rounded overflow-hidden">
                        <div v-for="(feature, index) in product.features" :key="index" class="grid grid-cols-2 *:py-1 *:px-4" :class="{ 'bg-gray-200': index % 2 != 0 }">
                            <template v-for="(value, key) in feature" :key="key">
                                <div v-if="key !== 'measure_unit'" class="border-r border-gray-300 font-medium">{{ key }}</div>
                                <div v-if="key !== 'measure_unit'">
                                    {{ value }} <span class="text-sm" v-if="feature.measure_unit">{{ feature.measure_unit }}</span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <p class="text-xs text-[#6D6E72] pl-2" v-else>No contiene características</p>

                    <div class="flex flex-col mt-7 border-y py-2">
                        <p class="text-[#6D6E72] mb-2">Descargables</p>
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-2 mb-2" v-if="product.media.filter(media => media.collection_name === 'files')?.length > 0">
                            <FileView v-for="file in product.media.filter(media => media.collection_name === 'files')" :key="file" :file="file" />
                        </div>
                        <p v-else class=" text-gray-400 mx-4 text-xs mt-1">No hay archivos adjuntos</p>
                    </div>
                </section>
            </div>
        </main>
    </PublicLayout>
</template>

<script>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import FileView from '@/Components/MyComponents/FileView.vue';
import Back from "@/Components/MyComponents/Back.vue";

export default {
data() {
    return {

    }
},
components:{
    PublicLayout,
    FileView,
    Back
},
props:{
    product: Object
},
methods:{
    getSubcategoryRoute(subcategory) {
        const subcategory_id = this.product.subcategory?.category?.subcategories.find(sb => sb.name === subcategory)?.id;
        this.$inertia.get(route('public.show-subcategory', subcategory_id));
    }
}
};
</script>