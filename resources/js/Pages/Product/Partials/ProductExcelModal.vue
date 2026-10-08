<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { ElNotification } from "element-plus";
import { Search } from '@element-plus/icons-vue';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    // 'export'   -> exportar productos filtrando por categoría / subcategoría
    // 'template' -> descargar la plantilla de importación de una subcategoría
    mode: {
        type: String,
        default: 'export',
    },
});

const emit = defineEmits(['close']);

const isTemplate = computed(() => props.mode === 'template');

// --- Árbol de categorías y subcategorías ---
const treeRef = ref(null);
const categories = ref([]);
const loadingOptions = ref(false);
const optionsLoaded = ref(false);
const filter = ref('');

// --- Opciones del modo exportación ---
const exportAll = ref(false); // Por defecto se eligen categorías / subcategorías concretas
const includeFeatures = ref(true);
const includeCosts = ref(true);
const checkedProductsCount = ref(0);
const downloading = ref(false); // El servidor está generando el archivo Excel

// --- Selección del modo plantilla ---
const templateSubcategoryId = ref(null);
const templateSubcategoryLabel = ref('');
const templateWithProducts = ref(false);

const treeProps = computed(() => ({
    label: 'label',
    children: 'children',
    // En el modo plantilla solo se puede elegir una subcategoría.
    disabled: (data) => isTemplate.value && data.type === 'category',
}));

const totalProducts = computed(() =>
    categories.value.reduce((total, category) => total + (category.products_count || 0), 0)
);

const selectedProductsCount = computed(() => (exportAll.value ? totalProducts.value : checkedProductsCount.value));

// Texto del botón principal: se convierte en mensaje de progreso mientras se genera el archivo.
const submitLabel = computed(() => {
    if (downloading.value) {
        return isTemplate.value ? 'Generando plantilla...' : 'Exportando...';
    }

    return isTemplate.value ? 'Descargar plantilla' : 'Exportar Excel';
});

const productCountLabel = (count) => `${count} ${count === 1 ? 'producto' : 'productos'}`;

// el-tree exige un método de comparación para poder usar filter(); sin él lanza
// "[Tree] filterNodeMethod is required when filter".
const filterNode = (value, data) => {
    const query = `${value ?? ''}`.trim().toLowerCase();

    if (!query) {
        return true;
    }

    return `${data?.label ?? ''}`.toLowerCase().includes(query);
};

const loadOptions = async () => {
    if (loadingOptions.value || optionsLoaded.value) {
        return;
    }

    loadingOptions.value = true;

    try {
        const { data } = await axios.get(route('products.export-options'));
        categories.value = data.categories ?? [];
        optionsLoaded.value = true;
    } catch (error) {
        console.error(error);
        ElNotification.error({
            title: 'Error',
            message: 'No se pudieron cargar las categorías.',
        });
    } finally {
        loadingOptions.value = false;
    }
};

// Total de productos de los nodos marcados, sin contar dos veces los de un nodo padre.
const updateCheckedProductsCount = () => {
    const tree = treeRef.value;

    if (!tree) {
        checkedProductsCount.value = 0;
        return;
    }

    const checkedNodes = tree.getCheckedNodes(false, false);
    const checkedKeys = new Set(checkedNodes.map((node) => node.node_key));
    let total = 0;

    checkedNodes.forEach((node) => {
        let parent = tree.getNode(node.node_key)?.parent;

        while (parent && parent.data) {
            if (checkedKeys.has(parent.data.node_key)) {
                return;
            }

            parent = parent.parent;
        }

        total += node.products_count || 0;
    });

    checkedProductsCount.value = total;
};

const buildQueryString = (params) => {
    const searchParams = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach((item) => searchParams.append(`${key}[]`, item));
            return;
        }

        searchParams.append(key, value);
    });

    const query = searchParams.toString();

    return query ? `?${query}` : '';
};

// Ids marcados en el árbol. Las subcategorías que ya caen dentro de una categoría
// marcada se descartan: el backend las incluye igual y así no se envían cientos
// de ids redundantes (una 'Electrónica' completa puede ser media URL).
const selectedTreeIds = () => {
    const checkedNodes = treeRef.value ? treeRef.value.getCheckedNodes(false, false) : [];
    const categoryIds = checkedNodes
        .filter((node) => node.type === 'category')
        .map((node) => node.id);
    const selectedCategoryIds = new Set(categoryIds);
    const categoryIdBySubcategoryId = new Map();

    const indexSubcategories = (nodes, categoryId = null) => {
        (nodes ?? []).forEach((node) => {
            const currentCategoryId = node.type === 'category' ? node.id : categoryId;

            if (node.type === 'subcategory') {
                categoryIdBySubcategoryId.set(node.id, currentCategoryId);
            }

            indexSubcategories(node.children, currentCategoryId);
        });
    };

    indexSubcategories(categories.value);

    const subcategoryIds = checkedNodes
        .filter((node) => node.type === 'subcategory')
        .map((node) => node.id)
        .filter((id) => !selectedCategoryIds.has(categoryIdBySubcategoryId.get(id)));

    return { categoryIds, subcategoryIds };
};

// El nombre real del archivo viene en Content-Disposition; si no se puede leer se usa un respaldo.
const filenameFromResponse = (response, fallback) => {
    const disposition = response?.headers?.['content-disposition'] ?? '';
    const utf8Match = /filename\*\s*=\s*(?:UTF-8'')?([^;]+)/i.exec(disposition);
    const plainMatch = /filename\s*=\s*"?([^";]+)"?/i.exec(disposition);
    const raw = (utf8Match?.[1] ?? plainMatch?.[1] ?? '').trim();

    if (!raw) {
        return fallback;
    }

    try {
        return decodeURIComponent(raw);
    } catch (error) {
        return raw;
    }
};

// Cuando el backend responde con error, el mensaje viene dentro del blob.
const errorMessageFrom = async (error) => {
    const data = error?.response?.data;

    if (data instanceof Blob) {
        try {
            const parsed = JSON.parse(await data.text());

            if (parsed?.message) {
                return parsed.message;
            }
        } catch (parseError) {
            // La respuesta no era JSON (por ejemplo un error del servidor): se usa el mensaje genérico.
        }
    }

    return 'No se pudo generar el archivo. Intenta de nuevo.';
};

// La descarga se pide como blob y se dispara con un enlace oculto: así no se abre
// otra pestaña y mientras el servidor genera el archivo se puede mostrar el spinner.
const downloadFile = async (url, notification, fallbackFilename, request = {}) => {
    if (downloading.value) {
        return;
    }

    downloading.value = true;

    try {
        const response = await axios.request({
            url,
            method: request.method ?? 'get',
            responseType: 'blob',
            ...(request.data ? { data: request.data } : {}),
        });
        const objectUrl = window.URL.createObjectURL(
            new Blob([response.data], {
                type: response?.headers?.['content-type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            })
        );

        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = filenameFromResponse(response, fallbackFilename);
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        link.remove();

        // La URL se libera después para no interrumpir la descarga que acaba de iniciar.
        window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 60000);

        emit('close');
        ElNotification.success(notification);
    } catch (error) {
        console.error(error);
        ElNotification.error({
            title: 'Error',
            message: await errorMessageFrom(error),
        });
    } finally {
        downloading.value = false;
    }
};

const confirmExport = () => {
    const payload = {
        include_features: includeFeatures.value ? 1 : 0,
        include_costs: includeCosts.value ? 1 : 0,
    };

    if (!exportAll.value) {
        const { categoryIds, subcategoryIds } = selectedTreeIds();

        if (categoryIds.length === 0 && subcategoryIds.length === 0) {
            ElNotification.warning({
                title: 'Sin selección',
                message: 'Elige al menos una categoría o subcategoría, o activa "Exportar todo el catálogo".',
            });
            return;
        }

        if (categoryIds.length) {
            payload.category_ids = categoryIds;
        }

        if (subcategoryIds.length) {
            payload.subcategory_ids = subcategoryIds;
        }
    }

    // La selección viaja en el cuerpo (POST): con la URL, una categoría completa
    // supera el tamaño máximo de petición del servidor y la conexión se corta.
    downloadFile(route('products.export'), {
        title: 'Exportación lista',
        message: 'La descarga del archivo Excel comenzará en unos segundos.',
    }, 'productos.xlsx', { method: 'post', data: payload });
};

const confirmTemplate = () => {
    if (!templateSubcategoryId.value) {
        ElNotification.warning({
            title: 'Sin selección',
            message: 'Elige la subcategoría de la que quieres descargar la plantilla.',
        });
        return;
    }

    const params = templateWithProducts.value ? { withProducts: 1 } : {};
    const url = `${route('subcategories.download-excel-template', templateSubcategoryId.value)}${buildQueryString(params)}`;

    downloadFile(url, {
        title: 'Plantilla generada',
        message: `Se descargará la plantilla de "${templateSubcategoryLabel.value}".`,
    }, 'plantilla_productos.xlsx');
};

const handleNodeClick = (data) => {
    if (!isTemplate.value || data.type !== 'subcategory') {
        return;
    }

    templateSubcategoryId.value = data.id;
    templateSubcategoryLabel.value = data.label;
};

const confirm = () => (isTemplate.value ? confirmTemplate() : confirmExport());

const close = () => emit('close');

watch(
    () => props.show,
    (show) => {
        if (!show) {
            return;
        }

        // Cada apertura arranca sin selección previa (y sin exportar todo el catálogo).
        exportAll.value = false;
        includeFeatures.value = true;
        includeCosts.value = true;
        checkedProductsCount.value = 0;
        filter.value = '';
        templateSubcategoryId.value = null;
        templateSubcategoryLabel.value = '';
        templateWithProducts.value = false;

        treeRef.value?.setCheckedKeys([]);
        treeRef.value?.setCurrentKey(null);

        loadOptions();
    }
);

watch(filter, (value) => treeRef.value?.filter(value));
</script>

<template>
    <DialogModal :show="show" max-width="2xl" @close="close">
        <template #title>
            <span class="font-bold text-gray-800">
                {{ isTemplate ? 'Descargar plantilla de productos' : 'Exportar productos a Excel' }}
            </span>
        </template>

        <template #content>
            <div class="space-y-4">
                <p class="text-sm text-gray-600">
                    {{ isTemplate
                        ? 'Elige la subcategoría para la que quieres generar la plantilla de importación. La plantilla incluye sus campos prellenados y sus características.'
                        : 'Selecciona las categorías o subcategorías que quieres incluir en el archivo Excel, o exporta el catálogo completo. Al elegir una categoría o subcategoría se incluyen también todas sus subcategorías hijas.' }}
                </p>

                <!-- Buscador del árbol -->
                <el-input
                    v-model="filter"
                    clearable
                    :disabled="loadingOptions || downloading"
                    placeholder="Buscar categoría o subcategoría..."
                >
                    <template #prefix>
                        <el-icon><Search /></el-icon>
                    </template>
                </el-input>

                <!-- Modo exportación: filtro por categoría / subcategoría -->
                <div v-if="!isTemplate" class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Exportar todo el catálogo</p>
                        <p class="text-xs text-gray-500">Desactívalo para elegir categorías y subcategorías concretas.</p>
                    </div>
                    <el-switch v-model="exportAll" :disabled="downloading" />
                </div>

                <!-- Árbol de categorías y subcategorías -->
                <div
                    v-loading="loadingOptions"
                    element-loading-text="Cargando categorías, esto puede demorar varios segundos"
                    class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg p-2"
                    :class="{ 'opacity-50 pointer-events-none select-none': (!isTemplate && exportAll) || downloading }"
                >
                    <el-tree
                        ref="treeRef"
                        :data="categories"
                        :props="treeProps"
                        node-key="node_key"
                        :show-checkbox="!isTemplate"
                        :check-on-click-node="!isTemplate"
                        highlight-current
                        :filter-node-method="filterNode"
                        :empty-text="loadingOptions
                            ? 'Cargando categorías, esto puede demorar varios segundos...'
                            : 'No hay categorías registradas'"
                        @check="updateCheckedProductsCount"
                        @node-click="handleNodeClick"
                    >
                        <template #default="{ data }">
                            <div class="flex items-center justify-between w-full pr-2">
                                <span
                                    class="text-sm"
                                    :class="data.type === 'category' ? 'font-semibold text-gray-800' : 'text-gray-600'"
                                >
                                    {{ data.label }}
                                </span>
                                <el-tag
                                    size="small"
                                    effect="plain"
                                    :type="data.type === 'category' ? 'info' : ''"
                                    class="ml-2 shrink-0"
                                >
                                    {{ productCountLabel(data.products_count || 0) }}
                                </el-tag>
                            </div>
                        </template>
                    </el-tree>
                </div>

                <!-- Opciones del modo exportación -->
                <div v-if="!isTemplate" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                        <span class="text-sm text-gray-700">Incluir características</span>
                        <el-switch v-model="includeFeatures" :disabled="downloading" />
                    </div>
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                        <span class="text-sm text-gray-700">Incluir moneda y costo</span>
                        <el-switch v-model="includeCosts" :disabled="downloading" />
                    </div>
                </div>

                <!-- Opciones del modo plantilla -->
                <template v-else>
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Incluir los productos existentes</p>
                            <p class="text-xs text-gray-500">Los productos de la subcategoría se agregan como filas de ejemplo.</p>
                        </div>
                        <el-switch v-model="templateWithProducts" :disabled="downloading" />
                    </div>

                    <p class="text-xs text-gray-500">
                        {{ templateSubcategoryLabel
                            ? `Subcategoría seleccionada: ${templateSubcategoryLabel}`
                            : 'Ninguna subcategoría seleccionada. Haz clic en una subcategoría del listado.' }}
                    </p>
                </template>

                <p v-if="!isTemplate" class="text-xs text-gray-500">
                    {{ selectedProductsCount > 0
                        ? `Productos a exportar: ${productCountLabel(selectedProductsCount)}.`
                        : 'No hay productos que coincidan con la selección actual.' }}
                </p>
            </div>
        </template>

        <template #footer>
            <SecondaryButton type="button" @click="close">
                Cancelar
            </SecondaryButton>

            <PrimaryButton
                type="button"
                class="ml-2 inline-flex items-center justify-center"
                :disabled="downloading"
                @click="confirm"
            >
                <svg
                    v-if="downloading"
                    class="animate-spin mr-2 h-4 w-4 text-white"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
                </svg>
                {{ submitLabel }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>

