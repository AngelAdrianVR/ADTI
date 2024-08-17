<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PublicCategoryCard from '@/Components/MyComponents/PublicLayout/PublicCategoryCard.vue';

const props = defineProps({
  categories: Array,
});

const cascaderProps = {
  // expandTrigger: 'hover',
  checkStrictly: true, //single selection
}

const value = ref([]);

const handleChange = (value) => { //el value es un arreglo que guarda las selecciones del cascader. el index equivale al nivel de profundidad
//si value solo tiene 1 elemento significa que es categoría, si tiene mas, es subcategoría
  if ( value.length === 1 ) {
    const category_id = props.categories.find(category => category.name === value[0]).id; //obtiene el id de la categoría seleccionada 
    router.get(route('public.show-category', category_id)); //direcciona a la ruta que muestra las subcategorías
  } else {
    const level = value.length; //obctiene el nivel de la opcion seleccionada 0-> categoría, mayor a 0 es subcategoría.
    const category = props.categories.find(cat => cat.name === value[0]); //se guarda la categría de la que forma parte la subcategoría seleccionada.
    const subcategory_id = category?.subcategories.find(sb => sb.name === value[(level - 1)]).id; //se busca el id de la subcategoría seleccionada
    router.get(route('public.show-subcategory', subcategory_id)); //direcciona a la ruta que muestra las subcategorías
  }
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
    let level4 = [];
    let level5 = [];

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
      } else if (subcategory.level === 4) {
        level4.push({
          id: subcategory.id,
          value: subcategory.name,
          label: subcategory.name,
          prev_sub: subcategory.prev_subcategory_id,
          children: []
        });
      } else if (subcategory.level === 5) {
        level5.push({
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

    // Añadir subcategorías de nivel 4 a las correspondientes de nivel 3
    level4.forEach(l4 => {
      const parent = level3.find(l3 => l3.id === l4.prev_sub);
      if (parent) parent.children.push(l4);
    });

    // Añadir subcategorías de nivel 5 a las correspondientes de nivel 4
    level5.forEach(l5 => {
      const parent = level4.find(l4 => l4.id === l5.prev_sub);
      if (parent) parent.children.push(l5);
    });

    transformedCategory.children.push(...level1);

    return transformedCategory;
  });
};

// Utiliza la función con tus categorías
const options = computed(() => transformCategories(props.categories));


</script>

<template>
    <PublicLayout class="relative" :title="'Bienvenido'">
        <main class="lg:mx-40 p-4">

            <!-- Decorations  -->
            <figure>
                <img class="hidden md:block absolute top-40 left-0" src="@/../../public/images/home_decoration1.png" alt="">
                <img class="hidden md:block absolute top-20 left-0" src="@/../../public/images/home_decoration2.png" alt="">
                <img class="hidden md:block absolute top-20 right-0" src="@/../../public/images/home_decoration3.png" alt="">
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
                    <h1 class="font-bold text-center mb-5">TODAS LAS CATEGORIAS</h1>

                    <div class="md:grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                      <PublicCategoryCard class="z-10" v-for="category in categories" :key="category" :category="category" />
                    </div>
                </section>
                <!-- -------------------------------- -->
            </body>
        </main>
    </PublicLayout>
</template>
