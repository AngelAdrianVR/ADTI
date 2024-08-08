<template>
    <div class="border border-grayD9 px-4 py-3 rounded-[10px] self-start">
        <header class="flex items-center justify-end space-x-1">
            <button type="button" @click="$inertia.visit(route('categories.edit', category.id))"
                class="size-6 rounded-full bg-grayED flex items-center justify-center text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                </svg>
            </button>
            <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#0355B5"
                title="Se borrarán subcategorias y productos relacionados. ¿Continuar?" @confirm="deleteCategory">
                <template #reference>
                    <button type="button"
                        class="size-6 rounded-full bg-grayED flex items-center justify-center text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </template>
            </el-popconfirm>
        </header>
        <main class="mt-3">
            <figure class="w-full h-56 border border-grayD9 rounded-[3px]">
                <img v-if="category.media.length" :src="category.media[0].original_url" :alt="category.name"
                    class="w-full h-56 object-contain">
                <div v-else class="h-full flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1"
                        stroke="currentColor" class="size-32 text-grayED">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                </div>
            </figure>
            <section class="lg:mx-5 mt-4">
                <h1 class="font-bold text-primary">{{ category.name }}</h1>
                <el-tree :data="data" default-expand-all :expand-on-click-node="false" :props="defaultProps"
                    @node-click="handleNodeClick">
                    <template #default="{ node, data }">
                        <div class="w-full flex items-center justify-between">
                            <span>{{ node.label }}</span>
                            <div v-if="data.features && data.features.length" class="flex items-center space-x-1">
                                <el-tooltip content="Exportar productos" placement="top">
                                    <button
                                        class="size-6 rounded-full flex items-center justify-center text-secondary hover:text-white hover:bg-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                        </svg>
                                    </button>
                                </el-tooltip>
                                <el-tooltip placement="top">
                                    <template #content>
                                        <h1 class="font-bold">Descargar plantilla</h1>
                                        <p>Si ya tienes los productos en la plantilla da clic en el link</p>
                                        <p class="text-end">
                                            <Link :href="route('products.index')">
                                            <button class="underline text-primary">Ir a importación</button>
                                            </Link>
                                        </p>
                                    </template>
                                    <button @click="downloadTemplate(data)"
                                        class="size-6 rounded-full flex items-center justify-center text-secondary hover:text-white hover:bg-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </button>
                                </el-tooltip>
                            </div>
                        </div>
                    </template>
                </el-tree>
            </section>
        </main>
    </div>
</template>
<script>
import { Link, useForm } from '@inertiajs/vue3';

export default {
    data() {
        const form = useForm({});

        return {
            form,
            data: [],
            defaultProps: {
                children: 'children',
                label: 'label',
            },
            // carga
            loading: false,
        }
    },
    components: {
        Link,
    },
    emits: ['deleted'],
    props: {
        category: Object,
    },
    methods: {
        downloadTemplate(data) {
            this.$inertia.visit(route('subcategories.download-excel-template', data.id))
        },
        handleNodeClick(data) {
            console.log(data)
        },
        organizeSubcategories() {
            const map = new Map();

            // Organizar las subcategorías en un mapa por su ID
            this.category.subcategories.forEach(subcategory => {
                map.set(subcategory.id, {
                    id: subcategory.id,
                    label: subcategory.name,
                    features: subcategory.features, // Incluyendo las características
                    children: []
                });
            });

            // Crear la estructura jerárquica
            this.category.subcategories.forEach(subcategory => {
                if (subcategory.prev_subcategory_id) {
                    const parent = map.get(subcategory.prev_subcategory_id);
                    if (parent) {
                        parent.children.push(map.get(subcategory.id));
                    }
                } else {
                    this.data.push(map.get(subcategory.id));
                }
            });
        },
        deleteCategory() {
            this.form.delete(route('categories.destroy', this.category.id), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: '',
                        type: 'success',
                    });

                    this.$emit('deleted');
                }
            });
        },
    },
    mounted() {
        this.organizeSubcategories();
    }
}
</script>