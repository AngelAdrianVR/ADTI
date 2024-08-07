<template>
    <div class="mx-3">
        <div class="flex items-center justify-end">
            <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear categorias')"
                @click="$inertia.visit(route('categories.create'))" class="rounded-full">
                Crear categoría
            </PrimaryButton>
        </div>
        <p class="text-secondary mt-2">En este apartado, podrás encontrar las categorías principales junto con sus
            subcategorías. Haz clic en “Editar” para modificar una categoría existente o en “Crear” para añadir una
            nueva categoría</p>
        <div class="text-sm mt-5">
            <CategoryCard v-for="(item, index) in categories" :key="index" />
            <el-empty v-if="!categories.length" description="No hay categorias registradas aún" />
        </div>
    </div>
</template>
<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CategoryCard from "@/Components/MyComponents/Category/CategoryCard.vue";

export default {
    data() {
        return {
            // generales
            categories: [],
            // cargas
            loading: false,
        };
    },
    components: {
        AppLayout,
        PrimaryButton,
        CategoryCard,
    },
    props: {

    },
    methods: {

    },
};
</script>