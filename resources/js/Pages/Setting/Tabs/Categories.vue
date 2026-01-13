<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CategoryCard from "@/Components/MyComponents/Category/CategoryCard.vue";
import axios from "axios";
import { ElNotification } from "element-plus";

// State
const categories = ref([]);
const loading = ref(false);

// Methods
const fetchCategories = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('categories.get-all'));
        if (response.status === 200) {
            categories.value = response.data.items;
        }
    } catch (error) {
        console.error(error);
        ElNotification.error({
            title: 'Error',
            message: 'No se pudo cargar la lista de categorías.',
        });
    } finally {
        loading.value = false;
    }
};

const createCategory = () => {
    router.visit(route('categories.create'));
};

onMounted(() => {
    fetchCategories();
});
</script>

<template>
    <div class="animate-fade-in">
        
        <!-- Header Actions -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <p class="text-xs text-[#6D6E72] max-w-2xl">
                Gestiona las familias de productos y sus niveles jerárquicos (Subcategorías). 
                Haz clic en "Editar" para modificar la estructura.
            </p>
            
            <button 
                v-if="$page.props.auth.user.permissions.includes('Crear categorias')"
                @click="createCategory"
                class="bg-[#1676A2] hover:bg-[#125d80] text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2 shrink-0"
            >
                <i class="fa-solid fa-plus"></i> Nueva Categoría
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-12 text-[#6D6E72]">
            <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 text-[#1676A2]"></i>
            <span class="text-sm">Cargando catálogo...</span>
        </div>

        <!-- Grid de Categorías -->
        <div v-else-if="categories.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <CategoryCard 
                v-for="item in categories" 
                :key="item.id" 
                :category="item" 
                @deleted="fetchCategories" 
            />
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-16 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50">
            <i class="fa-solid fa-layer-group text-4xl text-gray-300 mb-3"></i>
            <p class="text-[#6D6E72] font-medium">No hay categorías registradas.</p>
            <p class="text-xs text-gray-400">Comienza creando la primera familia de productos.</p>
        </div>

    </div>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>