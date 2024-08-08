<template>
    <AppLayout title="Editar usuario">
        <div class="px-3 md:px-16 py-8">
            <Back />

            <form @submit.prevent="update"
                class="rounded-lg border border-grayD9 lg:p-5 p-3 lg:w-2/3 xl:w-1/2 mx-auto mt-2 lg:grid lg:grid-cols-2 gap-3">
                <h1 class="font-bold ml-2 col-span-full">Editar usuario</h1>
                <div>
                    <InputLabel value="Nombre del usuario*" />
                    <el-input v-model="form.name" placeholder="Ej. Karla Figueroa" :maxlength="100" clearable />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel value="Puesto*" />
                    <el-input v-model="form.org_props.position" placeholder="Ej. Administración" :maxlength="100"
                        clearable />
                    <InputError :message="form.errors['org_props.position']" />
                </div>
                <div>
                    <InputLabel value="Correo electrónico*" />
                    <el-input v-model="form.email" placeholder="Ej. admin@adti.com" :maxlength="100" clearable />
                    <InputError :message="form.errors.email" />
                </div>
                <div>
                    <InputLabel value="Teléfono" />
                    <el-input v-model="form.phone" placeholder="Ingresa el número de teléfono"
                        :formatter="(value) => `${value}`.replace(/(\d{2})(\d{4})(\d{4})/, '$1 $2 $3')"
                        :parser="(value) => value.replace(/\D/g, '')" maxlength="10" clearable />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="col-span-full">
                    <InputLabel value="Foto del usuario" />
                    <InputFilePreview @imagen="saveImage($event)" @cleared="clearImage"
                        :imageUrl="user.profile_photo_url" />
                </div>
                <hr class="col-span-full border-grayD9 my-3">
                <div class="col-span-full">
                    <InputLabel>
                        <span>Rol de usuario*</span>
                        <el-tooltip placement="top">
                            <template #content>
                                Seleccione un rol para este usuario dentro del <br>
                                sistema para definir sus permisos y accesos
                            </template>
                            <i class="fa-regular fa-circle-question text-primary text-[10px] ml-2"></i>
                        </el-tooltip>
                    </InputLabel>
                    <div class="col-span-full grid grid-cols-2 lg:grid-cols-3 gap-2">
                        <InputLabel v-for="role in roles" :key="role.id" class="flex items-center">
                            <input type="checkbox" v-model="form.roles" :value="role.id"
                                class="rounded text-primary shadow-sm focus:ring-primary bg-transparent" />
                            <span class="ml-2 text-sm">{{ role.name }}</span>
                        </InputLabel>
                    </div>
                    <InputError :message="form.errors.roles" />
                </div>
                <div class="col-span-full text-right mt-7">
                    <PrimaryButton class="!rounded-full" :disabled="form.processing">
                        <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Guardar cambios
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputFilePreview from "@/Components/MyComponents/InputFilePreview.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { useForm } from "@inertiajs/vue3";

export default {
    data() {
        const form = useForm({
            name: this.user.name,
            org_props: {
                position: this.user.org_props.position
            },
            email: this.user.email,
            phone: this.user.phone,
            image: null,
            roles: this.user_roles,
            selectedImage: this.user.profile_photo_url
        });

        return {
            //formularios
            form,
        }
    },
    components: {
        AppLayout,
        InputFilePreview,
        PrimaryButton,
        InputLabel,
        InputError,
        Back,
    },
    props: {
        roles: Array,
        user: Object,
        user_roles: Array,
    },
    methods: {
        update() {
            if (this.form.image) {
                this.form.post(route("users.update-with-media", this.user.id), {
                    method: '_put',
                    onSuccess: () => {
                        this.$notify({
                            title: "Correcto",
                            message: "",
                            type: "success",
                        });

                    },
                });
            } else {
                this.form.put(route("users.update", this.user.id), {
                    onSuccess: () => {
                        this.$notify({
                            title: "Correcto",
                            message: "",
                            type: "success",
                        });

                    },
                });
            }
        },
        saveImage(image) {
            this.form.image = image;
            console.log(this.form.image)
        },
        clearImage() {
            this.form.image = null;

            // se eliminó la foto
            this.form.selectedImage = null;
        },
    },
}
</script>