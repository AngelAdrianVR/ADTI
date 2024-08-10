<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    categories: Array,
    total_products: Number,
});

const getTotalProducts = (category) => {
  return category.subcategories?.reduce((total, subcategory) => {
    return total + (subcategory?.products?.length || 0);
  }, 0);
};
</script>

<template>
    <AppLayout title="Dashboard">
        <main class="py-12 px-2 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <h1 class="font-bold text-lg mb-5">Panel de inicio</h1>

            <!-- Contenedor de estadisticas -->
            <section class="grid lg:grid-cols-2 gap-5">
                <article class="bg-[#2B2B2B] rounded-xl py-7 px-5">

                    <div class="grid grid-cols-2 gap-x-4">
                        <!-- Total de categorías -->
                        <div class="rounded-xl border border-white py-3 px-4 text-white grid grid-cols-3">
                            <svg width="40" height="40" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="18" cy="18" r="17.5" stroke="currentColor"/>
                                <path d="M13.6432 16.5385L10 18.5L13.6432 20.4615M13.6432 16.5385L18.5 19.1538L23.3568 16.5385M13.6432 16.5385L10 14.5769L18.5 10L27 14.5769L23.3568 16.5385M13.6432 20.4615L10 22.4231L18.5 27L27 22.4231L23.3568 20.4615M13.6432 20.4615L18.5 23.0769L23.3568 20.4615M23.3568 16.5385L27 18.5L23.3568 20.4615" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>

                            <div class="col-span-2">
                                <p class="text-2xl md:text-4xl font-bold">{{ categories.length }}</p>
                                <p class="text-base">Total de categorías</p>
                            </div>
                        </div>

                        <!-- Total de productos -->
                        <div class="rounded-xl border border-white py-3 px-4 text-white grid grid-cols-3">
                            <svg width="40" height="40" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="18" cy="18" r="17.5" stroke="currentColor"/>
                                <path d="M12 13.6875C12 13.2399 12.1778 12.8107 12.4943 12.4943C12.8107 12.1778 13.2399 12 13.6875 12H15.375C15.8226 12 16.2518 12.1778 16.5682 12.4943C16.8847 12.8107 17.0625 13.2399 17.0625 13.6875V15.375C17.0625 15.8226 16.8847 16.2518 16.5682 16.5682C16.2518 16.8847 15.8226 17.0625 15.375 17.0625H13.6875C13.2399 17.0625 12.8107 16.8847 12.4943 16.5682C12.1778 16.2518 12 15.8226 12 15.375V13.6875ZM12 21C12 20.5524 12.1778 20.1232 12.4943 19.8068C12.8107 19.4903 13.2399 19.3125 13.6875 19.3125H15.375C15.8226 19.3125 16.2518 19.4903 16.5682 19.8068C16.8847 20.1232 17.0625 20.5524 17.0625 21V22.6875C17.0625 23.1351 16.8847 23.5643 16.5682 23.8807C16.2518 24.1972 15.8226 24.375 15.375 24.375H13.6875C13.2399 24.375 12.8107 24.1972 12.4943 23.8807C12.1778 23.5643 12 23.1351 12 22.6875V21ZM19.3125 13.6875C19.3125 13.2399 19.4903 12.8107 19.8068 12.4943C20.1232 12.1778 20.5524 12 21 12H22.6875C23.1351 12 23.5643 12.1778 23.8807 12.4943C24.1972 12.8107 24.375 13.2399 24.375 13.6875V15.375C24.375 15.8226 24.1972 16.2518 23.8807 16.5682C23.5643 16.8847 23.1351 17.0625 22.6875 17.0625H21C20.5524 17.0625 20.1232 16.8847 19.8068 16.5682C19.4903 16.2518 19.3125 15.8226 19.3125 15.375V13.6875ZM19.3125 21C19.3125 20.5524 19.4903 20.1232 19.8068 19.8068C20.1232 19.4903 20.5524 19.3125 21 19.3125H22.6875C23.1351 19.3125 23.5643 19.4903 23.8807 19.8068C24.1972 20.1232 24.375 20.5524 24.375 21V22.6875C24.375 23.1351 24.1972 23.5643 23.8807 23.8807C23.5643 24.1972 23.1351 24.375 22.6875 24.375H21C20.5524 24.375 20.1232 24.1972 19.8068 23.8807C19.4903 23.5643 19.3125 23.1351 19.3125 22.6875V21Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>


                            <div class="col-span-2">
                                <p class="text-2xl md:text-4xl font-bold">{{ total_products }}</p>
                                <p class="text-base">Total de Productos</p>
                            </div>
                        </div>
                    </div>

                    <div class="my-7">
                        <div v-for="(category, index) in categories" :key="category">
                            <p class="text-white mx-5">Categoría: <strong>{{ category.name }}</strong></p>
                            <div class="flex justify-between items-center text-gray99 font-bold mx-5 mt-1">
                                <span>Subcategorías</span>
                                <span>{{ category.subcategories.length }}</span>
                            </div>
                            <div class="flex justify-between items-center text-gray99 font-bold mx-5 mt-1">
                                <span>Productos</span>
                                <span>{{ getTotalProducts(category) }}</span>
                            </div>

                            <!-- No se muestra la linea divisora en el ultimo elemento -->
                            <div v-if="index < (categories.length - 1)" class="border-t border-gray99 my-3"></div>
                        </div>
                    </div>
                </article>
            </section>
        </main>
    </AppLayout>
</template>
