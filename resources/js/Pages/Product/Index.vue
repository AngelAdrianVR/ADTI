<template>
    <AppLayout title="Productos">
        <main class="px-2 lg:px-10 pt-1 pb-16">
            <h1 class="font-bold my-3 ml-4 text-lg">Productos</h1>
            <section class="md:flex justify-between items-center">
                <div class="lg:w-1/3 relative">
                    <input v-model="searchQuery" @keydown.enter="handleSearch" class="input w-full pl-9"
                        placeholder="Buscar por nombre o número de parte del fabricante" type="search"
                        ref="searchInput" />
                    <i class="fa-solid fa-magnifying-glass text-xs text-gray99 absolute top-[10px] left-4"></i>
                </div>
                <el-dropdown split-button type="primary" @click="$inertia.get(route('products.create'))" trigger="click"
                    @command="handleDropdownCommand">
                    Crear producto
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="import">Importar productos</el-dropdown-item>
                            <el-dropdown-item command="export">Exportar productos</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
                <!-- <div class="my-4 lg:my-0 flex items-center justify-end space-x-3">
                    <PrimaryButton @click="$inertia.get(route('products.create'))">Crear producto</PrimaryButton>
                </div> -->
            </section>

            <!-- tabla starts -->
            <div class="w-[95%] lg:w-5/6 mx-auto mt-6">
                <div class="lg:flex justify-between mb-2">
                    <!-- pagination -->
                    <div class="flex space-x-5 items-center">
                        <el-pagination @current-change="handlePagination" layout="prev, pager, next"
                            :total="products.length" />
                        <!-- buttons -->
                        <div v-if="$page.props.auth.user.permissions?.includes('Eliminar productos')"
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
                </div>
                <el-table :data="filteredTableData" @row-click="handleRowClick" max-height="700" style="width: 100%"
                    @selection-change="handleSelectionChange" ref="multipleTableRef"
                    :row-class-name="tableRowClassName">
                    <el-table-column type="selection" width="30" />
                    <el-table-column prop="part_number_supplier" label="Num. de parte fabricante" width="200" />
                    <el-table-column prop="name" label="Nombre" width="150" />
                    <el-table-column prop="subcategory.category.name" label="Categoría" width="150" />
                    <el-table-column label="Subcategorías" width="150">
                        <template #default="scope">
                            <div class="flex flex-col">
                                <p v-for="subcategory in scope.row.bread_crumbles" :key="subcategory"
                                    class="flex items-center space-x-2">
                                    <i class="fa-solid fa-caret-right"></i>
                                    <span>{{ subcategory }}</span>
                                </p>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="location" label="Ubicación en almacén" width="150" />
                    <el-table-column prop="description" label="Descripción" />
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
                                        <el-dropdown-item
                                            v-if="$page.props.auth.user.permissions.includes('Editar productos')"
                                            :command="'edit-' + scope.row.id">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                            Editar</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <!-- tabla ends -->
        </main>

        <!-- modal de exportacion -->
        <DialogModal :show="showExportModal" @close="showExportModal = false">
            <template #title> Exportar productos </template>
            <template #content>
                <p class="text-black">
                    Ve a configuración en la pestaña de categorías. Ahí encontrarás el botón para exportar tus productos
                    por cada línea jerárquica o da clic en el siguiente link:
                </p>
                <Link :href="route('settings.index')" class="flex items-center space-x-3 text-primary mt-3">
                <span class="underline">Ir a categorías </span>
                <i class="fa-solid fa-arrow-right-long mt-1"></i>
                </Link>
                <figure class="border border-grayD9 rounded-[5px] px-5 py-2 mt-4">
                    <img src="@/../../public/images/export.jpg" alt="logo">
                </figure>
            </template>
            <template #footer>
            </template>
        </DialogModal>

        <!-- modal de importacion -->
        <DialogModal :show="showImportModal" @close="showImportModal = false">
            <template #title> Importar productos </template>
            <template #content>
                <p class="text-secondary">¡Bienvenido a la función de importación de productos! Para facilitar el
                    proceso y asegurar que todos
                    los datos se ingresen correctamente, te recomendamos seguir estos pasos simples.</p>
                <p class="text-black mt-5">
                    Primero, descarga la plantilla de importación desde la última subcategoría de cada línea jerárquica.
                    Esto permite identificar los productos específicos de cada jerarquía que se van a subir, ya que cada
                    subcategoría cuenta con características diferentes.
                </p>
                <Link :href="route('settings.index')" class="flex items-center space-x-3 text-primary mt-3">
                <span class="underline">Descargar plantilla desde categorías</span>
                <i class="fa-solid fa-arrow-right-long mt-1"></i>
                </Link>
                <figure class="border border-grayD9 rounded-[5px] px-5 py-2 mt-4">
                    <img src="@/../../public/images/import.jpg" alt="logo">
                </figure>
                <p class="text-black mt-4">
                    Una vez que hayas agregado todos los productos a la plantilla, adjunta el archivo. El sistema se
                    encargará automáticamente de procesar la información y agregar tus productos.
                </p>
                <form @submit.prevent="importProducts" ref="importForm" class="mt-4">
                    <div>
                        <FileUploader @files-selected="importForm.file = $event" :multiple="false"
                            acceptedFormat="excel" />
                        <InputError :message="importForm.errors.file" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div v-if="!isImporting && !importWasSuccessful && !importWasWrong" class="flex items-center space-x-2">
                    <CancelButton @click="showImportModal = false; importForm.file = []">
                        Cancelar
                    </CancelButton>
                    <PrimaryButton @click="importProducts()" :disabled="!importForm.file.length">
                        <i v-if="isImporting" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                        Importar
                    </PrimaryButton>
                </div>
                <div v-if="importWasWrong" class="flex items-center space-x-2">
                    <PrimaryButton @click="importWasWrong = false; importForm.file = []">
                        Ya corregí mi achivo
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import DialogModal from '@/Components/DialogModal.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import FileUploader from '@/Components/MyComponents/FileUploader.vue';
import { useForm, Link } from '@inertiajs/vue3';

export default {
    data() {
        const importForm = useForm({
            file: [],
        });

        return {
            // buscador
            searchQuery: null,
            searchedWord: null,
            // tabla
            disableMassiveActions: true,
            loading: false,
            inputSearch: '',
            search: '',
            // pagination
            itemsPerPage: 10,
            start: 0,
            end: 10,
            // importation
            showImportModal: false,
            isImporting: false,
            importWasSuccessful: false,
            importWasWrong: false,
            importErrors: [],
            importForm,
            //exportacion
            showExportModal: false,
            isExporting: false,
        }
    },
    components: {
        AppLayout,
        PrimaryButton,
        Link,
        CancelButton,
        DialogModal,
        FileUploader,
        InputError,
    },
    props: {
        products: Array,
    },
    methods: {
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
            this.$inertia.visit(route('products.show', row));
        },
        handleCommand(command) {
            const commandName = command.split('-')[0];
            const rowId = command.split('-')[1];

            this.$inertia.get(route('products.' + commandName, rowId));
        },
        handleDropdownCommand(command) {
            if (command == 'import') {
                this.showImportModal = true;
            } else if (command == 'export') {
                this.showExportModal = true;
            }
        },
        async deleteSelections() {
            try {
                const items_ids = this.$refs.multipleTableRef.value.map(item => item.id);
                const response = await axios.post(route('products.massive-delete', {
                    items_ids
                }));

                if (response.status === 200) {
                    this.$notify({
                        title: 'Correcto',
                        message: '',
                        type: 'success'
                    });

                    // update list of products
                    let deletedIndexes = [];
                    this.products.forEach((product, index) => {
                        if (items_ids.includes(product.id)) {
                            deletedIndexes.push(index);
                        }
                    });

                    // Ordenar los índices de forma descendente para evitar problemas de desplazamiento al eliminar elementos
                    deletedIndexes.sort((a, b) => b - a);

                    // Eliminar cotizaciones por índice
                    for (const index of deletedIndexes) {
                        this.products.splice(index, 1);
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
        async importProducts() {
            try {
                this.isImporting = true;
                const response = await axios.post(route('products.import'), {
                    file: this.importForm.file
                }, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                });

                if (response.status === 200) {
                    this.isImporting = false;
                    this.importWasSuccessful = true;
                    this.importWasWrong = false;
                    window.location.reload();
                }
            } catch (error) {
                this.isImporting = false;
                this.importWasWrong = true;
                this.importErrors = error.response.data.errors;
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
                        product.part_number_supplier.toLowerCase().includes(this.search.toLowerCase())
                )
            }
        }
    },
    mounted() {
        this.inputFocus();

        // buscar si en las variables de url esta el comando para abrir el modal de importacion 
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('openImportModal')) {
            this.showImportModal = true;
        }
    }
}
</script>
