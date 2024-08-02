<template>
    <main class="border">
        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Fecha de alta:</p>
            <p class="text-black">{{ formatDate(product.created_at) }}</p>
        </div>

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Número de parte:</p>
            <p class="text-black">{{ product.part_number }}</p>
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
                <li>{{ (index + 1) + '. ' + subcategory }}</li>
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

        <div class="flex mt-2">
            <p class="text-[#6D6E72] w-48">Características:</p>
            <!-- tabla de caracteristicas -->
            <div class="border border-grayD9 rounded overflow-hidden">
                <div v-for="feature in product.features" :key="feature">
                    <div class="grid grid-cols-2 *:py-1 *:px-4" 
                        :class="{ 'bg-[#EDEDED]': index % 2 != 0 }" 
                        v-for="(value, key) in feature" :key="key">
                        <div class="border-r border-grayD9">{{ key }}</div>
                        <div>{{ value }}</div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</template>

<script>
import { format, parseISO } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
components:{

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
