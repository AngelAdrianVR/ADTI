<template>
    <div>
        <div class="flex justify-end mt-5 mx-14">
            <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear permisos')"
                @click="createPermission()" class="rounded-full">
                Agregar permiso
            </PrimaryButton>
        </div>
        <div class="text-sm overflow-scroll mt-3">
            <div class="lg:grid grid-cols-4">
                <div v-for="(guard, index) in Object.keys(permissions)" :key="index" class="border p-3">
                    <h1 class="font-bold text-secondary">{{ guard.replace(/_/g, " ") }}</h1>
                    <div v-for="(permission, index2) in permissions[guard]" :key="index"
                        class="flex justify-between items-center mt-1">
                        <p @click="editPermission(permission, index2)" class="hover:underline cursor-pointer">{{
                            permission.name
                            }}</p>
                        <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar permisos')"
                            confirm-button-text="Si" cancel-button-text="No" icon-color="#6E6F72" title="¿Continuar?"
                            @confirm="deletePermission(permission)">
                            <template #reference>
                                <button class="text-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </template>
                        </el-popconfirm>
                    </div>
                </div>
            </div>
        </div>

        <DialogModal :show="showPermissionModal" @close="showPermissionModal = false">
            <template #title>
                <p v-if="editFlag">Editar permiso</p>
                <p v-else>Crear nuevo permiso</p>
            </template>
            <template #content>
                <div>
                    <form @submit.prevent="editFlag ? updatePermission() : storePermission()" ref="myform">
                        <div>
                            <InputLabel value="Nombre del permiso *" class="ml-2" />
                            <input v-model="form.name" class="input" type="text">
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="mt-3">
                            <InputLabel value="Categoria del permiso *" class="ml-2" />
                            <input v-model="form.category" class="input" type="text">
                            <InputError :message="form.errors.category" />
                        </div>
                    </form>
                </div>
            </template>
            <template #footer>
                <CancelButton class="mr-1" @click="showPermissionModal = false; form.reset();"
                    :disabled="form.processing">Cancelar</CancelButton>
                <PrimaryButton @click="submitForm" :disabled="form.processing">{{ editFlag ?
                    'Actualizar' :
                    'Crear' }}
                </PrimaryButton>
            </template>
        </DialogModal>
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

export default {
    data() {
        const form = useForm({
            name: null,
            category: null,
        });

        return {
            form,
            currentPermission: null,
            showPermissionModal: false,
            indexPermissionEdit: null,
            editFlag: false,
        };
    },
    components: {
        AppLayout,
        PrimaryButton,
        CancelButton,
        DialogModal,
        InputLabel,
        InputError
    },
    props: {
        permissions: Object,
    },
    methods: {
        editPermission(permission, index) {
            if (this.$page.props.auth.user.permissions.includes('Editar permisos')) {
                this.currentPermission = permission;
                this.editFlag = true;
                this.indexPermissionEdit = index;
                this.showPermissionModal = true;

                this.form.name = permission.name;
                this.form.category = permission.category;
            }
        },
        createPermission() {
            this.currentPermission = null;
            this.showPermissionModal = true;
            this.editFlag = false;
        },
        deletePermission(permission) {
            this.form.delete(route('settings.role-permission.delete-permission', permission), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: 'Permiso eliminado',
                        type: 'success'
                    });

                    this.form.reset();
                    this.showPermissionModal = false;
                },
            });
        },
        updatePermission() {
            this.form.put(route('settings.role-permission.update-permission', this.currentPermission), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: 'Permiso actualizado',
                        type: 'success'
                    });

                    this.form.reset();
                    this.showPermissionModal = false;
                },
            });
        },
        storePermission() {
            this.form.post(route('settings.role-permission.store-permission'), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: 'Permiso creado',
                        type: 'success'
                    });

                    this.form.reset();
                    this.showPermissionModal = false;
                },
            });
        },
        submitForm() {
            this.$refs.myform.dispatchEvent(new Event('submit', { cancelable: true }));
        },
    }
};
</script>