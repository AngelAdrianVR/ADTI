<script setup>
import { router } from '@inertiajs/vue3';

defineProps({
    category: Object
});

const goToCategory = (id) => {
    router.visit(route('public.show-category', id));
};

const goToSubcategory = (id) => {
    router.visit(route('public.show-subcategory', id));
};
</script>

<template>
    <article class="group bg-white rounded-2xl shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-gray-100 flex flex-col overflow-hidden h-full">
        
        <!-- Imagen Header -->
        <div @click="goToCategory(category.id)" 
             class="relative h-48 sm:h-56 p-6 bg-white cursor-pointer overflow-hidden flex items-center justify-center">
            
            <img v-if="category.media?.length" 
                 class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-500 ease-in-out"
                 :src="category.media[0]?.original_url" 
                 :alt="category.name">
            
            <div v-else class="flex flex-col items-center justify-center text-gray-300">
                <i class="fa-regular fa-image text-5xl mb-2"></i>
                <span class="text-xs font-medium uppercase tracking-wider">Sin imagen</span>
            </div>

            <!-- Overlay al hover -->
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-300"></div>
        </div>

        <!-- Contenido -->
        <div class="p-5 flex flex-col flex-1">
            <h3 @click="goToCategory(category.id)" 
                class="text-lg font-bold text-gray-800 mb-3 text-center cursor-pointer hover:text-primary transition-colors line-clamp-1"
                :title="category.name">
                {{ category.name }}
            </h3>

            <!-- Separador sutil -->
            <div class="w-12 h-1 bg-primary/20 rounded-full mx-auto mb-4"></div>

            <!-- Lista de subcategorías (Principal nivel) -->
            <div class="flex-1">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 text-center">Explorar</p>
                <ul class="space-y-1.5">
                    <li v-for="subcategory in category.subcategories?.filter(sb => sb.level === 1).slice(0, 4)" 
                        :key="subcategory.id"
                        @click.stop="goToSubcategory(subcategory.id)"
                        class="text-sm text-gray-600 hover:text-primary hover:bg-gray-50 px-2 py-1 rounded cursor-pointer transition-colors flex items-center justify-center text-center">
                        <span class="line-clamp-1">{{ subcategory.name }}</span>
                    </li>
                </ul>
                <!-- Indicador si hay muchas subcategorías -->
                <p v-if="category.subcategories?.filter(sb => sb.level === 1).length > 4" 
                   @click="goToCategory(category.id)"
                   class="text-xs text-center text-primary mt-2 cursor-pointer font-medium hover:underline">
                    Ver más...
                </p>
            </div>
            
            <button @click="goToCategory(category.id)" 
                class="w-full mt-5 py-2 px-4 border border-gray-200 rounded-lg text-sm font-semibold text-gray-600 hover:text-primary hover:border-primary/50 hover:bg-primary/5 transition-all">
                Ver todo
            </button>
        </div>
    </article>
</template>