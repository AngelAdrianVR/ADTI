<template>
    <Head title="Códigos de barras" />
    <section class="grid grid-cols-2 gap-">
        <div v-for="(product, index) in products" :key="index" class="h-[calc((100vh-2cm)/9)] md:h-32 flex flex-col items-center justify-center">
            <svg :ref="`barcode${index}`" class="h-[70%]"></svg>
            <p class="h-[30%] text-[10px] text-center">{{ product.name }}</p>
        </div>
    </section>
</template>

<script>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import JsBarcode from 'jsbarcode';
import { Head } from '@inertiajs/vue3';

export default {
    components: {
        PrimaryButton,
        Head,
    },
    props: {
        products: Array,
    },
    methods: {
        fillBarcodes() {
            this.products.forEach((product, index) => {
                const currentRef = `barcode${index}`;
                JsBarcode(this.$refs[currentRef], product.part_number, {
                    format: "CODE128",
                    lineColor: "#000",
                    width: 1.8,
                    height: 60, // Ajusta esta altura para que quepan 15 por página
                    displayValue: true
                });
            });
        },
    },
    mounted() {
        this.fillBarcodes();
        window.print();
    }
}
</script>

<style>
@media print {
    body {
        margin: 0;
        padding: 0;
    }
    .barcode-container {
        padding: 0;
    }
}
</style>
