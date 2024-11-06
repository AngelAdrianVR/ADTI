<template>
    <section class="mt-4 mb-10 mx-4 text-xs lg:text-sm">
        <div class="flex justify-end lg:mx-20">
            <PrimaryButton>Subir formatos</PrimaryButton>
        </div>
        <!-- pagination -->
        <div class="flex space-x-2 items-center ml-16">
            <el-pagination @current-change="handlePagination" layout="prev, pager, next"
                :total="user.media.length / itemsPerPage" />
            <div v-if="$page.props.auth.user.permissions?.includes('Eliminar expedientes de usuarios')"
                class="mt-2 lg:mt-0">
                <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#0355B5" title="¿Continuar?"
                    @confirm="deleteSelections">
                    <template #reference>
                        <el-button type="danger" plain class="mb-3"
                            :disabled="disableMassiveActions">Eliminar</el-button>
                    </template>
                </el-popconfirm>
            </div>
        </div>
        <el-table :data="user.media" @row-click="handleRowClick" max-height="670" style="width: 90%" class="mx-auto"
            @selection-change="handleSelectionChange" ref="multipleTableRef" :row-class-name="tableRowClassName">
            <el-table-column type="selection" width="30" />
            <el-table-column prop="file_name" label="Nombre del documento" />
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
                                    Editar nombre de archivo
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </template>
            </el-table-column>
        </el-table>
    </section>
</template>

<script>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        const form = useForm({
            // vacations: this.user.org_props.vacations,
        });

        return {
            form,
            disableMassiveActions: true,
            // pagination
            itemsPerPage: 10,
            start: 0,
            end: 10,
        }
    },
    components: {
        PrimaryButton,
    },
    props: {
        user: Object,
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

            if (commandName === 'clone') {
                // this.$inertia.visit(route('users.' + commandName, rowId));
            } else if (commandName === 'toogleSatus') {
                // this.toogleStatus(rowId);
            } else {
                this.$inertia.get(route('users.' + commandName, rowId));
            }

        },
        formatDate(dateString) {
            return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
        },
        updateVacations() {
            this.form.put(route('users.update-vacations', this.user.id), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        type: 'success',
                    })
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
