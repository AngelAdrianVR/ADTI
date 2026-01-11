<script setup>
import { computed } from 'vue';

const props = defineProps({
    categories: Array,
    total_products: Number
});

// Helper para calcular productos totales por categoría
const getTotalProducts = (category) => {
    return category.subcategories?.reduce((total, subcategory) => {
        return total + (subcategory?.products?.length || 0);
    }, 0);
};
</script>

<template>
    <div class="h-full">
        <!-- Sección de Resumen Superior -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <!-- Total de categorías -->
            <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-4 flex items-center justify-between group hover:shadow-sm transition-all">
                <div>
                    <p class="text-indigo-600 text-xs font-bold uppercase tracking-wider mb-1">Categorías</p>
                    <p class="text-2xl font-bold text-gray-800">{{ categories.length }}</p>
                </div>
                <div class="p-2 bg-white rounded-full text-indigo-500 shadow-sm group-hover:scale-110 transition-transform">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                </div>
            </div>

            <!-- Total de productos -->
            <div class="rounded-lg bg-purple-50 border border-purple-100 p-4 flex items-center justify-between group hover:shadow-sm transition-all">
                <div>
                    <p class="text-purple-600 text-xs font-bold uppercase tracking-wider mb-1">Productos</p>
                    <p class="text-2xl font-bold text-gray-800">{{ total_products }}</p>
                </div>
                <div class="p-2 bg-white rounded-full text-purple-500 shadow-sm group-hover:scale-110 transition-transform">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Lista de Categorías -->
        <div class="space-y-4">
            <div v-for="(category, index) in categories" :key="category.id" 
                 class="group border border-gray-100 rounded-lg p-3 hover:bg-gray-50 hover:border-gray-200 transition-colors">
                
                <div class="flex items-center justify-between mb-2">
                    <p class="text-gray-800 font-semibold text-sm flex items-center">
                        <span class="w-2 h-2 rounded-full bg-gray-300 mr-2 group-hover:bg-indigo-500 transition-colors"></span>
                        {{ category.name }}
                    </p>
                </div>

                <div class="flex items-center space-x-4 text-xs">
                    <div class="flex items-center text-gray-500">
                        <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded text-[10px] font-bold mr-1.5">
                            {{ category.subcategories?.length || 0 }}
                        </span>
                        <span>Subs</span>
                    </div>
                    <div class="w-px h-3 bg-gray-300"></div>
                    <div class="flex items-center text-gray-500">
                        <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded text-[10px] font-bold mr-1.5">
                            {{ getTotalProducts(category) }}
                        </span>
                        <span>Prods</span>
                    </div>
                </div>
            </div>
            
            <!-- Estado vacío si no hay categorías -->
            <div v-if="categories.length === 0" class="text-center py-4 text-gray-400 text-sm italic">
                No hay categorías registradas.
            </div>
        </div>
    </div>
</template>