<template>
    <AppLayout title="Productos">
        <main ref="scrollContainer" style="height: 93vh; overflow-y: scroll;" @scroll="handleScroll" class="px-2 lg:px-10 py-7">
            <h1 class="font-bold my-3 ml-4 text-lg">Productos</h1>
            <section class="md:flex justify-between items-center">
                <div class="lg:w-1/3 relative">
                    <input v-model="searchQuery" @keydown.enter="handleSearch" class="input w-full pl-9"
                        placeholder="Buscar por nombre o número de parte" type="search" ref="searchInput" />
                    <i class="fa-solid fa-magnifying-glass text-xs text-gray99 absolute top-[10px] left-4"></i>
                </div>
                <div class="my-4 lg:my-0 flex items-center justify-end space-x-3">
                    <PrimaryButton id="start" @click="$inertia.get(route('products.create'))">Crear producto</PrimaryButton>
                </div>
            </section>

            <!-- tabla starts -->
            <div class="w-[95%] lg:w-5/6 mx-auto mt-6">
                <div class="lg:flex justify-between mb-2">
                    <!-- pagination -->
                    <div>
                        <el-pagination @current-change="handlePagination" layout="prev, pager, next"
                            :total="products.length" />
                    </div>
                    <!-- buttons -->
                    <div v-if="$page.props.auth.user.permissions?.includes('Eliminar productos') || true"
                        class="mt-2 lg:mt-0">
                        <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#0355B5"
                            title="¿Continuar?" @confirm="deleteSelections">
                            <template #reference>
                                <el-button type="danger" plain class="mb-3"
                                    :disabled="disableMassiveActions">Eliminar</el-button>
                            </template>
                        </el-popconfirm>
                    </div>
                </div>
                <el-table :data="filteredTableData" @row-click="handleRowClick" max-height="670" style="width: 100%"
                    @selection-change="handleSelectionChange" ref="multipleTableRef"
                    :row-class-name="tableRowClassName">
                    <el-table-column type="selection" width="30" />
                    <el-table-column prop="part_number" label="Num de parte" width="200" />
                    <el-table-column prop="name" label="Nombre" width="200" />
                    <el-table-column prop="cost.number_format" label="Categoría" width="150" />
                    <el-table-column prop="description" label="Subcategorías" width="150" />
                    <el-table-column prop="description" label="Ubicación en almacén" />
                    <el-table-column align="right">
                        <template #default="scope">
                            <el-dropdown trigger="click" @command="handleCommand">
                                <button @click.stop
                                    class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-primary hover:bg-gray2 transition-all duration-200 ease-in-out">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item :command="'show-' + scope.row.id"><i
                                                class="fa-solid fa-eye"></i>
                                            Ver</el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="$page.props.auth.user.permissions.includes('Editar catalogo de productos')"
                                            :command="'edit-' + scope.row.id"><i class="fa-solid fa-pen"></i>
                                            Editar</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <!-- tabla ends -->

            <!-- Flecha para subir el scroll -->
            <i v-show="showScrollButton" @click="scrollToTop" class="fa-solid animate-bounce fa-arrow-up rounded-full bg-[#F2F2F2] text-gray9A py-3 px-[14px] fixed bottom-8 right-8 cursor-pointer transition-opacity duration-300"></i>
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
components:{
    AppLayout,
    PrimaryButton,
},
props:{
    products: Array
},
methods:{
    handleScroll() {
        const container = this.$refs.scrollContainer;
        // const scrollHeight = container.scrollHeight;
        const scrollTop = container.scrollTop;
        // const clientHeight = container.clientHeight;

        // Determinar si has llegado al final de la vista
        if (scrollTop > 500) {
            this.showScrollButton = true;
        } else {
            this.showScrollButton = false;
        }
    },
    scrollToTop() {
        const section = document.getElementById('start');
        section.scrollIntoView({ behavior: 'smooth' });
    },
    closedTag() {
        this.localProducts = this.products;
        this.searchedWord = null;
    },
    inputFocus() {
        this.$nextTick(() => {
            this.$refs.searchInput.focus();
        });
    },
    handleSearch(search) {
        this.search = search;
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
        this.$inertia.get(route('catalog-products.show', row));
    },
    handleCommand(command) {
        const commandName = command.split('-')[0];
        const rowId = command.split('-')[1];

        if (commandName == 'clone') {
            this.clone(rowId);
        } else if (commandName == 'make_so') {
            console.log('SO');
        } else {
            this.$inertia.get(route('catalog-products.' + commandName, rowId));
        }
    },
    async deleteSelections() {
        try {
            const response = await axios.post(route('catalog-products.massive-delete', {
                catalog_products: this.$refs.multipleTableRef.value
            }));

            if (response.status == 200) {
                this.$notify({
                    title: 'Éxito',
                    message: response.data.message,
                    type: 'success'
                });

                // update list of quotes
                let deletedIndexes = [];
                this.catalog_products.forEach((catalog_product, index) => {
                    if (this.$refs.multipleTableRef.value.includes(catalog_product)) {
                        deletedIndexes.push(index);
                    }
                });

                // Ordenar los índices de forma descendente para evitar problemas de desplazamiento al eliminar elementos
                deletedIndexes.sort((a, b) => b - a);

                // Eliminar cotizaciones por índice
                for (const index of deletedIndexes) {
                    this.catalog_products.splice(index, 1);
                }

            } else {
                this.$notify({
                    title: 'Algo salió mal',
                    message: response.data.message,
                    type: 'error'
                });
            }

        } catch (err) {
            this.$notify({
                title: 'Algo salió mal',
                message: err.message,
                type: 'error'
            });
            console.log(err);
        }
    },
},
computed: {
    filteredTableData() {
        if (!this.search) {
            return this.products.filter((item, index) => index >= this.start && index < this.end);
        } else {
            return this.products.filter(
                (product) =>
                    product.name.toLowerCase().includes(this.search.toLowerCase()) ||
                    product.part_number.toLowerCase().includes(this.search.toLowerCase())
            )
        }
    }
},
mounted() {
    this.inputFocus();
}
}
</script>
