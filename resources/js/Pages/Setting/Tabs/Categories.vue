<template>
    <div>
        <!-- <div class="flex justify-end mt-5 mx-14">
            <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear categorias')"
                @click="createCategory()" class="rounded-full">
                Agregar categoría
            </PrimaryButton>
            <div v-if="selectedItems.length && $page.props.auth.user.permissions.includes('Eliminar categorias')"
                class="ml-3">
                <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#D72C8A"
                    :title="'¿Desea eliminar los elementos seleccionados (' + selectedItems.length + ')?'"
                    @confirm="deleteItems()">
                    <template #reference>
                        <button class="bg-redpad text-white rounded-full px-3 py-[9px] text-xs tracking-widest">Eliminar</button>
                    </template>
                </el-popconfirm>
            </div>
        </div>
        <div class="text-sm mt-3">
            <p v-if="selectedItems.length" class="text-sm text-redpad flex items-center space-x-2 pb-5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <span>Al eliminar alguna categoría, también se eliminarán los tickets asociados</span>
            </p>
            <div v-if="categories.length" class="flex items-center border-b border-grayD9 pb-2">
                <label class="flex items-center ml-3">
                    <Checkbox @change="handleAllItemsChecked" v-model:checked="allItems" name="all"
                        :disabled="!categories.length" />
                    <span class="ms-2 text-sm font-bold">Todas las categorías</span>
                </label>
            </div>
            <CategoryRow :ref="'category' + category.id" v-for="(category, index) in categories" :key="category.id"
                @open="editCategory(category, index)" @checked="handleCheckedItem" :item="category" />
            <el-empty v-if="!categories.length" description="No hay categorías para mostrar" />
        </div>

        <DialogModal :show="showCategoryModal" @close="showCategoryModal = false">
            <template #title>
                <p v-if="editFlag">Categoría {{ currentCategory.name }}</p>
                <p v-else>Crear nueva categoría</p>
            </template>
            <template #content>
                <div>
                    <form @submit.prevent="editFlag ? updateCategory() : storeCategory()" ref="myform"
                        class="grid grid-cols-3">
                        <div class="col-span-full mb-4">
                            <InputLabel value="Nombre de categoría *" class="ml-2" />
                            <input v-model="form.name" class="input" type="text">
                            <InputError :message="form.errors.name" />
                        </div>
                    </form>
                </div>
            </template>
            <template #footer>
                <CancelButton class="mr-1" @click="showCategoryModal = false; form.reset(); editFlag = false;"
                    :disabled="form.processing">
                    Cancelar</CancelButton>
                <PrimaryButton @click="submitForm" :disabled="form.processing">{{ editFlag ? 'Actualizar' : 'Crear'
                    }}
                </PrimaryButton>
            </template>
        </DialogModal> -->
    </div>
</template>
<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import DialogModal from "@/Components/DialogModal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import { useForm } from "@inertiajs/vue3";
import axios from "axios";

export default {
    data() {
        const form = useForm({
            name: null,
        });

        return {
            form,
            selectedItems: [],
            categories: [],
            allItems: false,
            currentCategory: null,
            showCategoryModal: false,
            indexCategoryEdit: null,
            editFlag: false,
        };
    },
    components: {
        AppLayout,
        PrimaryButton,
        CancelButton,
        DialogModal,
        InputLabel,
        InputError,
    },
    props: {
    },
    methods: {
        submitForm() {
            this.$refs.myform.dispatchEvent(new Event('submit', { cancelable: true }));
        },
        handleCheckedItem(evt) {
            if (evt.isActive) {
                this.selectedItems.push(evt.id);
            } else {
                const index = this.selectedItems.findIndex(item => item === evt.id);
                this.selectedItems.splice(index, 1);
            }

            if (this.selectedItems.length === this.categories.length) {
                this.allItems = true;
            } else if (this.selectedItems.length < this.categories.length && this.allItems) {
                this.allItems = false;
            }
        },
        handleAllItemsChecked() {
            if (this.allItems) {
                this.selectedItems = this.categories.map(category => category.id);
            } else {
                this.selectedItems = [];
            }
            this.setSelectedPropFromUserRow(this.allItems);
        },
        setSelectedPropFromUserRow(value) {
            this.categories.forEach(element => {
                const ref = 'category' + element.id;
                this.$refs[ref][0].selected = value;
            });
        },
        editcategory(category, index) {
            // if (this.$page.props.auth.user.permissions.includes('Editar categorias')) {
            this.currentCategory = category;
            this.editFlag = true;
            this.indexCategoryEdit = index;
            this.showCategoryModal = true;

            this.form.name = category.name;
            // }
        },
        createCategory() {
            this.currentCategory = null;
            this.showCategoryModal = true;
            this.editFlag = false;
            this.form.reset();
        },
        async updateCategory() {
            try {
                const response = await axios.put(route('settings.categories.update', this.currentCategory), {
                    name: this.form.name,
                });

                if (response.status === 200) {
                    this.$notify({
                        title: 'Éxito',
                        message: 'Categoría actualizada',
                        type: 'success'
                    });
                    this.categories[this.indexCategoryEdit] = response.data.item;
                    this.form.reset();
                    this.showCategoryModal = false;
                }
            } catch (error) {
                this.$notify({
                    title: 'Error',
                    message: error.message,
                    type: 'error'
                });
            }
        },
        async storeCategory() {
            try {
                const response = await axios.post(route('settings.categories.store'), {
                    name: this.form.name,
                });

                if (response.status === 200) {
                    this.$notify({
                        title: 'Éxito',
                        message: 'Categoría creada',
                        type: 'success'
                    });
                    this.categories.push(response.data.item);
                    this.form.reset();
                    this.showCategoryModal = false;
                }
            } catch (error) {
                this.$notify({
                    title: 'Error',
                    message: error.message,
                    type: 'error'
                });
            }
        },
        async deleteItems() {
            try {
                const response = await axios.post(route('settings.categories.massive-delete', {
                    items_ids: this.selectedItems
                }));

                if (response.status == 200) {
                    this.$notify({
                        title: 'Éxito',
                        message: response.data.message,
                        type: 'success'
                    });

                    // Filtrar el arreglo 'categories' excluyendo los elementos con IDs en 'selectedItems'
                    this.categories = this.categories.filter(category => !this.selectedItems.includes(category.id));
                }
            } catch (err) {
                this.$notify({
                    title: 'Algo salió mal',
                    message: err.message,
                    type: 'error'
                });
                console.log(err);
            }
        },
        async fetchCategories() {
            try {
                const response = await axios.get(route('settings.categories.get-all'));

                if (response.status == 200) {
                    this.categories = response.data.items;
                }
            } catch (err) {
                this.$notify({
                    title: 'Algo salió mal',
                    message: err.message,
                    type: 'error'
                });
                console.log(err);
            }
        },
    },
    mounted() {
        // this.fetchCategories();
    }
};
</script>