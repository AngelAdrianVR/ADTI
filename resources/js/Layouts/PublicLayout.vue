<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from 'axios';

// Props
defineProps({
    title: String,
});

// State
const searchQuery = ref(null);
const searchFocus = ref(false);
const productsFound = ref(null);
const loading = ref(false);
const searchInput = ref(null);

// Methods
const handleBlur = () => {
    // Retraso para permitir clic en resultados
    setTimeout(() => {
        searchFocus.value = false;
    }, 200);
};

const searchProducts = async () => {
    if (!searchQuery.value) {
        productsFound.value = [];
        return;
    }
    
    try {
        loading.value = true;
        const response = await axios.get(route('products.search'), { 
            params: { query: searchQuery.value } 
        });
        
        if (response.status === 200) {
            productsFound.value = response.data.items;
        }
    } catch (error) {
        console.error("Error buscando productos:", error);
    } finally {
        loading.value = false;
    }
};

const goToProduct = (product) => {
    router.visit(route('public.show-product', product.id));
    searchQuery.value = null; // Limpiar búsqueda al navegar
    productsFound.value = null;
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 font-sans text-gray-900">
        <Head :title="title" />

        <!-- Navbar Sticky con efecto backdrop -->
        <nav class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-gray-200 shadow-sm transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-20 gap-4">
                    
                    <!-- Logo -->
                    <Link :href="'/'" class="shrink-0 transition hover:opacity-80">
                        <ApplicationMark class="h-10 w-auto md:h-12" />
                    </Link>

                    <!-- Buscador Central -->
                    <div class="relative flex-1 max-w-2xl mx-4">
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-magnifying-glass text-gray-400 group-focus-within:text-primary transition-colors"></i>
                            </div>
                            <input 
                                v-model="searchQuery" 
                                @focus="searchFocus = true" 
                                @blur="handleBlur"
                                @input="searchProducts" 
                                ref="searchInput" 
                                type="search"
                                class="block w-full pl-10 pr-4 py-2.5 bg-gray-100 border-transparent rounded-full text-sm focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all duration-300 placeholder-gray-500"
                                placeholder="Buscar productos por nombre, código o descripción..." 
                            />
                            
                            <!-- Indicador de Carga (Spinner absolute) -->
                            <div v-if="loading" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fa-solid fa-circle-notch fa-spin text-primary"></i>
                            </div>
                        </div>

                        <!-- Dropdown de Resultados -->
                        <div v-if="searchFocus && searchQuery"
                            class="absolute top-full mt-2 w-full bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-50 animate-fade-in-down">
                            
                            <ul v-if="productsFound?.length > 0" class="divide-y divide-gray-50 max-h-[60vh] overflow-y-auto">
                                <li v-for="product in productsFound" :key="product.id"
                                    @click="goToProduct(product)"
                                    class="group hover:bg-indigo-50 cursor-pointer px-4 py-3 transition-colors duration-150">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-gray-800 text-sm group-hover:text-primary">
                                                {{ product.name ?? product.description }}
                                            </p>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                N.P: {{ product.part_number }}
                                                <span v-if="product.part_number_supplier" class="mx-1">•</span>
                                                <span v-if="product.part_number_supplier">{{ product.part_number_supplier }}</span>
                                            </p>
                                        </div>
                                        <i class="fa-solid fa-chevron-right text-xs text-gray-300 group-hover:text-primary mt-1"></i>
                                    </div>
                                </li>
                            </ul>
                            
                            <div v-else-if="!loading" class="px-4 py-6 text-center text-gray-500">
                                <div class="mb-2">
                                    <i class="fa-regular fa-face-frown text-2xl text-gray-300"></i>
                                </div>
                                <p class="text-sm">No encontramos coincidencias para "{{ searchQuery }}"</p>
                            </div>
                        </div>
                    </div>

                    <!-- Navegación Derecha -->
                    <div class="flex items-center space-x-6">
                        <Link :href="route('welcome')" 
                              class="hidden lg:inline-flex text-sm font-medium transition-colors"
                              :class="route().current('welcome') ? 'text-primary font-bold' : 'text-gray-600 hover:text-primary'">
                            Inicio
                        </Link>
                        
                        <div class="h-6 w-px bg-gray-300 hidden lg:block"></div>

                        <Link :href="route('login')" class="group inline-flex items-center px-5 py-2.5 text-sm font-medium rounded-full text-white bg-gray-900 hover:bg-primary transition-colors shadow-lg shadow-gray-900/20 hover:shadow-primary/30">
                            <span>Ingresar</span>
                            <i class="fa-solid fa-arrow-right-to-bracket ml-2 group-hover:translate-x-1 transition-transform"></i>
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Contenido Principal -->
        <main class="w-full">
            <slot />
        </main>

        <!-- Footer Simple (Opcional) -->
        <footer class="bg-white border-t border-gray-200 py-8 mt-auto">
            <div class="max-w-7xl mx-auto px-4 text-center text-gray-500 text-sm">
                &copy; {{ new Date().getFullYear() }} ADTI erp. Todos los derechos reservados.
            </div>
        </footer>
    </div>
</template>

<style scoped>
.animate-fade-in-down {
    animation: fadeInDown 0.2s ease-out;
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>