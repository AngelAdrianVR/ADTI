<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    product: Object
});

const goToProduct = () => {
    router.visit(route('public.show-product', props.product.id));
};

// Helper para obtener la imagen de portada de forma segura
const coverImage = props.product.media?.find(img => img.collection_name === 'imageCover')?.original_url;
const hasDownloads = props.product.media?.some(media => media.collection_name !== 'imageCover');
</script>

<template>
    <article @click="goToProduct" 
        class="group bg-white rounded-xl border border-gray-100 p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center gap-6 shadow-sm hover:shadow-lg hover:border-primary/30 transition-all duration-300 cursor-pointer relative overflow-hidden">
        
        <!-- Decoración lateral (hover) -->
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>

        <!-- Imagen del producto -->
        <figure class="w-full sm:w-32 md:w-40 shrink-0 flex justify-center bg-gray-50 rounded-lg p-3 h-32 sm:h-auto self-stretch items-center">
            <img v-if="coverImage" 
                class="max-h-full max-w-full object-contain mix-blend-multiply group-hover:scale-105 transition-transform duration-500"
                :src="coverImage" 
                :alt="product.name">
            <div v-else class="text-center text-gray-300 flex flex-col items-center justify-center h-full">
                <i class="fa-regular fa-image text-4xl sm:text-5xl mb-2 opacity-50"></i>
                <span class="text-[10px] uppercase font-bold tracking-widest">Sin imagen</span>
            </div>
        </figure>

        <!-- Información de producto -->
        <section class="flex-1 min-w-0 w-full">
            
            <!-- Cabecera: Números de parte y Ubicación -->
            <div class="flex flex-wrap items-center justify-between gap-y-2 mb-2 text-xs text-gray-500">
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    <div class="flex items-center space-x-1" title="Número de parte fabricante">
                        <i class="fa-solid fa-industry text-gray-400"></i>
                        <span class="font-mono font-medium text-gray-700">{{ product.part_number_supplier || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center space-x-1" title="Número de parte interno">
                        <i class="fa-solid fa-barcode text-gray-400"></i>
                        <span class="font-mono text-gray-600">{{ product.part_number || 'N/A' }}</span>
                    </div>
                </div>
                
                <div v-if="product.location" class="flex items-center text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider">
                    <i class="fa-solid fa-location-dot mr-1"></i>
                    {{ product.location }}
                </div>
            </div>

            <!-- Título y Descripción -->
            <h3 class="text-lg font-bold text-gray-800 group-hover:text-primary transition-colors mb-2 line-clamp-2 leading-tight">
                {{ product.name }}
            </h3>
            
            <p class="text-sm text-gray-500 mb-4 line-clamp-2 leading-relaxed">
                {{ product.description }}
            </p>

            <!-- Footer: Badges -->
            <div class="flex items-center justify-between mt-auto">
                <div v-if="hasDownloads" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                    <i class="fa-solid fa-file-arrow-down mr-1.5"></i>
                    Ficha Técnica / Docs
                </div>
                <div v-else></div> <!-- Spacer -->

                <span class="text-xs font-semibold text-primary group-hover:translate-x-1 transition-transform flex items-center">
                    Ver detalles <i class="fa-solid fa-arrow-right ml-1"></i>
                </span>
            </div>
        </section>
    </article>
</template>