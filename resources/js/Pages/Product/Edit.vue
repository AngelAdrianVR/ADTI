<template>
    <AppLayout :title="'Edición - ' + product.name">
        <div class="px-3 md:px-16 py-8">
            <Back :to="route('products.index')" />

            <form @submit.prevent="update"
                class="rounded-lg border border-grayD9 lg:p-5 p-3 lg:w-2/3 xl:w-1/2 mx-auto mt-2 lg:grid lg:grid-cols-2 gap-x-3">

                <h1 class="font-bold ml-2 col-span-full">Editar producto</h1>

                <!-- <div class="mt-3">
                    <InputLabel value="Nombre del producto" class="ml-3 mb-1" />
                    <el-input v-model="form.name" placeholder="Escribe el nombre del producto" :maxlength="100"
                        clearable />
                    <InputError :message="form.errors.name" />
                </div> -->
                <div class="mt-3">
                    <InputLabel value="Número de parte del fabricante" class="ml-3 mb-1" />
                    <el-input v-model="form.part_number_supplier"
                        placeholder="Escribe el número de parte del fabricante" clearable @input="filterInput" />
                    <InputError :message="form.errors.part_number_supplier" />
                </div>
                <div class="mt-3">
                    <div class="flex items-center justify-between">
                        <InputLabel value="Categoría*" class="ml-3 mb-1" />
                        <p v-if="loading" class="text-xs mb-1 text-center">
                            Cargando <i class="fa-sharp fa-solid fa-circle-notch fa-spin ml-2 text-primary"></i>
                        </p>
                        <!-- <button @click="showCategoryFormModal = true" type="button"
                            class="rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-circle-plus text-primary mr-2"></i>
                        </button> -->
                    </div>
                    <el-select @change="fetchSubcategories()" class="w-1/2" filterable v-model="form.category_id"
                        placeholder="Seleccione" no-data-text="No hay opciones registradas"
                        no-match-text="No se encontraron coincidencias">
                        <el-option v-for="category in categories" :key="category" :label="category.name"
                            :value="category.id">
                            <p class="flex items-center justify-between">
                                <span>{{ category.name }}</span>
                                <span class="text-[10px] text-gray99">({{ category.key }})</span>
                            </p>
                        </el-option>
                    </el-select>
                    <InputError :message="form.errors.category_id" />
                </div>

                <div v-if="highestLevel > 0">
                    <div v-for="(subcategory, index) in highestLevel" :key="subcategory" class="mt-3">
                        <div class="flex items-center justify-between">
                            <InputLabel :value="'Subcategoría (' + (index + 1) + ')*'" class="ml-3 mb-1" />
                            <!-- <button @click="handleCreateSubcategory(index)" type="button"
                                class="rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-circle-plus text-primary mr-2"></i>
                            </button> -->
                        </div>

                        <!-- Cuando es la primera subcategoría (no contiene un subcategory_id) -->
                        <el-select v-if="index == 0" @change="saveFeatures((index + 1), form.subcategory_id[index])"
                            class="w-1/2" filterable v-model="form.subcategory_id[index]" clearable
                            placeholder="Seleccione" no-data-text="No hay opciones registradas"
                            no-match-text="No se encontraron coincidencias">
                            <el-option @click.stop="form.bread_crumbles[index] = subcategory.name"
                                v-for="subcategory in categoryInfo.subcategories.filter(sub => sub.level == (index + 1))"
                                :key="subcategory" :label="subcategory.name" :value="subcategory.id">
                                <p class="flex items-center justify-between">
                                    <span>{{ subcategory.name }}</span>
                                    <span class="text-[10px] text-gray99">({{ subcategory.key }})</span>
                                </p>
                            </el-option>
                        </el-select>

                        <!-- Cuando no es la primera subcategoría -->
                        <el-select v-else @change="saveFeatures((index + 1), form.subcategory_id[index])" class="w-1/2"
                            filterable v-model="form.subcategory_id[index]" clearable placeholder="Seleccione"
                            no-data-text="No hay opciones registradas"
                            no-match-text="Primero seleccione el nivel anterior">
                            <el-option @click.stop="form.bread_crumbles[index] = subcategory.name"
                                v-for="subcategory in categoryInfo.subcategories.filter(sub => sub.prev_subcategory_id === form.subcategory_id[index - 1] && sub.level === (index + 1))"
                                :key="subcategory" :label="subcategory.name" :value="subcategory.id">
                                <p class="flex items-center justify-between">
                                    <span>{{ subcategory.name }}</span>
                                    <span class="text-[10px] text-gray99">({{ subcategory.key }})</span>
                                </p>
                            </el-option>
                        </el-select>
                        <InputError :message="form.errors.subcategory_id" />
                    </div>
                </div>

                <div class="mt-3 col-span-full">
                    <InputLabel value="Descripción del producto" class="ml-3 mb-1 text-sm" />
                    <el-input v-model="form.description" :autosize="{ minRows: 3, maxRows: 5 }" type="textarea"
                        placeholder="Escribe una descripción del producto si es necesario" :maxlength="100"
                        show-word-limit clearable />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Caracteristicas del producto -->
                <div class="mt-3 col-span-full">
                    <div class="flex justify-between items-center">
                        <InputLabel value="Características del producto" class="ml-3 mb-1 text-sm" />
                        <ThirthButton type="button" v-if="form.features.length" @click="showMeasureUnitFormModal = true"
                            class="!py-0">Crear unidad de medida</ThirthButton>
                    </div>
                    <p v-if="form.features.length" class="text-gray99 text-sm mb-2">Si algún campo no es
                        necesario, puedes dejarlo en blanco. Este campo no será visible para los usuarios.</p>
                    <Loading v-if="loadingFeatures" class="my-6" />
                    <section v-else>
                        <div v-if="form.features.length" class="grid grid-cols-2 gap-5">
                            <div v-for="(feature, index) in form.features" :key="index"
                                class="flex items-center space-x-2">
                                <div class="w-1/2">
                                    <InputLabel :value="Object.values(feature)[0]" class="ml-3 mb-1 text-sm" />
                                    <el-select v-if="feature?.options?.length && feature?.options[0]?.name !== null"
                                        @change="handleChangeFeatureOption(index, $event)"
                                        v-model="form.features[index][Object.keys(feature)[1]]" class="w-1/2" filterable
                                        placeholder="Seleccione" no-data-text="No hay opciones registradas"
                                        no-match-text="No se encontraron coincidencias">
                                        <el-option v-for="option in feature.options" :key="option" :label="option.name"
                                            :value="option.name">
                                            <p class="flex items-center justify-between">
                                                <span>{{ option.name }}</span>
                                                <span class="text-[10px] text-gray99">({{ option.key }})</span>
                                            </p>
                                        </el-option>
                                    </el-select>
                                    <el-input v-else v-model="form.features[index][Object.keys(feature)[1]]"
                                        placeholder="Escribe el valor de la característica" :maxlength="100"
                                        clearable />
                                </div>
                                <div class="w-1/2">
                                    <InputLabel value="Unidad de medida" class="ml-3 mb-1 text-sm" />
                                    <el-select class="w-1/2" filterable v-model="form.features[index].measure_unit"
                                        placeholder="Seleccione" no-data-text="No hay opciones registradas"
                                        no-match-text="No se encontraron coincidencias">
                                        <el-option label="No aplica" value="No aplica" />
                                        <el-option v-for="unit in measure_units" :key="unit" :label="unit.name"
                                            :value="unit.name">
                                            <p class="flex items-center justify-between">
                                                <span>{{ unit.name }}</span>
                                                <span v-if="unit.abreviation" class="text-[10px] text-gray99">({{
                                                    unit.abreviation }})</span>
                                            </p>
                                        </el-option>
                                    </el-select>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-gray99 text-sm">Selecciona la subcategoría final para ver las
                            características
                            disponibles</p>
                    </section>
                </div>

                <div class="border-t border-grayD9 w-full col-span-full my-7"></div>

                <div class="col-span-full">
                    <InputLabel value="Imagen del producto" class="ml-3 mb-1" />
                    <InputFilePreview @imagen="saveImage($event); form.imageCoverCleared = false"
                        @cleared="form.imageCover = null; form.imageCoverCleared = true"
                        :imageUrl="product.media?.find(img => img.collection_name === 'imageCover')?.original_url" />
                </div>
                <div class="mt-3">
                    <InputLabel value="Ubicación en almacén" class="ml-3 mb-1" />
                    <el-input v-model="form.location" placeholder="Ej. S-4763" :maxlength="100" clearable />
                    <InputError :message="form.errors.location" />
                </div>
                <div class="mt-3">
                    <InputLabel value="Precio de lista" class="ml-3 mb-1" />
                    <div class="flex items-center space-x-1">
                        <div class="w-1/2">
                            <el-input v-model="form.line_cost" type="text"
                                :formatter="(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
                                :parser="(value) => value.replace(/[^\d.]/g, '')" placeholder="0.00">
                                <template #prefix>
                                    <i class="fa-solid fa-dollar-sign"></i>
                                </template>
                            </el-input>
                            <InputError :message="form.errors.line_cost" />
                        </div>
                        <div class="w-1/2">
                            <el-select v-model="form.currency" placeholder="Moneda"
                                no-data-text="No hay opciones registradas"
                                no-match-text="No se encontraron coincidencias">
                                <el-option v-for="currency in ['$MXN', '$USD']" :key="currency" :label="currency"
                                    :value="currency" />
                            </el-select>
                            <InputError :message="form.errors.currency" />
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <InputLabel value="Número de parte interno" class="ml-3 mb-1" />
                    <el-input v-model="form.part_number" disabled placeholder="Creación automática" :maxlength="100"
                        clearable />
                    <InputError :message="form.errors.part_number" />
                </div>
                <div class="mt-5 col-span-full">
                    <p class="text-[#6D6E72] mb-2 text-sm">Archivos adjuntos al producto</p>
                    <section v-if="product.media?.filter(media => media.collection_name === 'files')?.length > 0">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                            <FileView v-for="file in product_files.filter(media => media.collection_name === 'files')"
                                :key="file" :file="file" :deletable="true" @delete-file="removeFile($event)" />
                        </div>
                        <!-- <div class="flex justify-between items-center mt-2">
                            <el-checkbox v-model="form.deleteMedia" label="Eliminar archivos existentes" />
                        </div> -->
                    </section>
                    <p v-else class=" text-gray-400 mx-4 text-xs mt-1">No hay archivos adjuntos</p>
                </div>

                <div class="ml-2 mt-3 col-span-full">
                    <FileUploader @files-selected="this.form.media = $event" />
                </div>

                <div class="col-span-full space-x-4 text-right mt-7">
                    <!-- <ThirthButton :disabled="!form.category_id || form.subcategory_id.length < 2" type="button" @click="generatePartNumber()">Generar número de parte</ThirthButton> -->
                    <PrimaryButton class="!rounded-full" :disabled="form.processing">
                        <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Guardar cambios
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- measure unit form -->
        <DialogModal :show="showMeasureUnitFormModal" @close="showMeasureUnitFormModal = false">
            <template #title> Crear Unidad de medida </template>
            <template #content>
                <form @submit.prevent="storeMeasureUnit" class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Nombre de la unidad de medida*" class="ml-3 mb-1" />
                        <el-input v-model="measureUnitForm.name" placeholder="Ej. Centímetro" :maxlength="100" required
                            clearable />
                        <InputError :message="measureUnitForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Abreviación*" class="ml-3 mb-1" />
                        <el-input v-model="measureUnitForm.abreviation" placeholder="Ej. cm" :maxlength="100" required
                            clearable />
                        <InputError :message="measureUnitForm.errors.abreviation" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex items-center space-x-2">
                    <CancelButton @click="showMeasureUnitFormModal = false" :disabled="measureUnitForm.processing">
                        Cancelar
                    </CancelButton>
                    <PrimaryButton @click="storeMeasureUnit()" :disabled="measureUnitForm.processing">
                        <i v-if="measureUnitForm.processing"
                            class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Crear
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <!-- category form -->
        <DialogModal :show="showCategoryFormModal" @close="showCategoryFormModal = false">
            <template #title> Crear categoría </template>
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
                        <i v-if="categoryForm.processing"
                            class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Crear
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <!-- Subcategory form -->
        <DialogModal :show="showSubcategoryFormModal" @close="showSubcategoryFormModal = false">
            <template #title> Crear subcategoría </template>
            <template #content>
                <form @submit.prevent="storeSubcategory" class="grid grid-cols-2 gap-3">
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
                        <el-select class="w-1/2" filterable v-model="subcategoryForm.category_id" clearable
                            placeholder="Seleccione" no-data-text="No hay opciones registradas"
                            no-match-text="No se encontraron coincidencias">
                            <el-option v-for="category in categories" :key="category" :label="category.name"
                                :value="category.id">
                                <p class="flex items-center justify-between">
                                    <span>{{ category.name }}</span>
                                    <span class="text-[10px] text-gray99">({{ category.key }})</span>
                                </p>
                            </el-option>
                        </el-select>
                        <InputError :message="subcategoryForm.errors.category_id" />
                    </div>
                    <div>
                        <InputLabel value="Nivel de subcategoría*" class="ml-3 mb-1" />
                        <el-input v-model="subcategoryForm.level" type="number"
                            placeholder="Indica el nivel de la subcategoría" :maxlength="5" required />
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
                    <CancelButton @click="showSubcategoryFormModal = false" :disabled="subcategoryForm.processing">
                        Cancelar
                    </CancelButton>
                    <PrimaryButton @click="storeSubcategory()" :disabled="subcategoryForm.processing">
                        <i v-if="subcategoryForm.processing"
                            class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
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
import ThirthButton from '@/Components/MyComponents/ThirthButton.vue';
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputFilePreview from "@/Components/MyComponents/InputFilePreview.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import FileView from '@/Components/MyComponents/FileView.vue';
import DialogModal from "@/Components/DialogModal.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';
import Loading from '@/Components/MyComponents/Loading.vue';

export default {
    data() {
        const form = useForm({
            name: this.product.name,
            category_id: this.product.subcategory?.category_id,
            subcategory_id: [], //se guarda un arreglo de los ids de subcategorías de forma secuencial
            description: this.product.description,
            features: this.product.features ?? [],
            imageCover: null,
            imageCoverCleared: false, //bandera para saber si se eliminó la imagen
            part_number: this.product.part_number, //numero de parte interno
            part_number_supplier: this.product.part_number_supplier, //numero de parte del fabricante
            location: this.product.location,
            line_cost: this.product.line_cost,
            bread_crumbles: this.product.bread_crumbles, //nombres de todas las subcategorías.
            media: null, //archivos del producto (descargables)
            deleteMedia: false, //elimina todos los archivos existentes
            features_keys: this.product.features_keys, //claves de caracteristicas en orden para formar #parte interno
            currency: this.product.currency, //claves de caracteristicas en orden para formar #parte interno
        });

        const categoryForm = useForm({
            name: null,
            key: null,
        });

        const subcategoryForm = useForm({
            name: null,
            key: null,
            level: null,
            features: null,
            category_id: null,
            subcategory_id: null,
        });

        const measureUnitForm = useForm({
            name: null,
        });
        return {

            //formularios
            form,
            categoryForm,
            subcategoryForm,
            measureUnitForm,

            //General
            showCategoryFormModal: false,
            showSubcategoryFormModal: false,
            showMeasureUnitFormModal: false,
            categoryInfo: null, //Información recuperada de categoría incluye subcategorías.
            highestLevel: null, //Nivel maximo de subcategoría subcategorías.
            loading: false, //estado de carga (fetchCategory).
            loadingFeatures: false,
            product_files: this.product.media
        }
    },
    components: {
        AppLayout,
        InputFilePreview,
        PrimaryButton,
        ThirthButton,
        FileUploader,
        CancelButton,
        DialogModal,
        InputLabel,
        InputError,
        FileView,
        Back,
        Loading,
    },
    props: {
        measure_units: Array,
        categories: Array,
        product: Object
    },
    methods: {
        update() {
            if (this.form.imageCover || this.form.media?.length) {
                this.form.transform((data) => ({
                    ...data,
                    features: data.features.map(feature => ({
                        name: feature.name,
                        value: feature.value,
                        measure_unit: feature.measure_unit
                    }))
                })).post(route("products.update-with-media", this.product.id), {
                    method: '_put',
                    onSuccess: () => {
                        // toast
                        this.$notify({
                            title: "Correcto",
                            message: "",
                            type: "success",
                            position: "bottom-right",
                        });
                    },
                });
            } else {
                this.form.transform((data) => ({
                    ...data,
                    features: data.features.map(feature => ({
                        name: feature.name,
                        value: feature.value,
                        measure_unit: feature.measure_unit
                    }))
                })).put(route("products.update", this.product.id), {
                    onSuccess: () => {
                        // toast
                        this.$notify({
                            title: "Correcto",
                            message: "",
                            type: "success",
                            position: "bottom-right",
                        });
                    },
                });
            }
        },
        storeCategory() {
            this.categoryForm.post(route("categories.store"), {
                onSuccess: () => {
                    // toast
                    this.$notify({
                        title: "Correcto",
                        message: "",
                        type: "success",
                        position: "bottom-right",
                    });
                    this.showCategoryFormModal = false;
                },
            });
        },
        storeSubcategory() {
            this.subcategoryForm.post(route("subcategories.store"), {
                onSuccess: () => {
                    // toast
                    this.$notify({
                        title: "Correcto",
                        message: "",
                        type: "success",
                        position: "bottom-right",
                    });
                    this.showSubcategoryFormModal = false;
                    location.reload();
                },
            });
        },
        storeMeasureUnit() {
            this.measureUnitForm.post(route("measure_units.store"), {
                onSuccess: () => {
                    // toast
                    this.$notify({
                        title: "Correcto",
                        message: "",
                        type: "success",
                        position: "bottom-right",
                    });
                    this.showMeasureUnitFormModal = false;
                },
            });
        },
        async handleChangeFeatureOption(featureIndex, value) {
            const key = this.form.features[featureIndex].options.find(item => item.name == value)?.key;

            if (key) {
                // Crear un arreglo para mantener las partes seleccionadas según el índice de la característica
                this.form.features_keys[featureIndex] = key;

                // Filtrar las partes seleccionadas y concatenarlas en orden
                const selectedKeys = this.form.features_keys.filter(Boolean).join('');

                // resetear numero de parte
                await this.generatePartNumber();
                // Concatenar el resultado al número de parte base
                this.form.part_number += selectedKeys;
            }
        },
        async fetchSubcategories() {
            this.loading = true;
            this.form.subcategory_id = [];
            this.form.features = [];
            try {
                const response = await axios.get(route('categories.fetch-subcategories', this.form.category_id));
                if (response.status === 200) {
                    this.categoryInfo = response.data.category;

                    // Encontrar el nivel más alto entre las subcategorías
                    // this.highestLevel = Math.max(...this.categoryInfo.subcategories.map(sub => sub.level));
                    this.highestLevel = 1; //para mostrar solo una subcategoría
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loading = false;
            }
        },
        saveFeatures(level, subcategory_id) {
            // Elimina la seleccion de la subcategoria siguiente
            this.form.subcategory_id.splice(level);
            this.form.bread_crumbles.splice(level);

            const nextLevelSubcategories = this.categoryInfo.subcategories.find(sb => sb.prev_subcategory_id === subcategory_id);
            if (nextLevelSubcategories) {
                this.highestLevel = nextLevelSubcategories.level;
            }

            // Si es el último nivel, guarda las características de la subcategoría
            if (level === this.highestLevel) {

                this.generatePartNumber(); //se crea el numero de parte interno.

                // Filtrar subcategoría que tienen el nivel más alto
                const highestLevelSubcategory = this.categoryInfo.subcategories.find(sub => sub.id === this.form.subcategory_id[level - 1]);

                //crea en la descripción con el breadCrumble agregando tambien el nombre de la categoría
                this.form.description = this.categoryInfo.name + ' ' + this.form.bread_crumbles.join(' ');

                if (highestLevelSubcategory && Array.isArray(highestLevelSubcategory.features)) {
                    // Crear un array de objetos con las características y unidad de medida asignando null a cada una
                    this.form.features = highestLevelSubcategory.features.map(feature => ({
                        name: feature.name,       // Nombre de la característica
                        value: null,              // Valor inicial de la característica
                        measure_unit: feature.measure_unit // Unidad de medida predefinida
                    }));
                } else {
                    // Si no hay características, inicializar features como un array vacío
                    this.form.features = [];
                }
            }
        },
        saveImage(image) {
            this.form.imageCover = image;
        },
        filterInput(event) {
            // Filtrar caracteres especiales dejando solo letras y números
            this.form.part_number_supplier = event.replace(/[^a-zA-Z0-9]/g, '');
        },
        async generatePartNumber() {
            try {
                const lastElement = this.form.subcategory_id[this.form.subcategory_id.length - 1];
                const response = await axios.post(route('products.get-consecutivo', lastElement));
                if (response.status === 200) {
                    // Obtener la categoría seleccionada
                    const categoryKey = this.categoryInfo.key;

                    // Concatenar los "key" de las subcategorías seleccionadas
                    const subcategoryKeys = this.form.subcategory_id.map(id => {
                        const subcategory = this.categoryInfo.subcategories.find(sub => sub.id === id);
                        return subcategory ? subcategory.key : '';
                    }).join('');

                    // Función para agregar ceros a la izquierda si es necesario
                    const nextConsecutivo = String(response.data.next_consecutivo).padStart(3, '0');

                    // Concatenar todos los "key" en un solo string
                    const partNumber = categoryKey + subcategoryKeys + '-' + nextConsecutivo;
                    this.form.part_number = partNumber;
                }
            } catch (error) {
                console.log(error);
            }
        },
        handleCreateSubcategory(index) {
            this.showSubcategoryFormModal = true;
            this.subcategoryForm.level = (index + 1);
            this.subcategoryForm.category_id = this.form.category_id;

            if (index > 0) {
                this.subcategoryForm.subcategory_id = this.form.subcategory_id[index - 1];
            }
        },
        //metodo que trae las subcategorias de la categoria seleccionada desde el mount para no borrar otras variables
        async fetchSubcategoriesMounted() {
            this.loadingFeatures = true;
            try {
                const response = await axios.get(route('categories.fetch-subcategories', this.form.category_id));
                if (response.status === 200) {
                    this.categoryInfo = response.data.category;

                    // Encontrar el nivel más alto entre las subcategorías
                    this.highestLevel = Math.max(...this.categoryInfo.subcategories.map(sub => sub.level));
                }
            } catch (error) {
                console.log(error);
            }
        },
        //metodo para setear los valores de subcategorias que tiene el producto
        findSubcategoriesSelected() {
            this.product.bread_crumbles.forEach(crumb => {
                const found = this.categoryInfo?.subcategories.find(subcategory => subcategory.name === crumb);
                if (found) {
                    this.form.subcategory_id.push(found.id);
                }
            });

            // agregar opciones a caracteristicas que sean de lista desplegable
            const highestLevelSubcategory = this.categoryInfo.subcategories.find(sub => sub.level == this.highestLevel)
            const options = highestLevelSubcategory.features.map(feature => ({ options: feature.options }));

            if ( options ) {
                this.form.features.forEach((element, index) => {
                        element.options = options[index]?.options
                });
            }
            this.loadingFeatures = false;
        },
        removeFile(file_id) {
            // Encuentra el índice del archivo en el arreglo form.media
            const index = this.product_files.findIndex(file => file.id === file_id);

            // Si el archivo se encuentra en el arreglo, elimínalo usando splice
            if (index !== -1) {
                this.product_files.splice(index, 1);
            }
        },
    },
    async created() {
        await this.fetchSubcategoriesMounted();
        this.findSubcategoriesSelected();
    }
}
</script>
