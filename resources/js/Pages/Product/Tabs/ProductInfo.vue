<script setup>
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import FileView from '@/Components/MyComponents/FileView.vue';

const props = defineProps({
    product: Object
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
};

const formatCurrency = (amount, currency) => {
    if (amount === undefined || amount === null) return '-';
    return `${amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")} ${currency || ''}`;
};

// Helpers para filtrar medios
const files = props.product.media?.filter(media => 
    media.collection_name === 'files' || 
    (media.collection_name !== 'imageCover' && !media.mime_type?.startsWith('image/'))
) || [];
</script>

<template>
    <div class="space-y-8">
        
        <!-- Sección 1: Datos Principales (Grid) -->
        <section>
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <span class="w-1 h-6 bg-primary rounded-full mr-2"></span>
                Detalles Generales
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-sm">
                <!-- Número de Parte Fabricante -->
                <div class="flex flex-col border-b border-gray-100 pb-2">
                    <span class="text-gray-500 font-medium mb-1">N.P. Fabricante</span>
                    <span class="text-gray-900 font-bold font-mono text-base">{{ product.part_number_supplier || '-' }}</span>
                </div>

                <!-- Número de Parte Interno -->
                <div class="flex flex-col border-b border-gray-100 pb-2">
                    <span class="text-gray-500 font-medium mb-1">N.P. Interno</span>
                    <span class="text-gray-900 font-mono">{{ product.part_number || '-' }}</span>
                </div>

                <!-- Fecha Alta -->
                <div class="flex flex-col border-b border-gray-100 pb-2">
                    <span class="text-gray-500 font-medium mb-1">Fecha de Alta</span>
                    <span class="text-gray-900 capitalize">{{ formatDate(product.created_at) }}</span>
                </div>

                <!-- Precio -->
                <div class="flex flex-col border-b border-gray-100 pb-2">
                    <span class="text-gray-500 font-medium mb-1">Precio de Lista</span>
                    <span class="text-emerald-600 font-bold">{{ formatCurrency(product.line_cost, product.currency) }}</span>
                </div>

                <!-- Ubicación -->
                <div class="flex flex-col border-b border-gray-100 pb-2 md:col-span-2">
                    <span class="text-gray-500 font-medium mb-1">Ubicación Almacén</span>
                    <span class="text-gray-900 flex items-center">
                        <i class="fa-solid fa-map-pin text-gray-400 mr-2"></i>
                        {{ product.location || 'Sin ubicación asignada' }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Sección 2: Categorización y Descripción -->
        <section>
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <span class="w-1 h-6 bg-primary rounded-full mr-2"></span>
                Descripción y Clasificación
            </h3>
            
            <div class="bg-gray-50 rounded-lg p-5 border border-gray-100 space-y-4">
                <!-- Categoría / Breadcrumbs -->
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-2">Ruta de Categoría</span>
                    <nav class="flex flex-wrap items-center text-sm text-gray-600">
                        <span class="font-semibold text-primary bg-primary/10 px-2 py-0.5 rounded">{{ product.subcategory?.category?.name }}</span>
                        
                        <template v-if="product.bread_crumbles?.length">
                            <i class="fa-solid fa-chevron-right text-xs mx-2 text-gray-300"></i>
                            <div class="flex items-center flex-wrap gap-2">
                                <template v-for="(sub, index) in product.bread_crumbles" :key="index">
                                    <span class="hover:text-gray-900 transition-colors">{{ sub }}</span>
                                    <i v-if="index < product.bread_crumbles.length - 1" class="fa-solid fa-chevron-right text-[10px] text-gray-300"></i>
                                </template>
                            </div>
                        </template>
                    </nav>
                </div>

                <!-- Descripción Larga -->
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-2">Descripción Detallada</span>
                    <p class="text-gray-700 leading-relaxed text-sm whitespace-pre-line">
                        {{ product.description || 'Sin descripción disponible.' }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Sección 3: Características Técnicas -->
        <section v-if="product.features?.length">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <span class="w-1 h-6 bg-primary rounded-full mr-2"></span>
                Características Técnicas
            </h3>
            
            <div class="overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="(feature, index) in product.features" :key="index" 
                            class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm font-medium text-gray-500 bg-gray-50/50 w-1/3">
                                {{ feature.name }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-900 font-semibold">
                                {{ feature.value }}
                                <span v-if="feature.measure_unit !== 'No aplica'" class="text-gray-500 font-normal ml-1 text-xs">
                                    {{ feature.measure_unit }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Sección 4: Archivos Adjuntos -->
        <section>
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <span class="w-1 h-6 bg-primary rounded-full mr-2"></span>
                Documentación y Descargables
            </h3>

            <div v-if="files.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <FileView v-for="file in files" :key="file.id" :file="file" />
            </div>
            
            <div v-else class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                <i class="fa-regular fa-folder-open text-3xl text-gray-300 mb-2"></i>
                <p class="text-sm text-gray-500">No hay documentos adjuntos para este producto.</p>
            </div>
        </section>

    </div>
</template>