<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardPanel1 from '@/Components/MyComponents/Dashboard/DashboardPanel1.vue';

defineProps({
    categories: Array,
    total_products: Number,
});

</script>

<template>
    <AppLayout title="Dashboard">
        <main class="py-12 px-2 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <h1 class="font-bold text-lg mb-5">Panel de inicio</h1>

            <!-- Contenedor de estadisticas -->
            <section class="grid lg:grid-cols-2 gap-5">
                <DashboardPanel1 :categories="categories" :total_products="total_products" />
            </section>
        </main>
    </AppLayout>
</template>
