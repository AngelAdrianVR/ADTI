<script setup>
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElNotification } from "element-plus";

const props = defineProps({
    category: Object,
});

const emit = defineEmits(['deleted']);

const treeData = ref([]);
const defaultProps = {
    children: 'children',
    label: 'label',
};

// Obtener URL de imagen de manera segura
const coverImage = computed(() => {
    return props.category.media?.find(m => m.collection_name === 'default')?.original_url;
});

// Organizar datos para el Tree View (Optimizado en cliente)
const organizeSubcategories = () => {
    if (!props.category.subcategories) return;

    const map = new Map();
    const roots = [];

    // 1. Mapeo inicial
    props.category.subcategories.forEach(sub => {
        map.set(sub.id, {
            id: sub.id,
            label: sub.name,
            features: sub.features,
            children: []
        });
    });

    // 2. Construcción del árbol
    props.category.subcategories.forEach(sub => {
        if (sub.prev_subcategory_id) {
            const parent = map.get(sub.prev_subcategory_id);
            if (parent) {
                parent.children.push(map.get(sub.id));
            }
        } else {
            roots.push(map.get(sub.id));
        }
    });

    treeData.value = roots;
};

const editCategory = () => {
    router.visit(route('categories.edit', props.category.id));
};

const deleteCategory = () => {
    router.delete(route('categories.destroy', props.category.id), {
        onSuccess: () => {
            ElNotification.success({
                title: 'Eliminado',
                message: 'Categoría eliminada correctamente',
            });
            emit('deleted');
        },
        onError: () => {
            ElNotification.error({
                title: 'Error',
                message: 'No se pudo eliminar. Verifica dependencias.',
            });
        }
    });
};

onMounted(() => {
    organizeSubcategories();
});
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col h-full group overflow-hidden">
        
        <!-- Header con Imagen -->
        <div class="p-4 border-b border-gray-50 bg-gradient-to-br from-gray-50 to-white relative">
            <div class="flex items-start gap-4">
                
                <!-- Imagen de Portada -->
                <div class="w-16 h-16 rounded-lg overflow-hidden border border-gray-100 shrink-0 bg-white shadow-sm group-hover:scale-105 transition-transform duration-500">
                    <img v-if="coverImage" 
                         :src="coverImage" 
                         :alt="category.name"
                         class="w-full h-full object-cover"
                    >
                    <div v-else class="w-full h-full flex items-center justify-center bg-gray-50 text-gray-300">
                        <i class="fa-regular fa-image text-xl"></i>
                    </div>
                </div>

                <!-- Info -->
                <div class="min-w-0 flex-1 pt-1">
                    <h3 class="font-bold text-gray-800 text-base leading-tight truncate mb-1" :title="category.name">
                        {{ category.name }}
                    </h3>
                    <div class="flex items-center text-xs text-[#6D6E72] space-x-2">
                        <span class="bg-gray-100 px-2 py-0.5 rounded text-[10px] font-mono border border-gray-200">
                            {{ category.key || 'N/A' }}
                        </span>
                        <span>{{ category.subcategories?.length || 0 }} subs</span>
                    </div>
                </div>

                <!-- Acciones (Flotantes/Visibles en Hover) -->
                <div class="flex flex-col gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity absolute top-3 right-3 sm:static">
                    <button 
                        v-if="$page.props.auth.user.permissions.includes('Editar categorias')"
                        @click="editCategory"
                        class="text-[#1676A2] hover:bg-blue-50 bg-white p-1.5 rounded-md shadow-sm border border-gray-100 transition-colors"
                        title="Editar"
                    >
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    
                    <el-popconfirm
                        v-if="$page.props.auth.user.permissions.includes('Eliminar categorias')"
                        title="¿Eliminar categoría?"
                        confirm-button-text="Sí"
                        cancel-button-text="No"
                        icon-color="#DC2626"
                        @confirm="deleteCategory"
                        width="180"
                    >
                        <template #reference>
                            <button class="text-[#6D6E72] hover:text-red-600 hover:bg-red-50 bg-white p-1.5 rounded-md shadow-sm border border-gray-100 transition-colors" title="Eliminar">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </template>
                    </el-popconfirm>
                </div>
            </div>
        </div>

        <!-- Body: Tree View -->
        <div class="p-4 flex-1 bg-white">
            <div v-if="treeData.length > 0" class="max-h-60 overflow-y-auto custom-scrollbar pr-1">
                <el-tree 
                    :data="treeData" 
                    :props="defaultProps" 
                    :expand-on-click-node="false"
                    node-key="id"
                    default-expand-all
                    class="custom-tree"
                >
                    <template #default="{ node, data }">
                        <span class="custom-tree-node flex items-center w-full overflow-hidden">
                            <span class="node-icon mr-2 shrink-0">
                                <i v-if="!node.isLeaf" class="fa-regular fa-folder text-amber-400 text-xs"></i>
                                <i v-else class="fa-solid fa-turn-up rotate-90 text-gray-300 text-[10px] ml-1"></i>
                            </span>
                            <span class="truncate text-sm text-gray-600 select-none" :title="node.label">{{ node.label }}</span>
                            <!-- Indicador sutil de características -->
                            <span v-if="data.features?.length" class="ml-auto mr-2 w-1.5 h-1.5 rounded-full bg-orange-300" title="Tiene características"></span>
                        </span>
                    </template>
                </el-tree>
            </div>
            
            <div v-else class="h-full flex flex-col items-center justify-center text-center py-8 opacity-60">
                <i class="fa-solid fa-network-wired text-gray-200 text-3xl mb-2"></i>
                <p class="text-xs text-gray-400 italic">Sin subcategorías definidas</p>
            </div>
        </div>
        
    </div>
</template>

<style scoped>
/* Estilos refinados para el Tree */
.custom-tree :deep(.el-tree-node__content) {
    height: 28px;
    border-radius: 4px;
    margin-bottom: 1px;
}
.custom-tree :deep(.el-tree-node__content:hover) {
    background-color: #f3f4f6;
}
.custom-tree :deep(.el-tree-node__expand-icon) {
    color: #9ca3af;
    font-size: 10px; 
    padding: 4px;
}
.custom-tree :deep(.el-tree-node__expand-icon.is-leaf) {
    color: transparent;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}
</style>