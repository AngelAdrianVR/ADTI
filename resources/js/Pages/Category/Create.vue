<template>
    <AppLayout title="Crear categoría">
        <div class="px-3 md:px-16 py-8">
            <Back :to="route('settings.index', { currentTab: 3 })" />

            <form @submit.prevent="store" class="rounded-lg border border-grayD9 lg:p-5 p-3 lg:w-1/2 mx-auto mt-2">
                <h1 class="font-bold ml-2 col-span-full">Crear categoría</h1>
                <div class="my-3">
                    <InputLabel value="Nombre de la categoría principal*" />
                    <div class="flex items-center space-x-4">
                        <el-input v-model="form.name" placeholder="Ej. Automatización" :maxlength="255" clearable />
                        <div class="flex items-center space-x-2">
                            <el-tooltip content="Agregar imagen" placement="top">
                                <button type="button" class="hover:text-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                </button>
                            </el-tooltip>
                        </div>
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <br>
                <hr class="col-span-full border-grayD9">
                <br>

                <h1 class="font-bold ml-2 mb-2 col-span-full">Subcategorías</h1>
                <div class="space-y-2">
                    <SubCategory v-for="(subCategory, index) in form.subCategories" :key="index"
                        :subCategory="subCategory" :index="index" :parentIndex="''" @addSubCategory="addSubCategory"
                        @removeSubCategory="removeSubCategory" @imageUploaded="handleImageUploaded"
                        @openFeaturesModal="openFeaturesModal" />
                </div>

                <br>
                <hr class="col-span-full border-grayD9">
                <br>

                <button type="button" @click="addMainSubCategory" class="text-primary">+ Agregar subcategoría</button>

                <div class="col-span-full text-right mt-7">
                    <PrimaryButton class="!rounded-full" :disabled="form.processing">
                        <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Crear categoría
                    </PrimaryButton>
                </div>
            </form>
        </div>
        <div class="text-center mb-2">
        </div>

        <DialogModal :show="showFeaturesModal" @close="showFeaturesModal = false">
            <template #title>
                <h1>Agregar características</h1>
            </template>
            <template #content>
                <section class="grid grid-cols-2 gap-x-3">
                    <article>
                        <div class="grid grid-cols-2 gap-x-4 text-gray37">
                            <p>Característica</p>
                            <p>Unidad de medida</p>
                        </div>

                    </article>
                    <article class="border border-grayD9 rounded-[3px] px-4 py-3 min-h-28 max-h-56">
                        <div class="flex items-center justify-between h-[15%]">
                            <p class="text-black">Todas las características</p>
                            <button @click="addNewFeatures" class="text-primary text-sm">
                                <i class="fa-solid fa-circle-plus"></i>
                            </button>
                        </div>
                        <div class="h-[70%] overflow-auto">
                            
                        </div>
                        <div>
                            <button class="text-primary underline h-[15%]">Agregar todas</button>
                        </div>
                    </article>
                </section>
            </template>
            <template #footer>
                <div class="flex items-center space-x-1">
                    <PrimaryButton @click="handleFeatureSaved">Agregar</PrimaryButton>
                </div>
            </template>
        </DialogModal>
        <!-- {{ form.subCategories }} <br><br> -->
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
            subCategories: [{ name: '', subCategories: [], image: null, features: [] }],
        });

        return {
            //formularios
            form,
            // modales
            showFeaturesModal: false,
            // caracteristicas
            elementFeaturesPath: '',
            features: [],

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
    },
    methods: {
        store() {
            this.form.post(this.route('categories.store'), {
                onSuccess: () => {
                    this.form.reset();
                },
                onError: (errors) => {
                    console.log(errors);
                }
            });
        },
        addMainSubCategory() {
            this.form.subCategories.push({ name: '', subCategories: [], image: null, features: [] });
        },
        addSubCategory(path) {
            const indexes = path.split('.').map(i => parseInt(i) - 1);
            let subCategories = this.form.subCategories;

            indexes.forEach((index, idx) => {
                if (idx === indexes.length - 1) {
                    subCategories[index].subCategories.push({
                        name: '',
                        subCategories: [],
                        image: null,
                        features: []
                    });
                } else {
                    subCategories = subCategories[index].subCategories;
                }
            });
        },
        removeSubCategory(parentPath, index) {
            const indexes = parentPath ? parentPath.split('.').map(i => parseInt(i) - 1) : [];
            let subCategories = this.form.subCategories;

            indexes.forEach((idx, i) => {
                if (i === indexes.length - 1) {
                    subCategories[idx].subCategories.splice(index, 1);
                } else {
                    subCategories = subCategories[idx].subCategories;
                }
            });

            if (indexes.length === 0) {
                this.form.subCategories.splice(index, 1);
            }
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
        openFeaturesModal(path) {
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
                    subCategories[index].features.push(this.features);
                } else {
                    subCategories = subCategories[index].subCategories;
                }
            });
        },
    }
}
</script>