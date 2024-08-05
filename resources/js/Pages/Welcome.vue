<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
  categories: Array,
});

const cascaderProps = {
  // expandTrigger: 'hover',
  checkStrictly: true, //single selection
}

const handleChange = (value) => {
  console.log(value)
}

// Función para transformar categorías
const transformCategories = (categories) => {
  if (!categories || !Array.isArray(categories)) return []; // Asegúrate de que categories esté definido y sea un arreglo

  return categories.map(category => {
    const transformedCategory = {
      value: category.name,
      label: category.name,
      children: []
    };

    // Organizar subcategorías por nivel
    let level1 = [];
    let level2 = [];
    let level3 = [];

    category.subcategories.forEach(subcategory => {
      if (subcategory.level === 1) {
        level1.push({
          id: subcategory.id,
          value: subcategory.name,
          label: subcategory.name,
          children: []
        });
      } else if (subcategory.level === 2) {
        level2.push({
          id: subcategory.id,
          value: subcategory.name,
          label: subcategory.name,
          prev_sub: subcategory.prev_subcategory_id,
          children: []
        });
      } else if (subcategory.level === 3) {
        level3.push({
          id: subcategory.id,
          value: subcategory.name,
          label: subcategory.name,
          prev_sub: subcategory.prev_subcategory_id,
          children: []
        });
      }
    });

    // Añadir subcategorías de nivel 2 a las correspondientes de nivel 1
    level2.forEach(l2 => {
      const parent = level1.find(l1 => l1.id === l2.prev_sub);
      if (parent) parent.children.push(l2);
    });

    // Añadir subcategorías de nivel 3 a las correspondientes de nivel 2
    level3.forEach(l3 => {
      const parent = level2.find(l2 => l2.id === l3.prev_sub);
      if (parent) parent.children.push(l3);
    });

    transformedCategory.children.push(...level1);

    // console.log(level1);
    // console.log(level2);
    // console.log(level3);
    return transformedCategory;
  });
};


const options = computed(() => transformCategories(props.categories));

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
                    :props="cascaderProps"
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
