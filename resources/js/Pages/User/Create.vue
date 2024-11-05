<template>
    <AppLayout title="Nuevo usuario">
        <div class="px-3 md:px-16 py-8">
            <Back :to="route('users.index')" />

            <form @submit.prevent="store" class="rounded-lg border border-grayD9 lg:p-5 p-3 lg:w-2/3 xl:w-1/2 mx-auto mt-2 lg:grid lg:grid-cols-2 gap-x-3 gap-y-2">
                <h1 class="font-bold ml-2 col-span-full">Crear usuario</h1>
                <div class="col-span-full text-sm px-2 py-1 bg-[#F2F2F2] rounded-sm text-[#6D6E72] flex items-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-primary">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <span></span>La contraseña por defecto es <b>123456</b>. Esta puede cambiarla el usuario desde su perfil.
                </div>

                <h2 class="font-bold mb-1 mt-2 col-span-full">Datos personales</h2>
                <div>
                    <InputLabel value="Nombre del usuario*" />
                    <el-input v-model="form.name" placeholder="Ej. Karla Figueroa" :maxlength="100" clearable />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel value="Correo electrónico personal*" />
                    <el-input v-model="form.email" placeholder="Ej. karla@gmail.com" :maxlength="100" clearable />
                    <InputError :message="form.errors.email" />
                </div>
                <div>
                    <InputLabel value="Teléfono" />
                    <el-input v-model="form.phone" placeholder="Ingresa el número de teléfono"
                        :formatter="(value) => `${value}`.replace(/(\d{2})(\d{4})(\d{4})/, '$1 $2 $3')"
                        :parser="(value) => value.replace(/\D/g, '')" maxlength="10" clearable />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="w-full">
                    <InputLabel value="Fecha de nacimiento" />
                    <el-date-picker
                        v-model="form.birthdate"
                        class="!w-full"
                        type="date"
                        placeholder="Selecciona la fecha de nacimiento"
                        :size="size"
                    />
                    <InputError :message="form.errors.birthdate" />
                </div>
                <div>
                    <InputLabel value="Estado civil" />
                    <el-select class="w-1/2" filterable v-model="form.civil_state"
                        placeholder="Seleccione" no-data-text="No hay opciones registradas"
                        no-match-text="No se encontraron coincidencias">
                        <el-option v-for="item in civilStates" :key="item" :label="item" :value="item" />
                    </el-select>
                    <InputError :message="form.errors.civil_state" />
                </div>
                <div>
                    <InputLabel value="Domicilio" />
                    <el-input v-model="form.address" placeholder="Ingresa el domicilio del usuario" :maxlength="255" clearable />
                    <InputError :message="form.errors.address" />
                </div>
                <div>
                    <InputLabel value="FRC" />
                    <el-input v-model="form.rfc" placeholder="Ingresa el rfc del usuario" :maxlength="100" clearable />
                    <InputError :message="form.errors.rfc" />
                </div>
                <div>
                    <InputLabel value="CURP" />
                    <el-input v-model="form.rfc" placeholder="Ingresar curp del usuario" :maxlength="100" clearable />
                    <InputError :message="form.errors.rfc" />
                </div>
                <div>
                    <InputLabel value="Número de seguro social" />
                    <el-input v-model="form.ssn" placeholder="Ingresar el nss del usuario" :maxlength="100" clearable />
                    <InputError :message="form.errors.ssn" />
                </div>

                <!-- Datos laborales -->
                <h2 class="font-bold mt-3 col-span-full">Datos laborales</h2>
                <div class="w-full">
                    <InputLabel value="Fecha de ingreso*" />
                    <el-date-picker
                        v-model="form.org_props.entry_date"
                        class="!w-full"
                        type="date"
                        placeholder="Selecciona la fecha de ingreso"
                        :size="size"
                    />
                    <InputError :message="form.errors['org_props.entry_date']" />
                </div>
                <div>
                    <InputLabel value="Puesto*" />
                    <el-input v-model="form.org_props.position" placeholder="Ej. Administración" :maxlength="100" clearable />
                    <InputError :message="form.errors['org_props.position']" />
                </div>
                <div>
                    <InputLabel value="Departamento" />
                    <el-input v-model="form.org_props.department" placeholder="Ej. Producción" :maxlength="100" clearable />
                    <InputError :message="form.errors['org_props.department']" />
                </div>
                <div>
                    <InputLabel value="Correo electrónico empresarial*" />
                    <el-input v-model="form.org_props.email" placeholder="ingresa el correo empresarial del usuario" :maxlength="100" clearable />
                    <InputError :message="form.errors['org_props.email']" />
                </div>
                <div>
                    <InputLabel value="Teléfono empresarial*" />
                    <el-input v-model="form.org_props.phone" placeholder="Ingresa el número de teléfono empresarial del usuario"
                        :formatter="(value) => `${value}`.replace(/(\d{2})(\d{4})(\d{4})/, '$1 $2 $3')"
                        :parser="(value) => value.replace(/\D/g, '')" maxlength="10" clearable />
                    <InputError :message="form.errors['org_props.phone']" />
                </div>
                <div>
                    <InputLabel value="Sueldo neto" />
                    <el-input
                        v-model="form.org_props.net_salary"
                        placeholder="Ej. $10,000"
                        class="input-with-select"
                        :formatter="(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
                        :parser="(value) => value.replace(/\$\s?|(,*)/g, '')"
                        >
                        <template #prepend>
                            <p>$</p>
                        </template>
                    </el-input>
                    <InputError :message="form.errors['org_props.net_salary']" />
                </div>
                <div>
                    <InputLabel value="Sueldo bruto" />
                    <el-input
                        v-model="form.org_props.gross_salary"
                        placeholder="Ej. $12,000"
                        class="input-with-select"
                        :formatter="(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
                        :parser="(value) => value.replace(/\$\s?|(,*)/g, '')"
                        >
                        <template #prepend>
                            <p>$</p>
                        </template>
                    </el-input>
                    <InputError :message="form.errors['org_props.gross_salary']" />
                </div>
                <div class="col-span-full">
                    <InputLabel value="Foto del usuario" />
                    <InputFilePreview @imagen="saveImage" @cleared="form.image = null" />
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
                        Crear usuario
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
            //datos personales
            name: null,
            email: null,
            phone: null,
            birthdate: null,
            civil_state: null,
            address: null,
            rfc: null,
            curp: null,
            ssn: null,

            //datos laborales
            org_props: {
                entry_date: null,
                position: null,
                department: null,
                email: null,
                phone: null,
                gross_salary: null,
                net_salary: null,
            },
            image: null,
            roles: [],
        });

        return {
            //formularios
            form,

            //generales
            civilStates: ['Solter@', 'Casad@']
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
    },
    methods: {
        store() {
            this.form.post(route("users.store"), {
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
        },
        saveImage(image) {
            this.form.image = image;
            console.log(this.form.image)
        },
    }
}
</script>

<style>
.input-with-select .el-input-group__prepend {
  background-color: var(--el-fill-color-blank);
}
</style>