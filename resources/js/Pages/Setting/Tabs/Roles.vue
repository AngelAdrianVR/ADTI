<template>
    <div class="mx-3">
        <div class="flex items-center justify-between">
            <h1 class="font-bold text-base">Roles</h1>
            <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear roles')" @click="createRole()"
                class="rounded-full">
                Crear rol
            </PrimaryButton>
        </div>
        <p class="text-secondary">En este apartado, puedes gestionar los roles y sus permisos. Asigna permisos
            específicos a cada rol según sea necesario.</p>
        <div class="text-sm mt-5">
            <div v-if="roles.length" class="overflow-auto">
                <table class="">
                    <thead>
                        <tr class="*:text-secondary *:font-normal *:text-start">
                            <th class="w-12">ID</th>
                            <th class="w-44">Nombre del rol</th>
                            <th class="w-56">Fecha de creación</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(role, index) in roles" :key="role.id">
                            <td>{{ role.id }}</td>
                            <td>
                                <button @click="editRole(role, index)" class="hover:underline">
                                    {{ role.name }}
                                </button>
                            </td>
                            <td>{{ formatDate(role.created_at) }}</td>
                            <td>
                                <div v-if="$page.props.auth.user.permissions.includes('Eliminar roles')">
                                    <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#6F6E72"
                                        :title="'¿Desea eliminar el elemento seleccionado?'" @confirm="deleteRole(role)">
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
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <el-empty v-if="!roles.length" description="No hay roles para mostrar" />
        </div>
        <DialogModal :show="showRoleModal" @close="showRoleModal = false">
            <template #title>
                <p v-if="editFlag">Rol {{ currentRole.name }}</p>
                <p v-else>Crear nuevo rol</p>
            </template>
            <template #content>
                <div>
                    <form @submit.prevent="editFlag ? updateRole() : storeRole()" ref="myform"
                        class="grid grid-cols-2 lg:grid-cols-3">
                        <div class="col-span-full mb-4">
                            <InputLabel value="Nombre de rol *" class="ml-2" />
                            <input v-model="form.name" class="input" type="text">
                            <InputError :message="form.errors.name" />
                        </div>
                        <p class="font-bold mb-2 col-span-full">Asignar permisos</p>
                        <div v-for="(guard, index) in Object.keys(permissions)" :key="index" class="border p-3">
                            <h1 class="font-bold">{{ guard.replace(/_/g, " ") }}</h1>
                            <label v-for="permission in permissions[guard]" :key="permission.id"
                                class="flex items-center">
                                <input type="checkbox" v-model="form.permissions" :value="permission.id"
                                    class="rounded border-gray-400 text-primary shadow-sm focus:ring-primary bg-transparent" />
                                <span class="ml-2 text-sm">{{ permission.name }}</span>
                            </label>
                        </div>
                        <InputError class="col-span-full" :message="form.errors.permissions" />
                    </form>
                </div>
            </template>
            <template #footer>
                <CancelButton class="mr-1" @click="showRoleModal = false; form.reset();" :disabled="form.processing">
                    Cancelar
                </CancelButton>
                <PrimaryButton @click="submitForm" :disabled="form.processing">
                    {{ editFlag ? 'Actualizar' : 'Crear' }}
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
import axios from "axios";
import { parseISO, format } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        const form = useForm({
            name: null,
            permissions: [],
        });

        return {
            form,
            selectedItems: [],
            allItems: false,
            currentRole: null,
            showRoleModal: false,
            indexRoleEdit: null,
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
        roles: Object,
        permissions: Object,
    },
    methods: {
        formatDate(dateString) {
            return format(parseISO(dateString), 'dd MMMM yyyy', { locale: es });
        },
        submitForm() {
            this.$refs.myform.dispatchEvent(new Event('submit', { cancelable: true }));
        },
        editRole(role, index) {
            if (this.$page.props.auth.user.permissions.includes('Editar roles')) {
                this.currentRole = role;
                this.editFlag = true;
                this.indexRoleEdit = index;
                this.showRoleModal = true;

                this.form.name = role.name;
                this.form.permissions = role.permissions.map(item => item.id);
            }
        },
        createRole() {
            this.currentRole = null;
            this.showRoleModal = true;
            this.editFlag = false;
            this.form.reset();
        },
        deleteRole(role) {
            this.form.delete(route('settings.role-permission.delete-role', role.id), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: 'Rol eliminado',
                        type: 'success'
                    });
                },
            })
        },
        updateRole() {
            this.form.put(route('settings.role-permission.update-role', this.currentRole), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: 'Rol actualizado',
                        type: 'success'
                    });

                    this.form.reset();
                    this.showRoleModal = false;
                },
            })
        },
        storeRole() {
            this.form.post(route('settings.role-permission.store-role'), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: 'Rol creado',
                        type: 'success'
                    });

                    this.form.reset();
                    this.showRoleModal = false;
                },
            })
        },
    },
};
</script>