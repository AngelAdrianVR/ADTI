<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    subcategory: Object,
});

const goToSubcategory = () => {
    router.visit(route('public.show-subcategory', props.subcategory.id));
};

// Obtener imagen segura
const coverImage = props.subcategory.media?.[0]?.original_url;
</script>

<template>
    <article @click="goToSubcategory" 
        class="group bg-white rounded-xl border border-gray-100 p-4 cursor-pointer shadow-sm hover:shadow-md hover:-translate-y-1 hover:border-primary/40 transition-all duration-300 flex flex-col items-center h-full">
        
        <!-- Contenedor de Imagen -->
        <figure class="w-full aspect-square max-w-[140px] bg-gray-50 rounded-lg p-4 mb-4 flex items-center justify-center overflow-hidden relative group-hover:bg-indigo-50/30 transition-colors">
            <img v-if="coverImage" 
                class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-500"
                :src="coverImage" 
                :alt="subcategory.name">
            
            <div v-else class="text-center text-gray-300 flex flex-col items-center justify-center">
                <i class="fa-regular fa-folder-open text-5xl mb-2 opacity-40 group-hover:text-primary/40 transition-colors"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest opacity-60">Sin imagen</span>
            </div>
        </figure>

        <!-- Nombre -->
        <h3 class="text-center font-bold text-gray-700 text-sm md:text-base group-hover:text-primary transition-colors line-clamp-2 w-full px-2">
            {{ subcategory.name }}
        </h3>

        <!-- Indicador visual (opcional, una linea pequeña) -->
        <div class="w-8 h-1 bg-gray-200 rounded-full mt-3 group-hover:bg-primary group-hover:w-12 transition-all duration-300"></div>
    </article>
</template>