<template>
    <main @click="$inertia.get(route('public.show-product', product.id))" class="border border-grayD9 rounded-md p-4 flex cursor-pointer hover:border-primary bg-white z-50 space-x-4 md:space-x-0">

        <!-- Imagen del producto -->
        <figure class="flex flex-col justify-center items-center w-[15%]">
            <div class="flex justify-center items-center size-20 md:size-24 lg:size-32 p-1">
                <img v-if="product.media?.find(img => img.collection_name === 'imageCover')" class="h-full mx-auto object-contain"
                    :src="product.media?.find(img => img.collection_name === 'imageCover')?.original_url" alt="product's image cover">
                <div class="text-center" v-else>
                    <i class="fa-regular fa-image text-7xl text-gray-200"></i>
                </div>
            </div>
        </figure>

        <!-- Información de producto -->
        <section class="w-[90%] text-sm md:text-base">
            <div class="flex items-center justify-between">
                <!-- <p class="text-primary">NÚMERO DE PARTE INTERNO: {{ product.part_number }}</p> -->
                <p class="text-primary">NÚMERO DE PARTE DE FABRICANTE: {{ product.part_number_supplier }}</p>
                <p class="text-secondary">UBICACIÓN ALMACÉN: {{ product.location }}</p>
            </div>
            <p class="text-primary uppercase font-bold">{{ product.name }}</p>
            <p class="text-secondary mb-3">{{ product.description }}</p>
            <p v-if="product.media?.filter(media => media.collection_name !== 'imageCover').length" class="text-primary px-3 py-[2px] bg-grayD9 rounded-sm border border-gray99 inline">Descargables</p>
        </section>
    </main>
</template>

<script>
export default {
data() {
    return {

    }
},
components:{

},
props:{
    product: Object
},
methods:{

}
}
</script>
