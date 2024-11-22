<template>
    <div>
        <div class="flex justify-end mt-5 mx-14">
            <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear departamentos')"
                @click="createDepartment()" class="rounded-full">
                Crear departamento
            </PrimaryButton>
        </div>
        <p class="mt-3 text-secondary">
            En esta sección, encontrarás todos los departamentos que has creado. Si aún no
            has creado ninguno, puedes hacerlo utilizando el botón 'Crear'.
            También tienes la opción de editar o eliminar departamentos
            existentes </p>
        <section class="mt-10">
            <table class="text-secondary border border-grayD9 rounded-[3px]">
                <thead>
                    <tr class="*:text-start *:px-2 *:py-1 border border-grayD9">
                        <th>Departamentos</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in departments" :key="item.id"
                        class="*:text-start *:px-2 *:py-1 border border-grayD9">
                        <td>{{ item.name }}</td>
                        <td class="rounded-e-full text-end"
                            v-if="$page.props.auth.user.permissions.includes('Editar departamentos') || $page.props.auth.user.permissions.includes('Eliminar departamentos')">
                            <el-dropdown trigger="click" @command="handleCommand">
                                <button @click.stop
                                    class="el-dropdown-link justify-center items-center size-6 hover:bg-primary hover:text-primarylight rounded-full text-primary transition-all duration-200 ease-in-out">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-if="$page.props.auth.user.permissions.includes('Editar departamentos')"
                                            :command="'edit|' + index">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="size-[14px] mr-2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                            </svg>
                                            <span class="text-xs">Editar</span>
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="$page.props.auth.user.permissions.includes('Eliminar departamentos')"
                                            :command="'delete|' + index">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="size-[14px] mr-2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                            <span class="text-xs">Eliminar</span>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <DialogModal :show="showDepartmentModal" @close="showDepartmentModal = false" maxWidth="lg">
            <template #title>
                <p v-if="indexDepartmentToEdit">Editar departamento</p>
                <p v-else>Crear nuevo departamento</p>
            </template>
            <template #content>
                <div>
                    <form @submit.prevent="indexDepartmentToEdit ? updateDepartment() : storeDepartment()" ref="myform">
                        <div>
                            <InputLabel value="Nombre del departamento *" />
                            <input v-model="form.name" class="input" type="text">
                            <InputError :message="form.errors.name" />
                        </div>
                    </form>
                </div>
            </template>
            <template #footer>
                <CancelButton class="mr-1" @click="showDepartmentModal = false; form.reset();" :disabled="form.processing">
                    Cancelar</CancelButton>
                <PrimaryButton @click="submitForm" :disabled="form.processing">{{ indexDepartmentToEdit ?
                    'Actualizar' :
                    'Crear' }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <ConfirmationModal :show="showDeleteConfirm" @close="showDeleteConfirm = false">
            <template #title>
                <p>Eliminar departamento</p>
            </template>
            <template #content>
                <p>¿Continuar con la eliminación?</p>
            </template>
            <template #footer>
                <CancelButton class="mr-1" @click="showDeleteConfirm = false" :disabled="deletting">
                    Cancelar</CancelButton>
                <PrimaryButton @click="deleteDepartment()" :disabled="deletting">
                    Eliminar
                </PrimaryButton>
            </template>
        </ConfirmationModal>
    </div>
</template>
<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import DialogModal from "@/Components/DialogModal.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import { useForm } from "@inertiajs/vue3";

export default {
    data() {
        const form = useForm({
            name: null,
        });

        return {
            form,
            showDepartmentModal: false,
            indexDepartmentToEdit: null,
            currentDepartment: null,
            showDeleteConfirm: false,
            deletting: false,
        };
    },
    components: {
        AppLayout,
        PrimaryButton,
        CancelButton,
        DialogModal,
        InputLabel,
        InputError,
        ConfirmationModal,
    },
    props: {
        departments: Array,
    },
    methods: {
        handleCommand(command) {
            const commandName = command.split('|')[0];
            const index = command.split('|')[1];
            const department = this.departments[index];

            if (commandName == 'edit') {
                this.editDepartment(department, index);
            } else if (commandName == 'delete') {
                this.showDeleteConfirm = true;
                this.currentDepartment = department;
            }
        },
        editDepartment(department, index) {department
            this.currentDepartment = department;
            this.indexDepartmentToEdit = index;
            this.showDepartmentModal = true;

            this.form.name = department.name;
        },
        createDepartment() {
            this.currentDepartment = null;
            this.showDepartmentModal = true;
            this.indexDepartmentToEdit = null;
        },
        deleteDepartment() {
            this.deletting = true;
            this.form.delete(route('departments.destroy', this.currentDepartment), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: '',
                        type: 'success'
                    });
                    
                    this.showDeleteConfirm = false;
                    this.currentDepartment = null;
                    this.deletting = false;
                },
            });
        },
        updateDepartment() {
            this.form.put(route('departments.update', this.currentDepartment), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: '',
                        type: 'success'
                    });

                    this.form.reset();
                    this.showDepartmentModal = false;
                },
            });
        },
        storeDepartment() {
            this.form.post(route('departments.store'), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: '',
                        type: 'success'
                    });

                    this.form.reset();
                    this.showDepartmentModal = false;
                },
            });
        },
        submitForm() {
            this.$refs.myform.dispatchEvent(new Event('submit', { cancelable: true }));
        },
    }
};
</script>