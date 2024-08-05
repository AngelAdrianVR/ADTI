<template>
    <section>
        <article class="flex items-center space-x-4">
            <InputLabel :value="getLabel" class="" />
            <el-input v-model="subCategory.name" placeholder="Ej. Movimiento lineal" :maxlength="255" clearable />
            <div class="flex items-center space-x-2">
                <el-tooltip content="Crear secuencia/rama de subcategoría" placement="top">
                    <button v-if="canAddSubCategory" type="button" @click="handleAddSubCategory"
                        class="hover:text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </button>
                </el-tooltip>
                <el-tooltip content="Agregar imagen" placement="top">
                    <button type="button" class="hover:text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </button>
                </el-tooltip>
                <el-tooltip content="Agregar características a subcategorías" placement="top">
                    <button v-if="canAddCharacteristics" type="button" class="hover:text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3" />
                        </svg>
                    </button>
                </el-tooltip>
                <el-tooltip content="Eliminar subcategoría" placement="top">
                    <button type="button" @click="handleRemoveSubCategory" class="hover:text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </el-tooltip>
            </div>
        </article>

        <div v-if="subCategory.subCategories.length > 0" class="ml-4 space-y-1 mt-1">
            <SubCategory v-for="(child, idx) in subCategory.subCategories" :key="idx" :subCategory="child" :index="idx"
                :parentIndex="getLabel" @addSubCategory="addSubCategoryToChild"
                @removeSubCategory="removeSubCategoryFromChild" />
        </div>
    </section>
</template>

<script>
import InputLabel from "@/Components/InputLabel.vue";

export default {
    props: {
        subCategory: Object,
        index: Number,
        parentIndex: String,
    },
    components: {
        InputLabel,
    },
    emits: ['addSubCategory', 'removeSubCategory'],
    computed: {
        getLabel() {
            const parent = this.parentIndex ? `${this.parentIndex}.` : '';
            return `${parent}${this.index + 1}`;
        },
        canAddSubCategory() {
            const level = this.parentIndex ? this.parentIndex.split('.').length : 0;
            return level < 2; // Limita a 3 niveles (0, 1, 2)
        },
        canAddCharacteristics() {
            return this.subCategory.subCategories.length === 0;
        }
    },
    methods: {
        handleAddSubCategory() {
            this.$emit('addSubCategory', this.getLabel);
        },
        handleRemoveSubCategory() {
            this.$emit('removeSubCategory', this.parentIndex, this.index);
        },
        addSubCategoryToChild(path) {
            const updatedPath = `${this.getLabel}.${path.split('.').pop()}`;
            this.$emit('addSubCategory', updatedPath);
        },
        removeSubCategoryFromChild(parentPath, index) {
            const updatedPath = this.parentIndex
                ? `${this.parentIndex}.${this.index + 1}`
                : `${this.index + 1}`;
            this.$emit('removeSubCategory', updatedPath, index);
        }
    }
};
</script>