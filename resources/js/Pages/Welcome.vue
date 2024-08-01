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
    value: 'guide',
    label: 'Guide',
    children: [
      {
        value: 'disciplines',
        label: 'Disciplines',
        children: [
          {
            value: 'consistency',
            label: 'Consistency',
          },
          {
            value: 'feedback',
            label: 'Feedback',
          },
          {
            value: 'efficiency',
            label: 'Efficiency',
          },
          {
            value: 'controllability',
            label: 'Controllability',
          },
        ],
      },
      {
        value: 'navigation',
        label: 'Navigation',
        children: [
          {
            value: 'side nav',
            label: 'Side Navigation',
          },
          {
            value: 'top nav',
            label: 'Top Navigation',
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
