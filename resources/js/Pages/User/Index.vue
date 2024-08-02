<template>
    <AppLayout title="Usuarios">
        <main class="px-2 lg:px-10 py-7">
            <h1 class="font-bold my-3 ml-4 text-lg">Usuarios</h1>
            <section class="md:flex justify-between items-center">
                <div class="lg:w-1/3 relative">
                    <input v-model="searchQuery" @keydown.enter="handleSearch" class="input w-full pl-9"
                        placeholder="Buscar por nombre o número de parte" type="search" ref="searchInput" />
                    <i class="fa-solid fa-magnifying-glass text-xs text-gray99 absolute top-[10px] left-4"></i>
                </div>
                <!-- buttons -->
                <div class="flex items-center space-x-1">
                    <div v-if="$page.props.auth.user.permissions?.includes('Eliminar usuarios') && !disableMassiveActions"
                        class="mt-2 lg:mt-0">
                        <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#6D6E72"
                            title="¿Continuar?" @confirm="deleteSelections">
                            <template #reference>
                                <PrimaryButton class="!bg-red-600 focus:!ring-red-600">Eliminar</PrimaryButton>
                            </template>
                        </el-popconfirm>
                    </div>
                    <div class="my-4 lg:my-0 flex items-center justify-end space-x-3">
                        <PrimaryButton v-if="$page.props.auth.user.permissions?.includes('Crear usuarios')"
                            @click="$inertia.get(route('users.create'))">Crear usuario</PrimaryButton>
                    </div>
                </div>
            </section>

            <div class="mx-2 lg:mx-10 mt-6">
                <div class="lg:flex justify-between mb-2">
                    <!-- pagination -->
                    <div>
                        <el-pagination @current-change="handlePagination" layout="prev, pager, next"
                            :total="users.length" />
                    </div>
                </div>
                <el-table :data="filteredTableData" @row-click="handleRowClick" max-height="670" style="width: 100%"
                    @selection-change="handleSelectionChange" ref="multipleTableRef"
                    :row-class-name="tableRowClassName">
                    <el-table-column type="selection" width="30" />
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
                                        <el-dropdown-item :command="'show-' + scope.row.id"><i
                                                class="fa-solid fa-eye"></i>
                                            Ver</el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                                            :command="'edit-' + scope.row.id"><i class="fa-solid fa-pen"></i>
                                            Editar</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

export default {
    data() {
        return {
            // buscador
            searchQuery: null,
            searchedWord: null,

            // tabla
            disableMassiveActions: true,
            showScrollButton: false,
            loading: false,
            inputSearch: '',
            search: '',

            // pagination
            itemsPerPage: 10,
            start: 0,
            end: 10,
        }
    },
    components: {
        AppLayout,
        PrimaryButton,
    },
    props: {
        users: Array
    },
    methods: {
        closedTag() {
            this.localUsers = this.users;
            this.searchedWord = null;
        },
        inputFocus() {
            this.$nextTick(() => {
                this.$refs.searchInput.focus();
            });
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

            if (commandName == 'clone') {
                // this.$inertia.visit(route('users.' + commandName, rowId));
            } else {
                this.$inertia.get(route('users.' + commandName, rowId));
            }
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
                        type: 'success'
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
                    type: 'error'
                });
                console.log(err);
            }
        },
    },
    computed: {
        filteredTableData() {
            if (!this.search) {
                return this.users.filter((item, index) => index >= this.start && index < this.end);
            } else {
                return this.users.filter(
                    (user) =>
                        user.name.toLowerCase().includes(this.search.toLowerCase()) ||
                        user.org_props.position.toLowerCase().includes(this.search.toLowerCase())
                )
            }
        }
    },
    mounted() {
        this.inputFocus();
    }
}
</script>
