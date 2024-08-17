<template>
    <AppLayout title="Crear categoría">
        <div class="px-3 md:px-16 py-8">
            <Back :to="route('settings.index', { currentTab: 1 })" />

            <form @submit.prevent="store"
                class="rounded-lg border border-grayD9 lg:p-5 p-3 lg:w-2/3 xl:w-1/2 mx-auto mt-2">
                <h1 class="font-bold ml-2 col-span-full">Crear categoría</h1>

                <section class="my-3 grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Nombre de la categoría principal*" />
                        <div class="flex items-center space-x-3">
                            <el-input v-model="form.category" placeholder="Ej. Automatización" :maxlength="255"
                                clearable />
                            <el-tooltip content="Agregar imagen" placement="top">
                                <button type="button" @click="openFileExplorer"
                                    class="hover:text-primary disabled:opacity-50 disabled:hover:text-black disabled:cursor-not-allowed"
                                    :disabled="imageUrl">
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
                        <div v-if="imageUrl" class="mt-2">
                            <figure class="size-32 border border-grayD9 rounded-[3px] relative">
                                <button @click="removeImage" class="absolute p-1 top-1 right-1 z-10 text-xs">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                                <img :src="imageUrl" alt="Image Preview" class="size-32 object-contain opacity-50" />
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
                        @openFeaturesModal="openFeaturesModal" />
                </div>
                <div class="col-span-full text-right mt-7">
                    <PrimaryButton class="!rounded-full"
                        :disabled="form.processing || subcategoryNameEmpty(form.subCategories)">
                        <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Crear categoría
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
                <article
                    class="border border-grayD9 rounded-[3px] px-3 py-3 min-h-28 max-h-56 mx-auto flex flex-col justify-between space-y-4">
                    <div class="flex items-center justify-between">
                        <p class="text-black">Todas las características</p>
                        <button type="button" @click="showNewFeatureModal = true" class="text-primary text-sm">
                            <i class="fa-solid fa-circle-plus"></i>
                        </button>
                    </div>
                    <div v-if="getAvailableFeatures.length" class="overflow-auto flex items-center flex-wrap">
                        <button type="button" @click="addFeature(item)" v-for="(item, index) in getAvailableFeatures"
                            :key="index"
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
                <article class="mt-4">
                    <div class="grid grid-cols-10 gap-x-4 text-gray37 *:ml-2">
                        <p class="col-span-3">Característica</p>
                        <p class="col-span-3">Formato</p>
                        <p class="flex items-center justify-between col-span-3">
                            <span>Unidad de medida</span>
                            <button type="button" @click="showNewUnitModal = true" class="text-primary text-sm">
                                <i class="fa-solid fa-circle-plus"></i>
                            </button>
                        </p>
                    </div>
                    <div v-if="localFeatures.length" class="space-y-2">
                        <div v-for="(item, index) in localFeatures" :key="index"
                            class="grid grid-cols-10 gap-x-3 gap-y-2 relative">
                            <el-input v-model="item.name" class="col-span-3" disabled />
                            <div class="relative col-span-3">
                                <select @change="handleChangeFormat(index)" v-model="item.format"
                                    plceholder="Selecciona"
                                    class="text-sm w-full text-black h-9 pl-7 border-grayD9 rounded-[5px] outline-none focus:ring-0 focus:border-primary transition-all duration-100">
                                    <option value="Texto libre">Texto libre</option>
                                    <option value="Lista desplegable">Lista desplegable</option>
                                </select>
                                <svg v-if="item.format == 'Texto libre'" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                    class="size-4 text-black absolute top-[10px] left-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 9h16.5m-16.5 6.75h16.5" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor"
                                    class="size-4 text-black absolute top-[10px] left-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m9 12.75 3 3m0 0 3-3m-3 3v-7.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <select v-model="item.measure_unit" plceholder="Selecciona"
                                class="col-span-3 text-black text-sm h-9 border-grayD9 rounded-[5px] outline-none focus:ring-0 focus:border-primary transition-all duration-100">
                                <option value="No aplica">No aplica</option>
                                <option v-for="mu in measure_units" :key="mu.id" :value="mu.name">{{ mu.name }}
                                </option>
                            </select>
                            <button @click="removeLocalFeature(index)" type="button" class="text-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                            <div v-if="item.format == 'Lista desplegable'"
                                class="grid grid-cols-3 gap-3 col-span-9 border border-grayD9 rounded-[3px] px-2 py-1">
                                <p class="flex items-center justify-between col-span-full">
                                    <span class="text-gray37">Lista de opciones para característica <b>{{ item.name
                                            }}</b></span>
                                    <span v-if="hasEmptyOptions(index)" class="ml-6 text-yellow-600 text-xs">
                                        No dejar ningún campo vacío
                                    </span>
                                </p>
                                <div v-for="(option, index2) in item.options" :key="index"
                                    class="flex items-center space-x-2">
                                    <el-input v-model="option.name" :placeholder="`Opción ${index2 + 1}`"
                                        class="!w-[65%]" />
                                    <el-input v-model="option.key" placeholder="Clave" class="!w-[30%]" />
                                    <button @click="removeOption(index, index2)" type="button"
                                        class="w-[5%] text-black">x</button>
                                </div>
                                <div class="col-span-full">
                                    <button @click="addOption(index)" type="button"
                                        class="text-sm text-primary text-start">
                                        + Agregar opción
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-3 pb-3 border-b border-grayD9">
                        <p class="text-secondary flex items-center space-x-4">
                            <span>Da clic a alguna característica para agregar</span>
                            <i class="fa-solid fa-arrow-up-long"></i>
                        </p>
                    </div>
                </article>
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
                    <CancelButton @click="showFeaturesModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="storeFeature" :disabled="featureForm.processing">Crear</PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <!-- agregar nueva unidad de medida -->
        <DialogModal :show="showNewUnitModal" @close="showNewUnitModal = false" maxWidth="2xl">
            <template #title>
                <h1>Crear Unidad de medida</h1>
            </template>
            <template #content>
                <form @submit.prevent="storeUnit" class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Nombre de la unidad de medida*" />
                        <el-input v-model="unitForm.name" placeholder="Ej. Metro" :maxlength="255" clearable />
                        <InputError :message="unitForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Abreviación*" />
                        <el-input v-model="unitForm.abreviation" placeholder="Ej. m" :maxlength="10" clearable />
                        <InputError :message="unitForm.errors.abreviation" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex items-center space-x-1">
                    <PrimaryButton @click="storeUnit" :disabled="unitForm.processing">Crear</PrimaryButton>
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
import CancelButton from '@/Components/MyComponents/CancelButton.vue';

export default {
    data() {
        const form = useForm({
            category: null,
            key: null,
            image: null,
            subCategories: [{ name: '', subCategories: [], image: null, features: [] }],
        });

        const featureForm = useForm({
            name: null,
        });

        const unitForm = useForm({
            name: null,
            abreviation: null,
        });

        return {
            //formularios
            form,
            featureForm,
            unitForm,
            // modales
            showFeaturesModal: false,
            showNewFeatureModal: false,
            showNewUnitModal: false,
            // caracteristicas
            elementFeaturesPath: '',
            localFeatures: [],
            // generales
            imageUrl: null,
        }
    },
    components: {
        AppLayout,
        InputFilePreview,
        PrimaryButton,
        CancelButton,
        InputLabel,
        InputError,
        Back,
        SubCategory,
        DialogModal,
    },
    props: {
        features: Array,
        measure_units: Array,
    },
    computed: {
        getAvailableFeatures() {
            return this.features.filter(feature => {
                return !this.localFeatures.some(localFeature => localFeature.name === feature.name);
            });
        },
    },
    methods: {
        store() {
            this.form.post(route('categories.store-with-subcategories'), {
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
        hasEmptyOptions(index) {
            return this.localFeatures[index].options.some(option => !option.name || !option.key);
        },
        handleChangeFormat(index) {
            if (this.localFeatures[index].format == 'Texto libre') {
                this.localFeatures[index].options = [{ name: null, key: null }];
            }
        },
        addOption(index) {
            this.localFeatures[index].options.push({ name: null, key: null });
        },
        removeOption(index, index2) {
            this.localFeatures[index].options.splice(index2, 1);
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
                this.imageUrl = URL.createObjectURL(file);
                this.form.image = file;
            }
        },
        removeImage() {
            this.imageUrl = null;
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
        // funciones de modal para agregar cracteristicas
        openFeaturesModal(path) {
            // buscar si la subcategoria del path ya tiene caracteristicas cargadas
            const indexes = path.split('.').map(i => parseInt(i) - 1);
            let subCategories = this.form.subCategories;
            this.localFeatures = [];

            indexes.forEach((index, idx) => {
                if (idx === indexes.length - 1) {
                    if (subCategories[index].features.length) {
                        this.localFeatures = JSON.parse(JSON.stringify(subCategories[index].features));
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
        storeUnit() {
            this.unitForm.post(route('measure_units.store'), {
                onSuccess: () => {
                    this.unitForm.reset();
                    this.showNewUnitModal = false;
                },
                onError: (errors) => {
                    console.log(errors);
                }
            });
        },
        addFeature(feature) {
            this.localFeatures.push(
                { name: feature.name, measure_unit: 'No aplica', format: 'Texto libre', options: [{ name: null, key: null }] }
            );
        },
        addAllFeatures() {
            this.getAvailableFeatures.forEach(item => {
                this.addFeature(item);
            });
        },
        removeLocalFeature(index) {
            this.localFeatures.splice(index, 1);
        }
    }
}
</script>