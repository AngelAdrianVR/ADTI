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
            <el-table-column prop="phone" label="Teléfono" />
            <el-table-column prop="inactivate_date" label="Fecha de baja">
                <template #default="scope">
                    <p>{{ formatDate(scope.row.inactivate_date) }}</p>
                </template>
            </el-table-column>
            <el-table-column prop="inactivate_reason" label="Motivo de baja" />
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
                                <el-dropdown-item
                                    v-if="$page.props.auth.user.permissions.includes('Reactivar usuarios')"
                                    :command="'activate-' + scope.row.id">
                                    <svg class="size-4 mr-1" width="14" height="14" viewBox="0 0 14 14" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <mask id="mask0_14784_96" style="mask-type:alpha" maskUnits="userSpaceOnUse"
                                            x="0" y="0" width="14" height="14">
                                            <rect width="14" height="14" fill="#D9D9D9" />
                                        </mask>
                                        <g mask="url(#mask0_14784_96)">
                                            <path
                                                d="M10.2401 7.00065L8.16927 4.92982L9.00052 4.11315L10.2401 5.35273L12.7193 2.87357L13.5359 3.70482L10.2401 7.00065ZM5.2526 7.00065C4.61094 7.00065 4.06163 6.77218 3.60469 6.31523C3.14774 5.85829 2.91927 5.30898 2.91927 4.66732C2.91927 4.02565 3.14774 3.47635 3.60469 3.0194C4.06163 2.56246 4.61094 2.33398 5.2526 2.33398C5.89427 2.33398 6.44358 2.56246 6.90052 3.0194C7.35747 3.47635 7.58594 4.02565 7.58594 4.66732C7.58594 5.30898 7.35747 5.85829 6.90052 6.31523C6.44358 6.77218 5.89427 7.00065 5.2526 7.00065ZM0.585938 11.6673V10.034C0.585938 9.70343 0.671007 9.39961 0.841146 9.12253C1.01128 8.84544 1.23733 8.63398 1.51927 8.48815C2.12205 8.18676 2.73455 7.96072 3.35677 7.81003C3.97899 7.65933 4.61094 7.58398 5.2526 7.58398C5.89427 7.58398 6.52622 7.65933 7.14844 7.81003C7.77066 7.96072 8.38316 8.18676 8.98594 8.48815C9.26788 8.63398 9.49392 8.84544 9.66406 9.12253C9.8342 9.39961 9.91927 9.70343 9.91927 10.034V11.6673H0.585938ZM1.7526 10.5007H8.7526V10.034C8.7526 9.92704 8.72587 9.82982 8.6724 9.74232C8.61892 9.65482 8.54844 9.58676 8.46094 9.53815C7.93594 9.27565 7.40608 9.07878 6.87136 8.94753C6.33663 8.81628 5.79705 8.75065 5.2526 8.75065C4.70816 8.75065 4.16858 8.81628 3.63385 8.94753C3.09913 9.07878 2.56927 9.27565 2.04427 9.53815C1.95677 9.58676 1.88628 9.65482 1.83281 9.74232C1.77934 9.82982 1.7526 9.92704 1.7526 10.034V10.5007ZM5.2526 5.83398C5.57344 5.83398 5.84809 5.71975 6.07656 5.49128C6.30504 5.2628 6.41927 4.98815 6.41927 4.66732C6.41927 4.34648 6.30504 4.07183 6.07656 3.84336C5.84809 3.61489 5.57344 3.50065 5.2526 3.50065C4.93177 3.50065 4.65712 3.61489 4.42865 3.84336C4.20017 4.07183 4.08594 4.34648 4.08594 4.66732C4.08594 4.98815 4.20017 5.2628 4.42865 5.49128C4.65712 5.71975 4.93177 5.83398 5.2526 5.83398Z"
                                                fill="currentColor" />
                                        </g>
                                    </svg>
                                    Reactivar
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script>
import axios from 'axios';
import { format, parseISO } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        return {
            disableMassiveActions: true,
            // pagination
            itemsPerPage: 10,
            start: 0,
            end: 10,
        }
    },
    components: {

    },
    props: {
        users: Array
    },
    methods: {
        formatDate(dateString) {
            return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
        },
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

           if (commandName === 'activate') {
                this.$inertia.get(route('users.reactivatation', rowId));
            } else {
                this.$inertia.get(route('users.' + commandName, rowId));
            }
        },
        // async toogleStatus(userId) {
        //     try {
        //         const response = await axios.put(route('users.toggle-status', userId));
        //         if (response.status === 200) {
        //             this.$notify({
        //                 title: 'Correcto',
        //                 message: '',
        //                 type: 'success',
        //                 position: "bottom-right",
        //             });
        //             this.users.find(user => user.id == userId).is_active = response.data.user.is_active;
        //         }
        //     } catch (error) {
        //         this.$notify({
        //             title: 'Correcto',
        //             message: '',
        //             type: 'success',
        //             position: "bottom-right",
        //         });
        //     }
        // },
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
