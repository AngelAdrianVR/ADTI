<template>
    <div class="w-full">
        <input type="file" ref="fileInput" style="display: none" @change="handleFileInputChange" :multiple="multiple" :accept="getAcceptedFormat()" />
        <button type="button" @click="openFileBrowser">
            <p class="flex items-center space-x-2 text-sm text-primary cursor-pointer flex-shrink-0 flex-grow-0">
                <!-- <i class="fa-solid fa-plus"></i> -->
                <i class="fa-solid fa-paperclip"></i>
                <span>Adjuntar {{ multiple ? 'archivos' : 'archivo' }}</span>
            </p>
        </button>

        <div v-if="selectedFiles.length">
            <ul class="grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-3 text-sm mt-2">
                <li v-for="(file, index) in selectedFiles" :key="index" class="flex items-center justify-between rounded-md p-2 border">
                    <p class="flex items-center">
                        <!-- <i v-if="currentFiles" :class="getFileTypeIcon(file.file_name)"></i> -->
                        <i :class="getFileTypeIcon(file.name)"></i>
                        <span class="ml-2 w-32 truncate">{{ file.name }}</span>
                    </p>
                    <button type="button" @click="removeFile(index)"><i class="fa-solid fa-xmark text-xs ml-2 text-red-700"></i></button>
                </li>
            </ul>
        </div>
    </div>
</template>
  
<script>
export default {
    data() {
        return {
            selectedFiles: [],
        };
    },
    props: {
        multiple: {
            type: Boolean,
            default: true
        },
        acceptedFormat: {
            type: String,
            default: 'Todo'
        },
        // currentFiles: {
        //     type: Array,
        //     default: null,
        // },
    },
    emits: ['files-selected'],
    methods: {
        getAcceptedFormat() {
            const format = this.acceptedFormat.toLowerCase();
            if (format === 'video') return 'video/*';
            else if (format === 'pdf') return 'application/pdf';
            else if (format === 'imagen') return 'image/*';
            else if (format === 'excel') return '.xlsx, .xls';
            else '*/*';
        },
        openFileBrowser() {
            // Simula el clic en el input file al hacer clic en el botón personalizado
            this.$refs.fileInput.click();
        },
        handleFileInputChange(event) {
            // Agrega los archivos seleccionados a la lista existente
            const newSelectedFiles = Array.from(event.target.files);
            if (this.multiple) {
                this.selectedFiles = [...this.selectedFiles, ...newSelectedFiles];
            } else {
                this.selectedFiles = [...newSelectedFiles];
            }
            this.$emit('files-selected', this.selectedFiles);
        },
        removeFile(index) {
            this.selectedFiles.splice(index, 1); // Elimina el archivo de la lista por su índice
            this.$emit('files-selected', this.selectedFiles); // Emitir la lista actualizada después de la eliminación
        },
        getFileTypeIcon(fileName) {
            // Asocia extensiones de archivo a iconos
            const fileExtension = fileName?.split('.').pop().toLowerCase();
            switch (fileExtension) {
                case 'pdf':
                    return 'fa-regular fa-file-pdf text-red-700';
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                    return 'fa-regular fa-image text-blue-300';
                case 'mp4':
                case 'avi':
                case 'mkv':
                case 'mov':
                    return 'fa-regular fa-file-video text-sky-400';
                case 'xlsx':
                case 'xls':
                    return 'fa-regular fa-file-excel text-green-600';
                default:
                    return 'fa-regular fa-file-lines';
            }
        },
    },
    // mounted() {
    //     if ( this.currentFiles ) {
    //         // guarda en el arreglo de archivos seleccionados los que ya tiene el producto en caso de editar
    //         this.selectedFiles = this.currentFiles;
    //         // emite el evento para cargar los archivos al formulario
    //         this.$emit('files-selected', this.selectedFiles);
    //     }
    // }
};
</script>
  