<template>
    <div class="mx-2 lg:mx-10 mt-6">
        <div class="lg:flex justify-between mb-2">
            <!-- pagination -->
            <div class="flex space-x-2 items-center lg:ml-20">
                <el-pagination @current-change="handlePagination" layout="prev, pager, next"
                    :total="users.length / itemsPerPage" />
                <div v-if="$page.props.auth.user.permissions?.includes('Eliminar usuarios')" class="mt-2 lg:mt-0">
                    <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#0355B5"
                        title="¿Continuar?" @confirm="deleteSelections">
                        <template #reference>
                            <el-button type="danger" plain class="mb-3"
                                :disabled="disableMassiveActions">Eliminar</el-button>
                        </template>
                    </el-popconfirm>
                </div>
            </div>
        </div>
        <el-table :data="users" @row-click="handleRowClick" max-height="670" style="width: 90%" class="mx-auto"
            @selection-change="handleSelectionChange" ref="multipleTableRef" :row-class-name="tableRowClassName">
            <el-table-column type="selection" width="30" />
            <el-table-column prop="code" label="ID" width="90" />
            <el-table-column prop="name" label="Nombre" />
            <el-table-column prop="org_props.position" label="Puesto" />
            <el-table-column prop="email" label="Correo electrónico" />
            <el-table-column prop="phone" label="Teléfono" />
            <el-table-column align="right">
                <template #default="scope">
                    <el-dropdown trigger="click" @command="handleCommand">
                        <button @click.stop
                            class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-primary hover:bg-grayED transition-all duration-200 ease-in-out">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item :command="'show-' + scope.row.id">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    Ver</el-dropdown-item>
                                <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                                    :command="'edit-' + scope.row.id">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                    Editar</el-dropdown-item>
                                <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Inactivar usuarios')"
                                    :command="'inactivate-' + scope.row.id">
                                    <svg class="size-4 mr-1" width="14" height="14" viewBox="0 0 14 14" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <mask id="mask0_14810_295" style="mask-type:alpha" maskUnits="userSpaceOnUse"
                                            x="0" y="0" width="14" height="14">
                                            <rect width="14" height="14" fill="#D9D9D9" />
                                        </mask>
                                        <g mask="url(#mask0_14810_295)">
                                            <path
                                                d="M11.538 13.1974L10.0068 11.6661H2.33594V10.0328C2.33594 9.70226 2.42101 9.39844 2.59115 9.12135C2.76128 8.84427 2.98733 8.63281 3.26927 8.48698C3.70677 8.26337 4.15156 8.08351 4.60365 7.9474C5.05573 7.81128 5.5151 7.7092 5.98177 7.64115L0.804688 2.46406L1.63594 1.63281L12.3693 12.3661L11.538 13.1974ZM3.5026 10.4995H8.8401L7.0901 8.74948H7.0026C6.45816 8.74948 5.91858 8.8151 5.38385 8.94635C4.84913 9.0776 4.31927 9.27448 3.79427 9.53698C3.70677 9.58559 3.63628 9.65365 3.58281 9.74115C3.52934 9.82865 3.5026 9.92587 3.5026 10.0328V10.4995ZM10.7359 8.48698C11.0179 8.62309 11.2415 8.82969 11.4068 9.10677C11.572 9.38385 11.6595 9.68281 11.6693 10.0036L9.7151 8.04948C9.8901 8.11753 10.0627 8.18559 10.2328 8.25365C10.403 8.3217 10.5707 8.39948 10.7359 8.48698ZM8.28594 6.62031L7.42552 5.7599C7.64913 5.6724 7.82899 5.52899 7.9651 5.32969C8.10122 5.13038 8.16927 4.9092 8.16927 4.66615C8.16927 4.34531 8.05503 4.07066 7.82656 3.84219C7.59809 3.61372 7.32344 3.49948 7.0026 3.49948C6.75955 3.49948 6.53837 3.56753 6.33906 3.70365C6.13976 3.83976 5.99635 4.01962 5.90885 4.24323L5.04844 3.38281C5.27205 3.05226 5.55399 2.79462 5.89427 2.6099C6.23455 2.42517 6.60399 2.33281 7.0026 2.33281C7.64427 2.33281 8.19358 2.56128 8.65052 3.01823C9.10746 3.47517 9.33594 4.02448 9.33594 4.66615C9.33594 5.06476 9.24358 5.4342 9.05885 5.77448C8.87413 6.11476 8.61649 6.3967 8.28594 6.62031Z"
                                                fill="currentColor" />
                                        </g>
                                    </svg>
                                    Dar de baja
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </template>
            </el-table-column>
        </el-table>
    </div>
    <DialogModal :show="showInactivatigModal" @close="showInactivatigModal = false" maxWidth="lg">
        <template #title>
            <h1>Baja del usuario</h1>
        </template>
        <template #content>
            <form @submit.prevent="inactiveUser">
                <div>
                    <InputLabel value="Fecha de baja*" />
                    <input v-model="form.inactivate_date" class="w-full input" type="date"
                        placeholder="Selecciona la fecha de nacimiento" />
                    <InputError :message="form.errors.inactivate_date" />
                </div>
                <div class="mt-3">
                    <InputLabel value="Motivo de baja* " />
                    <el-input v-model="form.inactivate_reason" :autosize="{ minRows: 2, maxRows: 6 }" type="textarea"
                        placeholder="Escribe el motivo de baja " :maxlength="300" show-word-limit clearable />
                    <InputError :message="form.errors.inactivate_reason" />
                </div>
                <div class="mt-3 flex justify-end">
                    <PrimaryButton :disabled="form.processing">
                        <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Guardar baja
                    </PrimaryButton>
                </div>
            </form>
        </template>
    </DialogModal>
</template>

<script>
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from "date-fns";

export default {
    data() {
        const form = useForm({
            inactivate_date: format(new Date(), "yyyy-MM-dd"), // Establece la fecha de hoy por defecto,
            inactivate_reason: null,
        });

        return {
            form,
            disableMassiveActions: true,
            showInactivatigModal: false,
            inactivateUserId: null,
            // pagination
            itemsPerPage: 10,
            start: 0,
            end: 10,
        }
    },
    components: {
        DialogModal,
        InputLabel,
        InputError,
        PrimaryButton,
    },
    props: {
        users: Array
    },
    methods: {
        handleSearch() {
            this.search = this.searchQuery;
        },
        handleSelectionChange(val) {
            this.$refs.multipleTableRef.value = val;

            if (!this.$refs.multipleTableRef.value.length) {
                this.disableMassiveActions = true;
            } else {
                this.disableMassiveActions = false;
            }
        },
        handlePagination(val) {
            this.start = (val - 1) * this.itemsPerPage;
            this.end = val * this.itemsPerPage;
        },
        tableRowClassName({ row, rowIndex }) {
            return 'cursor-pointer text-xs';
        },
        handleRowClick(row) {
            this.$inertia.visit(route('users.show', row));
        },
        handleCommand(command) {
            const commandName = command.split('-')[0];
            const rowId = command.split('-')[1];

            if (commandName === 'inactivate') {
                this.showInactivatigModal = true;
                this.inactivateUserId = rowId;
            } else {
                this.$inertia.get(route('users.' + commandName, rowId));
            }

        },
        inactiveUser() {
            this.form.put(route('users.inactivate', this.inactivateUserId), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        message: '',
                        type: 'success',
                        position: "bottom-right",
                    });
                    this.showInactivatigModal = false;
                    this.form.reset();
                },
                onError: (error) => {
                    console.log(error);
                }
            });
        },
        async deleteSelections() {
            try {
                const items_ids = this.$refs.multipleTableRef.value.map(item => item.id);
                const response = await axios.post(route('users.massive-delete', {
                    items_ids
                }));

                if (response.status === 200) {
                    this.$notify({
                        title: 'Correcto',
                        message: '',
                        type: 'success',
                        position: "bottom-right",
                    });

                    // update list of quotes
                    let deletedIndexes = [];
                    this.users.forEach((user, index) => {
                        if (items_ids.includes(user.id) && user.id != this.$page.props.auth.user.id) {
                            deletedIndexes.push(index);
                        }
                    });

                    // Ordenar los índices de forma descendente para evitar problemas de desplazamiento al eliminar elementos
                    deletedIndexes.sort((a, b) => b - a);

                    // Eliminar cotizaciones por índice
                    for (const index of deletedIndexes) {
                        this.users.splice(index, 1);
                    }
                }
            } catch (err) {
                this.$notify({
                    title: 'No se pudo completar la solicitud',
                    message: '',
                    type: 'error',
                    position: "bottom-right",
                });
                console.log(err);
            }
        },
    }
}
</script>
