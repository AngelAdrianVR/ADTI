<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElNotification } from 'element-plus';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    user: Object,
});

// Forms & State
const form = useForm({
    media: [],
    media_name: null,
});

const fileList = ref([]);
const showUploadModal = ref(false);
const editIndex = ref(null);
const mediaNameInput = ref(null);
const selectedFiles = ref([]);

// Methods
const handleChangeFile = (file, fileListRef) => {
    form.media = fileListRef.map(item => item.raw);
};

const handleRemoveFile = (file, fileListRef) => {
    const index = form.media.indexOf(file.raw);
    if (index !== -1) form.media.splice(index, 1);
};

const storeMedia = () => {
    form.post(route('users.store-media', props.user.id), {
        onSuccess: () => {
            ElNotification.success('Documentos subidos correctamente');
            showUploadModal.value = false;
            form.reset();
            fileList.value = [];
        },
        onError: () => ElNotification.error('Error al subir documentos'),
    });
};

const updateMediaName = () => {
    form.put(route('users.update-media-name', editIndex.value), {
        onSuccess: () => {
            ElNotification.success('Nombre actualizado');
            editIndex.value = null;
            form.reset();
        }
    });
};

const handleSelectionChange = (val) => {
    selectedFiles.value = val;
};

const deleteSelections = async () => {
    try {
        const items_ids = selectedFiles.value.map(item => item.id);
        const response = await axios.post(route('users.massive-delete-media'), { items_ids });
        
        if (response.status === 200) {
            ElNotification.success('Documentos eliminados');
            // Optimistic update
            const indexSet = new Set(items_ids);
            props.user.media = props.user.media.filter(m => !indexSet.has(m.id));
        }
    } catch (error) {
        console.error(error);
        ElNotification.error('No se pudo eliminar la selección');
    }
};

const startEdit = (row) => {
    form.media_name = row.file_name;
    editIndex.value = row.id;
    // Focus logic would go here if needed with nextTick
};

const openFile = (url) => {
    window.open(url, '_blank');
};

const getIcon = (mimeType) => {
    if (mimeType === 'application/pdf') return 'fa-regular fa-file-pdf text-red-500';
    if (mimeType.startsWith('image/')) return 'fa-regular fa-file-image text-blue-500';
    return 'fa-regular fa-file text-gray-400';
};
</script>

<template>
    <div class="px-2">
        <!-- Header Actions -->
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h3 class="text-lg font-bold text-gray-800 flex items-center self-start sm:self-auto">
                <i class="fa-solid fa-folder-tree text-primary mr-2"></i> Documentos ({{ user.media.length }})
            </h3>
            
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <el-button 
                    v-if="$page.props.auth.user.permissions?.includes('Eliminar expedientes de usuarios')"
                    type="danger" 
                    plain 
                    :disabled="selectedFiles.length === 0"
                    @click="deleteSelections"
                    class="!px-3"
                >
                    <i class="fa-solid fa-trash mr-2"></i> Eliminar
                </el-button>
                
                <PrimaryButton @click="showUploadModal = true">
                    <i class="fa-solid fa-cloud-arrow-up mr-2"></i> Subir Documento
                </PrimaryButton>
            </div>
        </div>

        <!-- Tabla -->
        <div class="border rounded-lg overflow-hidden shadow-sm">
            <el-table 
                :data="user.media" 
                @selection-change="handleSelectionChange" 
                style="width: 100%"
                empty-text="No hay documentos en el expediente"
            >
                <el-table-column v-if="$page.props.auth.user.permissions?.includes('Eliminar expedientes de usuarios')" type="selection" width="40" />
                
                <el-table-column label="Nombre del Archivo" min-width="200">
                    <template #default="scope">
                        <div v-if="editIndex === scope.row.id" class="flex items-center gap-2">
                            <el-input 
                                v-model="form.media_name" 
                                size="small" 
                                placeholder="Nuevo nombre"
                                @keyup.enter="updateMediaName"
                            />
                            <button @click="updateMediaName" class="text-green-600 hover:text-green-800"><i class="fa-solid fa-check"></i></button>
                            <button @click="editIndex = null" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div v-else class="flex items-center gap-3 cursor-pointer group" @click="openFile(scope.row.original_url)">
                            <i :class="[getIcon(scope.row.mime_type), 'text-xl']"></i>
                            <span class="text-sm text-gray-700 font-medium group-hover:text-primary transition-colors">
                                {{ scope.row.file_name }}
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Tamaño" width="120" align="right">
                    <template #default="scope">
                        <span class="text-xs text-gray-500">{{ (scope.row.size / 1024).toFixed(1) }} KB</span>
                    </template>
                </el-table-column>

                <el-table-column label="Acciones" width="100" align="right">
                    <template #default="scope">
                        <el-dropdown trigger="click">
                            <button class="p-2 text-gray-400 hover:text-primary rounded-full hover:bg-gray-100 transition-colors">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item @click="openFile(scope.row.original_url)">
                                        <i class="fa-solid fa-eye mr-2"></i> Ver / Descargar
                                    </el-dropdown-item>
                                    <el-dropdown-item 
                                        v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                                        @click="startEdit(scope.row)"
                                    >
                                        <i class="fa-solid fa-pen mr-2"></i> Cambiar nombre
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <!-- Modal Upload -->
        <DialogModal :show="showUploadModal" @close="showUploadModal = false">
            <template #title>
                <h2 class="text-lg font-bold text-gray-800">Subir al expediente digital</h2>
            </template>
            <template #content>
                <div class="p-4">
                    <el-upload
                        class="upload-demo"
                        drag
                        action="#"
                        :auto-upload="false"
                        :on-change="handleChangeFile"
                        :on-remove="handleRemoveFile"
                        v-model:file-list="fileList"
                        multiple
                    >
                        <i class="el-icon-upload"></i>
                        <div class="el-upload__text">
                            Arrastra archivos aquí o <em>haz clic para subir</em>
                        </div>
                        <template #tip>
                            <div class="text-xs text-gray-400 mt-2">
                                Archivos PDF o Imágenes. Máx 25MB.
                            </div>
                        </template>
                    </el-upload>
                </div>
            </template>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <SecondaryButton @click="showUploadModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton @click="storeMedia" :disabled="form.processing || !form.media.length">
                        <i v-if="form.processing" class="fa-solid fa-circle-notch fa-spin mr-2"></i>
                        Subir Archivos
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>
    </div>
</template>