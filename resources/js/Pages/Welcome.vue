<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({

});

const props = {
  expandTrigger: 'hover',
  checkStrictly: true, //single selection
}

const handleChange = (value) => {
  console.log(value)
}

const options = [
  {
    value: 'Automatización',
    label: 'Automatización',
    children: [
      {
        value: 'Movimiento lineal',
        label: 'Movimiento lineal',
        children: [
          {
            value: 'Guías de bolas',
            label: 'Guías de bolas',
          },
          {
            value: 'Guías lineales',
            label: 'Guías lineales',
          },
        ],
      },
      {
        value: 'Movimiento radial',
        label: 'Movimiento radial',
        children: [
          {
            value: 'Guias radiales',
            label: 'Guias radiales',
          },
          {
            value: 'Gupias uniformes',
            label: 'Gupias uniformes',
          },
        ],
      },
    ],
  },
  {
    value: 'Sujetadores y tornillos',
    label: 'Sujetadores y tornillos',
    children: [
      {
        value: 'Tornillos y pernos',
        label: 'Tornillos y pernos',
        children: [
          {
            value: 'cabeza cuadrada',
            label: 'cabeza cuadrada',
          },
          {
            value: 'Cabeza redonda',
            label: 'Cabeza redonda',
          },
        ],
      },
      {
        value: 'Tornillos de cruz',
        label: 'Tornillos de cruz',
        children: [
          {
            value: 'Hallen',
            label: 'Hallen',
          },
          {
            value: 'Phillips',
            label: 'Phillips',
          },
        ],
      },
    ],
  },
];

</script>

<template>
    <PublicLayout class="relative" :title="'Bienvenido'">
        <main class="lg:mx-40 p-4">

            <!-- Decorations  -->
            <figure>
                <img class="absolute top-40 left-0" src="@/../../public/images/home_decoration1.png" alt="">
                <img class="hidden md:block absolute top-20 left-0" src="@/../../public/images/home_decoration2.png" alt="">
                <img class="absolute top-20 right-0" src="@/../../public/images/home_decoration3.png" alt="">
            </figure>
            <!-- ------------ -->

            <body>

                <!-- cascader -->
                <div class="md:w-96">
                    <el-cascader
                    class="!w-full"
                    v-model="value"
                    :options="options"
                    :props="props"
                    @change="handleChange"
                    placeholder="Todas las categorías"
                    />
                </div>
                <!-- --------- -->

                <!-- Vista de selección de categorías -->
                <section class="mx-auto my-16">
                    <h1 class="font-bold text-center">TODAS LAS CATEGORIAS</h1>
                </section>
                <!-- -------------------------------- -->
            </body>
        </main>
    </PublicLayout>
</template>
