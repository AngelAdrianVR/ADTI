<template>
    <AppLayout title="Nuevo producto">
        <div class="px-3 md:px-16 py-8">
            <Back :to="route('products.index')" />

            <form @submit.prevent="store"
                class="rounded-lg border border-grayD9 lg:p-5 p-3 lg:w-1/2 mx-auto mt-2 lg:grid lg:grid-cols-2 gap-x-3">

                <h1 class="font-bold ml-2 col-span-full">Crear producto</h1>

                <div class="mt-3">
                    <InputLabel value="Nombre del producto*" class="ml-3 mb-1" />
                    <el-input v-model="form.name" placeholder="Escribe el nombre del producto" :maxlength="100"
                        clearable />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="mt-3">
                    <div class="flex items-center justify-between">
                        <InputLabel value="Categoría*" class="ml-3 mb-1" />
                        <button @click="showCategoryFormModal = true" type="button"
                            class="rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-circle-plus text-primary mr-2"></i>
                        </button>
                    </div>
                    <el-select @change="fetchSubcategories()" class="w-1/2" filterable v-model="form.category_id" clearable placeholder="Seleccione"
                        no-data-text="No hay opciones registradas" no-match-text="No se encontraron coincidencias">
                            <el-option v-for="category in categories" :key="category" :label="category.name"
                            :value="category.id">
                            <p class="flex items-center justify-between">
                                <span>{{ category.name }}</span>
                                <span class="text-[10px] text-gray99">({{ category.key}})</span>
                            </p>
                        </el-option>
                    </el-select>
                    <InputError :message="form.errors.category_id" />
                </div>

                <!-- <div class="mt-3">
                    <div class="flex items-center justify-between">
                        <InputLabel value="Subcategoría*" class="ml-3 mb-1" />
                        <button @click="showSubcategoryFormModal = true" type="button"
                            class="rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-circle-plus text-primary mr-2"></i>
                        </button>
                    </div>
                    <el-select class="w-1/2" filterable v-model="form.subcategory_id" clearable placeholder="Seleccione"
                        no-data-text="No hay opciones registradas" no-match-text="No se encontraron coincidencias">
                        <el-option v-for="subcategory in subcategories" :key="subcategory" :label="subcategory.name"
                            :value="subcategory.id">
                            <p class="flex items-center justify-between">
                                <span>{{ subcategory.name }}</span>
                                <span class="text-[10px] text-gray99">({{ subcategory.key}})</span>
                            </p>
                        </el-option>
                    </el-select>
                    <InputError :message="form.errors.subcategory" />
                </div> -->

                <div class="mt-3 col-span-full">
                    <InputLabel value="Descripción del producto" class="ml-3 mb-1 text-sm" />
                    <el-input v-model="form.description" :autosize="{ minRows: 3, maxRows: 5 }" type="textarea"
                        placeholder="Escribe una descripción del producto si es necesario" :maxlength="255" show-word-limit
                        clearable />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="mt-3 col-span-full">
                    <InputLabel value="Características del producto" class="ml-3 mb-1 text-sm" />
                    <div v-if="form.features.length">
                        <p>Tienes features</p>
                    </div>
                    <p v-else class="text-gray99 text-sm">Selecciona la subcategoría final para ver las características disponibles</p>
                </div>

                <div class="border-t border-grayD9 w-full col-span-full my-7"></div>

                <div class="col-span-full">
                    <InputLabel value="Imagen del producto" class="ml-3 mb-1" />
                    <InputFilePreview @imagen="saveImage" @cleared="form.imageCover = null" />
                </div>

                <div class="mt-3">
                    <InputLabel value="Número de parte" class="ml-3 mb-1" />
                    <el-input v-model="form.part_number" disabled placeholder="Creación automática" :maxlength="100"
                        clearable />
                    <InputError :message="form.errors.part_number" />
                </div>

                <div class="mt-3">
                    <InputLabel value="Ubicación en almacén" class="ml-3 mb-1" />
                    <el-input v-model="form.location" placeholder="Ej. S-4763" :maxlength="100"
                        clearable />
                    <InputError :message="form.errors.location" />
                </div>

                <div class="col-span-full text-right mt-7">
                    <PrimaryButton class="!rounded-full" :disabled="form.processing">
                        <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Crear producto
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- category form -->
        <DialogModal :show="showCategoryFormModal" @close="showCategoryFormModal = false">
            <template #title> Agregar categoría </template>
            <template #content>
                <form @submit.prevent="storeCategory" class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Nombre de la categoría*" class="ml-3 mb-1" />
                        <el-input v-model="categoryForm.name" placeholder="Escribe el nombre de la categoría"
                            :maxlength="100" required clearable />
                        <InputError :message="categoryForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Clave de la categoría*" class="ml-3 mb-1" />
                        <el-input v-model="categoryForm.key" placeholder="Con esta se genera el número de parte"
                            :maxlength="5" required clearable show-word-limit />
                        <InputError :message="categoryForm.errors.key" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex items-center space-x-2">
                    <CancelButton @click="showCategoryFormModal = false" :disabled="categoryForm.processing">Cancelar
                    </CancelButton>
                    <PrimaryButton @click="storeCategory()" :disabled="categoryForm.processing">
                        <i v-if="categoryForm.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Crear
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>
        
        <!-- Subcategory form -->
        <DialogModal :show="showSubcategoryFormModal" @close="showSubcategoryFormModal = false">
            <template #title> Agregar categoría </template>
            <template #content>
                <form @submit.prevent="storeCategory" class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Nombre de la categoría*" class="ml-3 mb-1" />
                        <el-input v-model="subcategoryForm.name" placeholder="Escribe el nombre de la categoría"
                            :maxlength="100" required clearable />
                        <InputError :message="subcategoryForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Clave de la categoría*" class="ml-3 mb-1" />
                        <el-input v-model="subcategoryForm.key" placeholder="Con esta se genera el número de parte"
                            :maxlength="5" required clearable show-word-limit />
                        <InputError :message="subcategoryForm.errors.key" />
                    </div>
                    <div>
                        <InputLabel value="Categoría a la que pertenece*" class="ml-3 mb-1" />
                        <el-select class="w-1/2" filterable v-model="subcategoryForm.category_id" clearable placeholder="Seleccione"
                            no-data-text="No hay opciones registradas" no-match-text="No se encontraron coincidencias">
                                <el-option v-for="category in categories" :key="category" :label="category.name"
                                :value="category.id">
                                <p class="flex items-center justify-between">
                                    <span>{{ category.name }}</span>
                                    <span class="text-[10px] text-gray99">({{ category.key}})</span>
                                </p>
                            </el-option>
                        </el-select>
                        <InputError :message="subcategoryForm.errors.category_id" />
                    </div>
                    <div>
                        <InputLabel value="Nivel de subcategoría*" class="ml-3 mb-1" />
                        <el-input v-model="subcategoryForm.level" type="number" placeholder="Indica el nivel de la subcategoría"
                            :maxlength="5" required />
                        <InputError :message="subcategoryForm.errors.level" />
                    </div>

                    <figure class="w-[600px] mt-7">
                        <InputLabel value="Ejemplo de niveles de subcategoría" class="ml-3 mb-3" />
                        <img src="@/../../public/images/lvl_example.png" alt="">
                    </figure>
                </form>
            </template>
            <template #footer>
                <div class="flex items-center space-x-2">
                    <CancelButton @click="showSubcategoryFormModal = false" :disabled="subcategoryForm.processing">Cancelar
                    </CancelButton>
                    <PrimaryButton @click="storeSubcategory()" :disabled="subcategoryForm.processing">
                        <i v-if="subcategoryForm.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Crear
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>

    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputFilePreview from "@/Components/MyComponents/InputFilePreview.vue";
import DialogModal from "@/Components/DialogModal.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
data() {
    const form = useForm({
            name: null,
            category_id: null,
            subcategory_id: null,
            description: null,
            features: {},
            imageCover: null,
            part_number: null,
            location: null,
        });

        const categoryForm = useForm({
            name: null,
            key: null,
        });

        const subcategoryForm = useForm({
            name: null,
            key: null,
            category_id: null,
        });

    return {
        //formularios
        form,
        categoryForm,
        subcategoryForm,

        //General
        showCategoryFormModal: false,
        showSubcategoryFormModal: false,
        categoryInfo: null, //Información recuperada de categoría incluye subcategorías.
        loading: false, //estado de carga (fetchCategory).
    }
},
components:{
    AppLayout,
    InputFilePreview,
    PrimaryButton,
    CancelButton,
    DialogModal,
    InputLabel,
    InputError,
    Back,
},
props:{
    categories: Array,
},
methods:{
    async store() {
        try {
            this.form.post(route("products.store"), {
                onSuccess: async () => {
                    // toast
                    this.$notify({
                        title: "Correcto",
                        message: "",
                        type: "success",
                        position: "bottom-right",
                    });
                },
            });
        } catch (error) {
            console.error(error);
        }
    },
    async storeCategory() {
        try {
            const response = await axios.post(route('categories.store'), {
                name: this.categoryForm.name
            });
            if (response.status === 200) {
                this.$notify({
                    title: "Éxito",
                    message: "Se ha creado una nueva categoría",
                    type: "success",
                    position: "bottom-right",
                });
                this.form.category_id = response.data.item.id;
                this.localCategories.push(response.data.item);
                this.showCategoryFormModal = false;
                this.categoryForm.reset();
            }
        } catch (error) {
            console.log(error)
        }
    },
    async storeSubcategory() {
        try {
            const response = await axios.post(route('categories.store'), {
                name: this.categoryForm.name
            });
            if (response.status === 200) {
                this.$notify({
                    title: "Éxito",
                    message: "Se ha creado una nueva categoría",
                    type: "success",
                    position: "bottom-left",
                });
                this.form.category_id = response.data.item.id;
                this.localCategories.push(response.data.item);
                this.showCategoryFormModal = false;
                this.categoryForm.reset();
            }
        } catch (error) {
            console.log(error)
        }
    },
    async fetchSubcategories() {
        this.loading = true;
        try {
            const response = await axios.get(route('categories.fetch-subcategories', this.form.category_id));
            if ( response.status === 200 ) {
                this.categoryInfo = response.data.category;
            }
        } catch (error) {
            console.log(error);
        } finally {
            this.loading = false;
        }
    },
    saveImage(image) {
        this.form.imageCover = image;
    },
}
}
</script>