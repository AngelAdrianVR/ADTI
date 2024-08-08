<template>
    <AppLayout title="Editar categoría">
        <div class="px-3 md:px-16 py-8">
            <Back :to="route('settings.index', { currentTab: 1 })" />

            <form @submit.prevent="update" class="rounded-lg border border-grayD9 lg:p-5 p-3 lg:w-2/3 xl:w-1/2 mx-auto mt-2">
                <h1 class="font-bold ml-2 col-span-full">Editar categoría</h1>

                <section class="my-3 grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Nombre de la categoría principal*" />
                        <div class="flex items-center space-x-3">
                            <el-input v-model="form.category" placeholder="Ej. Automatización" :maxlength="255"
                                clearable />
                            <el-tooltip content="Agregar imagen" placement="top">
                                <button type="button" @click="openFileExplorer"
                                    class="hover:text-primary disabled:opacity-50 disabled:hover:text-black disabled:cursor-not-allowed"
                                    :disabled="form.image">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                </button>
                            </el-tooltip>
                        </div>
                        <InputError :message="form.errors.category" />
                        <!-- imagen de categoria -->
                        <input type="file" ref="fileInput" accept="image/*" @change="onImageChange" class="hidden" />
                        <div v-if="form.image" class="mt-2">
                            <figure class="size-32 border border-grayD9 rounded-[3px] relative">
                                <button @click="removeImage" class="absolute p-1 top-1 right-1 z-10 text-xs">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                                <img :src="getImageUrl" alt="Image Preview" class="size-32 object-contain opacity-50" />
                            </figure>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Clave de la categoría*" />
                        <el-input v-model="form.key" placeholder="Ej. AUT" :maxlength="5" clearable />
                        <InputError :message="form.errors.key" />
                    </div>
                </section>

                <br>
                <hr class="col-span-full border-grayD9">
                <br>

                <h1 class="font-bold ml-2 mb-2 col-span-full flex items-center space-x-3">
                    <span>Subcategorías</span>
                    <button type="button" @click="addMainSubCategory" class="text-primary text-sm font-normal">
                        + Agregar subcategoría
                    </button>
                </h1>
                <div class="space-y-2">
                    <SubCategory v-for="(subCategory, index) in form.subCategories" :key="index"
                        :subCategory="subCategory" :index="index" :parentIndex="''" @addSubCategory="addSubCategory"
                        @removeSubCategory="removeSubCategory" @imageUploaded="handleImageUploaded"
                        @removeFeatures="removeFeatures" @openFeaturesModal="openFeaturesModal" />
                </div>
                <div class="col-span-full text-right mt-7">
                    <PrimaryButton class="!rounded-full"
                        :disabled="form.processing || subcategoryNameEmpty(form.subCategories)">
                        <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Guardar cambios
                    </PrimaryButton>
                </div>
            </form>
        </div>
        <div class="text-center mb-2">
        </div>

        <!-- modal de caracteristicas -->
        <DialogModal :show="showFeaturesModal" @close="showFeaturesModal = false">
            <template #title>
                <h1>Agregar características</h1>
            </template>
            <template #content>
                <section class="flex space-x-10 items-start">
                    <article class="w-[60%]">
                        <div class="grid grid-cols-2 gap-x-4 text-gray37 *:ml-2">
                            <p>Característica</p>
                            <p>Unidad de medida</p>
                        </div>
                        <div v-if="localFeatures.length" class="space-y-2">
                            <div v-for="(item, index) in localFeatures" :key="index"
                                class="grid grid-cols-2 gap-3 relative">
                                <el-input v-model="item.name" disabled />
                                <el-select filterable v-model="item.measure_unit" placeholder="Selecciona"
                                    no-data-text="No hay opciones registradas"
                                    no-match-text="No se encontraron coincidencias">
                                    <el-option label="No aplica" value="No aplica" />
                                    <el-option v-for="mu in measure_units" :key="mu.id" :label="mu.name"
                                        :value="mu.name" />
                                </el-select>
                                <button @click="removeLocalFeature(index)" type="button"
                                    class="text-primary absolute top-2 -right-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-else class="mt-3 pb-3 border-b border-grayD9">
                            <p class="text-secondary flex items-center space-x-4">
                                <span>Da clic a alguna característica para agregar</span>
                                <i class="fa-solid fa-arrow-right-long"></i>
                            </p>
                        </div>
                    </article>
                    <article
                        class="border border-grayD9 rounded-[3px] px-3 py-3 min-h-28 max-h-56 w-[40%] flex flex-col justify-between space-y-4">
                        <div class="flex items-center justify-between">
                            <p class="text-black">Todas las características</p>
                            <button type="button" @click="showNewFeatureModal = true" class="text-primary text-sm">
                                <i class="fa-solid fa-circle-plus"></i>
                            </button>
                        </div>
                        <div v-if="getAvailableFeatures.length" class="overflow-auto flex items-center flex-wrap">
                            <button type="button" @click="addFeature(item)"
                                v-for="(item, index) in getAvailableFeatures" :key="index"
                                class="border border-dashed rounded-[3px] border-secondary text-secondary mb-2 mx-1 px-2 py-1 hover:text-[#077B04] hover:border-[#077B04] hover:bg-[#C0FDBF]">
                                {{ item.name }}
                            </button>
                        </div>
                        <el-empty v-else description="Vacío" :image-size="80" />
                        <div>
                            <button type="button" @click="addAllFeatures" v-if="getAvailableFeatures.length"
                                class="text-primary underline">
                                Agregar todas
                            </button>
                        </div>
                    </article>
                </section>
            </template>
            <template #footer>
                <div class="flex items-center space-x-1">
                    <PrimaryButton @click="handleFeatureSaved" :disabled="!localFeatures.length">Agregar</PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <!-- agregar nueva caracteristica -->
        <DialogModal :show="showNewFeatureModal" @close="showNewFeatureModal = false" maxWidth="2xl">
            <template #title>
                <h1>Crear característica</h1>
            </template>
            <template #content>
                <form @submit.prevent="storeFeature">
                    <div>
                        <InputLabel value="Nombre de la característica*" />
                        <el-input v-model="featureForm.name" placeholder="Ej. Volumen" :maxlength="255" clearable />
                        <InputError :message="featureForm.errors.name" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex items-center space-x-1">
                    <PrimaryButton @click="storeFeature" :disabled="featureForm.processing">Crear</PrimaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import DialogModal from "@/Components/DialogModal.vue";
import InputFilePreview from "@/Components/MyComponents/InputFilePreview.vue";
import SubCategory from "@/Components/MyComponents/Category/SubCategory.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { useForm } from "@inertiajs/vue3";

export default {
    data() {
        const form = useForm({
            category: null,
            key: null,
            image: null,
            subCategories: [{ id: null, name: '', subCategories: [], image: null, features: [] }],
        });

        const featureForm = useForm({
            name: null,
        });

        return {
            //formularios
            form,
            featureForm,
            // modales
            showFeaturesModal: false,
            showNewFeatureModal: false,
            // caracteristicas
            elementFeaturesPath: '',
            localFeatures: [],
            // generales
        }
    },
    components: {
        AppLayout,
        InputFilePreview,
        PrimaryButton,
        InputLabel,
        InputError,
        Back,
        SubCategory,
        DialogModal,
    },
    props: {
        features: Array,
        measure_units: Array,
        category: Object,
    },
    computed: {
        getAvailableFeatures() {
            return this.features.filter(feature => {
                return !this.localFeatures.some(localFeature => localFeature.name === feature.name);
            });
        },
        getImageUrl() {
            return URL.createObjectURL(this.form.image);
        }
    },
    methods: {
        async urlToFile(url, filename, mimeType) {
            const response = await fetch(url);
            const buffer = await response.arrayBuffer();
            return new File([buffer], filename, { type: mimeType });
        },
        async transformData(data) {
            // Inicializa la categoría principal
            this.form.category = data.name;
            this.form.key = data.key;

            // Convertir la imagen principal si existe
            if (data.media.length) {
                const media = data.media[0];
                this.form.image = await this.urlToFile(media.original_url, media.file_name, media.mime_type);
            } else {
                this.image = null;
            }

            // Organiza las subcategorías
            const map = new Map();

            for (const subcategory of data.subcategories) {
                const subCategoryData = {
                    id: subcategory.id,
                    name: subcategory.name,
                    subCategories: [],
                    image: null,
                    features: subcategory.features || [],
                };

                // Convertir la imagen de la subcategoría si existe
                if (subcategory.media.length) {
                    const media = subcategory.media[0];
                    subCategoryData.image = await this.urlToFile(media.original_url, media.file_name, media.mime_type);
                }

                map.set(subcategory.id, subCategoryData);
            }

            const subCategories = [];

            data.subcategories.forEach(subcategory => {
                if (subcategory.prev_subcategory_id) {
                    const parent = map.get(subcategory.prev_subcategory_id);
                    if (parent) {
                        parent.subCategories.push(map.get(subcategory.id));
                    }
                } else {
                    subCategories.push(map.get(subcategory.id));
                }
            });

            this.form.subCategories = subCategories;
        },
        update() {
            this.form.post(route("categories.update-with-subcategories", this.category.id), {
                method: '_put',
                onSuccess: () => {
                    this.$notify({
                        title: "Correcto",
                        message: "",
                        type: "success",
                    });

                },
                onError: (errors) => {
                    console.log(errors);
                }
            });
        },
        subcategoryNameEmpty(subCategories) {
            for (let subCategory of subCategories) {
                if (!subCategory.name) {
                    return true;
                }
                if (subCategory.subCategories && subCategory.subCategories.length > 0) {
                    if (this.subcategoryNameEmpty(subCategory.subCategories)) {
                        return true;
                    }
                }
            }
            return false;
        },
        openFileExplorer() {
            this.$refs.fileInput.click();
        },
        onImageChange(event) {
            const file = event.target.files[0];
            if (file) {
                this.form.image = file;
            }
        },
        removeImage() {
            this.form.image = null;
        },
        addMainSubCategory() {
            this.form.subCategories.push({ name: '', subCategories: [], image: null, features: [] });
        },
        addSubCategory(path, data = null) {
            const indexes = path.split('.').map(i => parseInt(i) - 1);
            let subCategories = this.form.subCategories;
            if (!data) {
                data = {
                    name: '',
                    subCategories: [],
                    image: null,
                    features: []
                };
            }

            indexes.forEach((index, idx) => {
                if (idx === indexes.length - 1) {
                    subCategories[index].subCategories.push(data);
                } else {
                    subCategories = subCategories[index].subCategories;
                }
            });
        },
        removeSubCategory(path) {
            const indexes = path.split('.').map(i => parseInt(i) - 1);
            let subCategories = this.form.subCategories;

            indexes.forEach((index, idx) => {
                if (idx === indexes.length - 1) {
                    subCategories.splice(index, 1);
                } else {
                    subCategories = subCategories[index].subCategories;
                }
            });
        },
        handleImageUploaded(file, path) {
            const indexes = path.split('.').map(i => parseInt(i) - 1);
            let subCategories = this.form.subCategories;

            indexes.forEach((index, idx) => {
                if (idx === indexes.length - 1) {
                    subCategories[index].image = file;
                } else {
                    subCategories = subCategories[index].subCategories;
                }
            });
        },
        removeFeatures(path) {
            const indexes = path.split('.').map(i => parseInt(i) - 1);
            let subCategories = this.form.subCategories;

            indexes.forEach((index, idx) => {
                if (idx === indexes.length - 1) {
                    subCategories[index].features = [];
                } else {
                    subCategories = subCategories[index].subCategories;
                }
            });
        },
        // funciones de modal para agregar cracteristicas
        openFeaturesModal(path) {
            // buscar si la subcategoria del path ya tiene caracteristicas cargadas
            const indexes = path.split('.').map(i => parseInt(i) - 1);
            let subCategories = this.form.subCategories;

            indexes.forEach((index, idx) => {
                if (idx === indexes.length - 1) {
                    if (subCategories[index].features.length) {
                        this.localFeatures = subCategories[index].features;
                    } else {
                        this.localFeatures = [];
                    }
                } else {
                    subCategories = subCategories[index].subCategories;
                }
            });

            this.elementFeaturesPath = path;
            this.showFeaturesModal = true;
        },
        handleFeatureSaved() {
            const indexes = this.elementFeaturesPath.split('.').map(i => parseInt(i) - 1);
            let subCategories = this.form.subCategories;

            indexes.forEach((index, idx) => {
                if (idx === indexes.length - 1) {
                    if (!subCategories[index].features) {
                        subCategories[index].features = [];
                    }
                    subCategories[index].features = this.localFeatures;
                } else {
                    subCategories = subCategories[index].subCategories;
                }
            });

            this.showFeaturesModal = false;
        },
        storeFeature() {
            this.featureForm.post(route('features.store'), {
                onSuccess: () => {
                    this.featureForm.reset();
                    this.showNewFeatureModal = false;
                },
                onError: (errors) => {
                    console.log(errors);
                }
            });
        },
        addFeature(feature) {
            this.localFeatures.push({ name: feature.name, measure_unit: 'No aplica' });
        },
        addAllFeatures() {
            this.getAvailableFeatures.forEach(item => {
                this.addFeature(item);
            });
        },
        removeLocalFeature(index) {
            this.localFeatures.splice(index, 1);
        }
    },
    async mounted() {
        await this.transformData(this.category);
    }
}
</script>