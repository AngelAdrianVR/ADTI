<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { ElNotification } from "element-plus";

const props = defineProps({
    products: Array,
});

// State
const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const showImportModal = ref(false);
const searchInput = ref(null);

const importForm = useForm({
    file: null,
});

// Computed: Filtrado y Paginación
const filteredProducts = computed(() => {
    if (!search.value) return props.products;
    const lowerSearch = search.value.toLowerCase();
    
    return props.products.filter(product => 
        (product.name && product.name.toLowerCase().includes(lowerSearch)) ||
        (product.part_number_supplier && product.part_number_supplier.toLowerCase().includes(lowerSearch)) ||
        (product.subcategory?.category?.name && product.subcategory.category.name.toLowerCase().includes(lowerSearch)) ||
        (product.location && product.location.toLowerCase().includes(lowerSearch))
    );
});

const paginatedProducts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredProducts.value.slice(start, end);
});

// Helpers
const getCoverImage = (product) => {
    return product.media?.find(img => img.collection_name === 'default')?.original_url;
};

// Methods
const handleRowClick = (row) => {
    router.visit(route('products.show', row.id));
};

const handlePageChange = (val) => {
    currentPage.value = val;
};

const editProduct = (id) => {
    router.visit(route('products.edit', id));
};

const deleteProduct = (id) => {
    router.delete(route('products.destroy', id), {
        onSuccess: () => {
            ElNotification.success({
                title: 'Éxito',
                message: 'Producto eliminado correctamente'
            });
        },
        onError: () => {
            ElNotification.error({
                title: 'Error',
                message: 'No se pudo eliminar el producto'
            });
        }
    });
};

const handleDropdownCommand = (command) => {
    if (command === 'import') {
        showImportModal.value = true;
    } else if (command === 'export') {
        window.open(route('products.export'), '_blank');
    } else if (command === 'template') {
        window.open(route('products.import-template'), '_blank');
    }
};

const importProducts = () => {
    importForm.post(route('products.import'), {
        onSuccess: () => {
            ElNotification.success({
                title: 'Éxito',
                message: 'Productos importados correctamente'
            });
            showImportModal.value = false;
            importForm.reset();
            router.reload();
        },
        onError: () => {
            ElNotification.error({
                title: 'Error',
                message: 'Hubo un problema al importar el archivo'
            });
        }
    });
};

onMounted(() => {
    // Foco en buscador
    nextTick(() => {
        searchInput.value?.focus();
    });

    // Abrir modal si viene en URL (para deep linking)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('openImportModal')) {
        showImportModal.value = true;
        // Limpiar URL
        urlParams.delete('openImportModal');
        const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
        window.history.replaceState({}, '', newUrl);
    }
});
</script>

<template>
    <AppLayout title="Productos">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Catálogo de Productos</h1>
                        <p class="text-xs text-gray-500 mt-1">Gestión de inventario y referencias.</p>
                    </div>
                    
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <!-- Buscador -->
                        <div class="relative w-full sm:w-72">
                            <input 
                                v-model="search" 
                                type="text" 
                                ref="searchInput"
                                placeholder="Buscar producto..." 
                                class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2] text-sm shadow-sm"
                            >
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                        </div>

                        <!-- Botón Crear / Dropdown Acciones -->
                        <div class="flex items-center">
                            <el-dropdown 
                                v-if="$page.props.auth.user.permissions?.includes('Crear productos')" 
                                split-button 
                                type="primary" 
                                @click="router.visit(route('products.create'))" 
                                @command="handleDropdownCommand"
                                class="!bg-[#1676A2] !border-[#1676A2]"
                            >
                                <span class="flex items-center gap-2"><i class="fa-solid fa-plus"></i> Crear Producto</span>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Importar productos')" command="import">
                                            <i class="fa-solid fa-file-import mr-2"></i> Importar Excel
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Exportar productos')" command="export">
                                            <i class="fa-solid fa-file-export mr-2"></i> Exportar Excel
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Importar productos')" command="template" divided>
                                            <i class="fa-solid fa-download mr-2"></i> Descargar Plantilla
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Productos -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <el-table 
                        :data="paginatedProducts" 
                        @row-click="handleRowClick"
                        style="width: 100%"
                        class="cursor-pointer"
                        :row-class-name="'hover:bg-gray-50 transition-colors'"
                    >
                        <!-- Imagen y Nombre -->
                        <el-table-column label="Producto" min-width="250">
                            <template #default="scope">
                                <div class="flex items-center gap-4 py-2">
                                    <!-- <div class="w-12 h-12 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center shrink-0 overflow-hidden">
                                        <img v-if="getCoverImage(scope.row)" 
                                             :src="getCoverImage(scope.row)" 
                                             class="w-full h-full object-contain mix-blend-multiply" 
                                             alt="Cover"
                                        >
                                        <i v-else class="fa-regular fa-image text-gray-300 text-lg"></i>
                                    </div> -->
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-800 text-sm truncate" :title="scope.row.name">
                                            {{ scope.row.name }}
                                        </p>
                                        <p class="text-xs text-gray-500 truncate" :title="scope.row.part_number_supplier">
                                            NP: {{ scope.row.part_number_supplier || 'N/A' }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Categoría -->
                        <el-table-column label="Categoría / Ruta" min-width="200">
                            <template #default="scope">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-[#1676A2]">
                                        {{ scope.row.subcategory?.category?.name || 'Sin Categoría' }}
                                    </span>
                                    <span class="text-xs text-gray-400 truncate" v-if="scope.row.bread_crumbles">
                                        {{ scope.row.bread_crumbles.join(' > ') }}
                                    </span>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Ubicación -->
                        <el-table-column label="Ubicación" width="150">
                            <template #default="scope">
                                <span v-if="scope.row.location" class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                    <i class="fa-solid fa-location-dot mr-1 text-gray-400"></i>
                                    {{ scope.row.location }}
                                </span>
                                <span v-else class="text-gray-400 text-xs italic">-</span>
                            </template>
                        </el-table-column>

                        <!-- Acciones -->
                        <el-table-column align="right" width="120">
                            <template #default="scope">
                                <div class="flex items-center justify-end gap-1">
                                    <button 
                                        v-if="$page.props.auth.user.permissions.includes('Editar productos')"
                                        @click.stop="editProduct(scope.row.id)" 
                                        class="p-2 text-gray-400 hover:text-[#1676A2] hover:bg-blue-50 rounded-full transition-colors"
                                        title="Editar"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <el-popconfirm 
                                        v-if="$page.props.auth.user.permissions.includes('Eliminar productos')"
                                        title="¿Eliminar este producto?"
                                        confirm-button-text="Sí, eliminar"
                                        cancel-button-text="No"
                                        icon-color="#DC2626"
                                        @confirm="deleteProduct(scope.row.id)"
                                        width="200"
                                    >
                                        <template #reference>
                                            <button @click.stop class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-full transition-colors" title="Eliminar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </template>
                                    </el-popconfirm>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- Paginación -->
                    <div class="px-4 py-3 border-t border-gray-100 flex justify-between items-center bg-gray-50">
                        <p class="text-xs text-gray-500">
                            Mostrando {{ paginatedProducts.length }} de {{ filteredProducts.length }} productos
                        </p>
                        <el-pagination 
                            layout="prev, pager, next" 
                            :total="filteredProducts.length" 
                            :page-size="itemsPerPage"
                            @current-change="handlePageChange"
                            background
                            small
                        />
                    </div>
                </div>

            </div>
        </main>

        <!-- Modal de Importación -->
        <DialogModal :show="showImportModal" @close="showImportModal = false">
            <template #title>
                <span class="font-bold text-gray-800">Importar Productos</span>
            </template>
            <template #content>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">
                        Sube un archivo Excel (.xlsx) con la estructura correcta para cargar productos masivamente.
                        <a :href="route('products.import-template')" target="_blank" class="text-[#1676A2] hover:underline font-medium">
                            Descargar plantilla aquí.
                        </a>
                    </p>
                    
                    <div class="flex items-center justify-center w-full">
                        <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 mb-2"></i>
                                <p class="text-sm text-gray-500"><span class="font-semibold">Clic para subir</span> o arrastra el archivo</p>
                                <p class="text-xs text-gray-500">XLSX (MAX. 10MB)</p>
                            </div>
                            <input id="dropzone-file" type="file" class="hidden" @input="importForm.file = $event.target.files[0]" accept=".xlsx" />
                        </label>
                    </div>
                    
                    <div v-if="importForm.file" class="flex items-center gap-2 text-sm text-green-600 bg-green-50 p-2 rounded border border-green-200">
                        <i class="fa-solid fa-file-excel"></i>
                        {{ importForm.file.name }}
                    </div>
                    <InputError :message="importForm.errors.file" />
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showImportModal = false" class="mr-2">Cancelar</SecondaryButton>
                <PrimaryButton @click="importProducts" :disabled="!importForm.file || importForm.processing">
                    <i v-if="importForm.processing" class="fa-solid fa-circle-notch fa-spin mr-2"></i>
                    Importar
                </PrimaryButton>
            </template>
        </DialogModal>

    </AppLayout>
</template>

<style scoped>
/* Estilo personalizado para el dropdown split de Element Plus */
:deep(.el-dropdown-menu__item:not(.is-disabled):focus) {
    background-color: #f0f9ff;
    color: #1676A2;
}
</style>