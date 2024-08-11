<template>
    <main>
        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Fecha de alta:</p>
            <p class="text-black">{{ formatDate(product.created_at) }}</p>
        </div>

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Número de parte interno:</p>
            <p class="text-black">{{ product.part_number }}</p>
        </div>

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Número de parte de fabricante:</p>
            <p class="text-black">{{ product.part_number_supplier }}</p>
        </div>

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Nombre del producto:</p>
            <p class="text-black font-bold">{{ product.name }}</p>
        </div>

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Categoría:</p>
            <p class="text-black">{{ product.subcategory?.category?.name }}</p>
        </div>

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Subcategorías:</p>
            <ul class="flex flex-col" v-for="(subcategory, index) in product.bread_crumbles" :key="subcategory">
                <li><i v-if="index !==0" class="fa-solid fa-arrow-right text-sm mx-1"></i>{{ subcategory }}</li>
            </ul>
        </div>

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Descripción:</p>
            <p class="text-black">{{ product.description }}</p>
        </div>

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Ubicación en almacén:</p>
            <p class="text-black">{{ product.location }}</p>
        </div>

        <div class="flex mt-5">
            <p class="text-[#6D6E72] w-48">Características:</p>
            <!-- tabla de caracteristicas -->
            <div v-if="product.features?.length" class="border border-gray-300 rounded overflow-hidden">
                <div v-for="(feature, index) in product.features" :key="index" class="grid grid-cols-2 *:py-1 *:px-4" :class="{ 'bg-gray-200': index % 2 != 0 }">
                    <div class="border-r border-gray-300 font-medium">
                        {{ feature.name }}
                    </div>
                    <div>
                        {{ feature.value }} <span v-if="feature.measure_unit !== 'No aplica'" class="text-sm">{{ feature.measure_unit }}</span>
                    </div>
                </div>
            </div>
            <p class="text-xs text-[#6D6E72]" v-else>No contiene características</p>
        </div>

        <div class="flex mt-5">
            <p class="text-[#6D6E72] w-48">Descargables:</p>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2" v-if="product.media.filter(media => media.collection_name === 'files')?.length > 0">
                <FileView v-for="file in product.media.filter(media => media.collection_name === 'files')" :key="file" :file="file" />
            </div>
            <p v-else class=" text-gray-400 mx-4 text-xs mt-1">No hay archivos adjuntos</p>
        </div>

    </main>
</template>

<script>
import FileView from '@/Components/MyComponents/FileView.vue';
import { format, parseISO } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
components:{    
    FileView
},
props:{
    product: Object
},
methods:{
    formatDate(dateString) {
        return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
    },
}
}
</script>
