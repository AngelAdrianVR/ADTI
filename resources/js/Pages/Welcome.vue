<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PublicCategoryCard from '@/Components/MyComponents/PublicLayout/PublicCategoryCard.vue';

const props = defineProps({
  categories: Array,
});

const cascaderProps = {
  checkStrictly: true, // Selección única permitida en cualquier nivel
  expandTrigger: 'hover', // UX mejorada: expandir al pasar el mouse
}

const value = ref([]);
const loadingPage = ref(true);

const handleChange = (value) => {
  if (!value || value.length === 0) return;

  // Lógica original preservada pero optimizada
  if (value.length === 1) {
    const category = props.categories.find(c => c.name === value[0]);
    if (category) router.get(route('public.show-category', category.id));
  } else {
    const level = value.length;
    const categoryName = value[0];
    const targetSubName = value[level - 1];
    
    const category = props.categories.find(c => c.name === categoryName);
    const subcategory = category?.subcategories.find(sb => sb.name === targetSubName);
    
    if (subcategory) router.get(route('public.show-subcategory', subcategory.id));
  }
}

// Función de transformación (Lógica original mantenida, solo limpiada visualmente)
const transformCategories = (categories) => {
  if (!categories || !Array.isArray(categories)) return [];

  return categories.map(category => {
    const transformedCategory = {
      value: category.name,
      label: category.name,
      children: []
    };

    // Agrupar por niveles
    const levels = { 1: [], 2: [], 3: [], 4: [], 5: [] };

    category.subcategories.forEach(sub => {
        if (levels[sub.level]) {
            levels[sub.level].push({
                id: sub.id,
                value: sub.name,
                label: sub.name,
                prev_sub: sub.prev_subcategory_id,
                children: []
            });
        }
    });

    // Anidar niveles (bottom-up logic simplificada en loops)
    [5, 4, 3, 2].forEach(lvl => {
        levels[lvl].forEach(child => {
            const parent = levels[lvl-1].find(p => p.id === child.prev_sub);
            if (parent) parent.children.push(child);
        });
    });

    transformedCategory.children.push(...levels[1]);
    return transformedCategory;
  });
};

const options = computed(() => transformCategories(props.categories));

onMounted(() => {
    setTimeout(() => {
      loadingPage.value = false;
    }, 1500); // Reduje un poco el tiempo para mejor UX
});
</script>

<template>
    <div>
        <!-- Loading Screen (Overlay) -->
        <transition name="fade">
            <div v-if="loadingPage" class="fixed inset-0 z-[100] bg-white flex flex-col items-center justify-center">
                <div class="relative">
                    <div class="w-24 h-24 border-4 border-gray-100 border-t-primary rounded-full animate-spin"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                         <img class="w-12 opacity-80" src="/images/logo_colors.webp" alt="Logo" />
                    </div>
                </div>
                <p class="mt-4 text-gray-400 text-sm tracking-widest uppercase animate-pulse">Cargando catálogo...</p>
            </div>
        </transition>

        <PublicLayout :title="'Bienvenido'">
            
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-16">
                
                <!-- Decoraciones de fondo (Absolute) -->
                <div class="pointer-events-none select-none absolute inset-0 overflow-hidden -z-10 opacity-60">
                     <img class="hidden lg:block absolute top-20 left-0 w-64 opacity-50 transform -translate-x-10" src="/images/home_decoration1.png" alt="">
                     <img class="hidden md:block absolute top-10 right-0 w-96 opacity-40 transform translate-x-20" src="/images/home_decoration3.png" alt="">
                </div>

                <div class="relative z-10 flex flex-col items-center">
                    
                    <!-- Header Section -->
                    <div class="text-center max-w-2xl mx-auto mb-10">
                        <h1 class="text-3xl md:text-5xl font-extrabold text-gray-900 tracking-tight mb-4">
                            Explora el <span class="text-primary">Catálogo</span>
                        </h1>
                        <p class="text-gray-500 text-lg">
                            Encuentra rápidamente lo que necesitas navegando por las categorías o usando el buscador inteligente.
                        </p>
                    </div>

                    <!-- Buscador Jerárquico (Cascader) -->
                    <div class="w-full max-w-lg mb-16 rounded-lg">
                        <el-cascader
                            class="!w-full !h-12 text-lg"
                            v-model="value"
                            :options="options"
                            :props="cascaderProps"
                            @change="handleChange"
                            placeholder="Selecciona una categoría..."
                            size="large"
                            filterable
                            clearable
                        />
                    </div>

                    <!-- Grid de Categorías -->
                    <section class="w-full">
                        <div class="flex items-center mb-8">
                            <h2 class="text-xl font-bold text-gray-800 uppercase tracking-wider border-b-4 border-primary/20 pb-1">
                                Categorías Destacadas
                            </h2>
                            <div class="flex-1 h-px bg-gray-100 ml-4"></div>
                        </div>

                        <div v-if="categories.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-8">
                            <PublicCategoryCard 
                                v-for="category in categories" 
                                :key="category.id" 
                                :category="category" 
                                class="h-full"
                            />
                        </div>
                        <div v-else class="text-center py-20 bg-white rounded-xl border border-dashed border-gray-300">
                            <p class="text-gray-400">No hay categorías disponibles por el momento.</p>
                        </div>
                    </section>

                </div>
            </div>
        </PublicLayout>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>