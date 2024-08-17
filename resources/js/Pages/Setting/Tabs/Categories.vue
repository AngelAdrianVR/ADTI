<template>
    <Loading v-if="loading" class="mt-4 lg:mt-20" />
    <div v-else class="mx-3 mb-10">
        <div class="flex items-center justify-end">
            <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear categorias')"
                @click="$inertia.visit(route('categories.create'))" class="rounded-full">
                Crear categoría
            </PrimaryButton>
        </div>
        <p class="text-secondary mt-2">En este apartado, podrás encontrar las categorías principales junto con sus
            subcategorías. Haz clic en “Editar” para modificar una categoría existente o en “Crear” para añadir una
            nueva categoría</p>
        <div class="text-sm mt-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <CategoryCard v-for="(item, index) in categories" :key="index" :category="item" @deleted="fetchCategories()" />
            <el-empty v-if="!categories.length" description="No hay categorias registradas aún" class="col-span-full" />
        </div>
    </div>
</template>
<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CategoryCard from "@/Components/MyComponents/Category/CategoryCard.vue";
import Loading from "@/Components/MyComponents/Loading.vue";
import axios from "axios";

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
        Loading,
    },
    props: {

    },
    methods: {
        async fetchCategories() {
            try {
                this.loading = true;
                const response = await axios.get(route('categories.get-all'));

                if (response.status === 200) {
                    this.categories = response.data.items;
                }
            } catch (error) {
                console.log(error);
                this.$notify({
                    title: 'No se pudo obtener la lista de categorias.',
                    message: 'Inténtalo nuevamente o contacta a soporte',
                    type: 'error',
                });
            } finally {
                this.loading = false;
            }
        },
    },
    async mounted() {
        await this.fetchCategories();
    }
};
</script>