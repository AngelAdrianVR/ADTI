<template>
    <section class="mt-4 mb-10 mx-4 text-xs lg:text-sm">
        <div class="flex justify-end lg:mx-20">
            <PrimaryButton @click="showUploadModal = true">Subir formatos</PrimaryButton>
        </div>
        <!-- pagination -->
        <div class="flex space-x-2 items-center ml-16">
            <el-pagination @current-change="handlePagination" layout="prev, pager, next"
                :total="user.media.length" hide-on-single-page />
            <div v-if="$page.props.auth.user.permissions?.includes('Eliminar expedientes de usuarios')"
                class="mt-2 lg:mt-0">
                <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#0355B5" title="¿Continuar?"
                    @confirm="deleteSelections">
                    <template #reference>
                        <el-button type="danger" plain class="mb-3"
                            :disabled="disableMassiveActions">Eliminar</el-button>
                    </template>
                </el-popconfirm>
            </div>
        </div>
        <el-table :data="user.media" @row-click="handleRowClick" max-height="670" style="width: 90%" class="mx-auto"
            :default-sort="{ prop: 'file_name', order: 'descending' }" @selection-change="handleSelectionChange"
            ref="multipleTableRef" :row-class-name="tableRowClassName">
            <el-table-column v-if="$page.props.auth.user.permissions?.includes('Eliminar expedientes de usuarios')" type="selection" width="30" />
            <el-table-column prop="file_name" label="Nombre del documento" width="360" sortable>
                <template #default="scope">
                    <div v-if="editIndex == scope.row.id" class="flex items-center space-x-2">
                        <el-input v-model="form.media_name" placeholder="Llenar campo*" size="small" :maxlength="255"
                            ref="mediaNameInput" clearable />
                        <div class="flex items-center space-x-1">
                            <el-tooltip content="Actualizar" placement="top">
                                <button type="button" @click.stop="updateMediaName"
                                    class="flex items-center justify-center bg-primary text-white size-5 text-[10px] rounded-full"
                                    :disabled="form.processing">
                                    <i v-if="form.processing"
                                        class="fa-sharp fa-solid fa-circle-notch fa-spin text-white"></i>
                                    <i v-else class="fa-solid fa-check"></i>
                                </button>
                            </el-tooltip>
                            <el-tooltip content="Cancelar" placement="top">
                                <button type="button" @click.stop="editIndex = null; form.reset()"
                                    class="flex items-center justify-center bg-grayED text-gray37 size-5 text-[10px] rounded-full"
                                    :disabled="form.processing"><i class="fa-solid fa-xmark"></i></button>
                            </el-tooltip>
                        </div>
                    </div>
                    <p v-else class="flex items-center space-x-2">
                        <svg v-if="scope.row.mime_type == 'application/pdf'" width="20" height="23" viewBox="0 0 23 25"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M3 10.5V4.5C3.65974 2.48879 4.27299 1.6077 6.5 1H15M22.5 8C22.5 5 18.5 1 16 1H15M22.5 8V20C21.7175 22.4962 20.8229 23.3489 18.5 24H8.5M22.5 8H15V1"
                                stroke="#999999" />
                            <path
                                d="M13 15C13.9763 15 14.5237 15 15.5 15H16H13ZM13 15V17.5M13 15V20.5V20V17.5M13 17.5C13.5858 17.5 13.9142 17.5 14.5 17.5H15M1 20.5V17.5M1 17.5V15.5V15H3C3.5 15 4.16509 15.6769 4 16.5C3.80649 17.4648 2.89034 17.5325 1 17.5ZM7 14.9961V19.9961H8.5C10.5 19.4961 10.5 15.4961 8.5 14.9961H7Z"
                                stroke="#1676A2" />
                        </svg>
                        <svg v-else-if="scope.row.mime_type == 'image/jpeg'" width="20" height="23" viewBox="0 0 23 25"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M3 10.5V4.5C3.65974 2.48879 4.27299 1.6077 6.5 1H15M22.5 8C22.5 5 18.5 1 16 1H15M22.5 8V20C21.7175 22.4962 20.8229 23.3489 18.5 24H8.5M22.5 8H15V1"
                                stroke="#999999" />
                            <path
                                d="M3 19.5V19M7 17.4961V15.4961V14.9961H9C9.5 14.9961 10.1651 15.673 10 16.4961C9.80649 17.4609 8.89034 17.5286 7 17.4961ZM7 17.4961V20.4961M3 19V14.5V15V19ZM3 19C3.07156 19.8864 2.76078 19.9902 2 20H1V19.5M16.5 14.9961L15.5 15L14 15.0039C13.4697 15.0909 13.2021 15.1827 13 16.004V19.004C13.1442 19.6947 13.4554 19.8562 14 20.004L16 20C16.4684 19.9022 16.5 19.5 16.5 19C16.5435 17.8616 16.5023 17.4381 16 17.5H15H14.5"
                                stroke="#1676A2" />
                        </svg>
                        <svg v-else width="20" height="23" viewBox="0 0 23 25" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M17 19C17.0349 19.6241 16.829 19.839 16.5 20H14C13.4554 19.8522 13.1442 19.6907 13 19V16C13.2021 15.1787 13.4697 15.087 14 14.9999L16 15C16.7638 15.0516 17.0252 15.1815 17 15.9999M1 14.9999V19.9999H2.5C4.5 19.4999 4.5 15.4999 2.5 14.9999H1ZM7 15.9999C7.09898 15.1856 7.20763 14.963 7.5 14.9999H9.5C9.76716 15.0967 9.95392 15.366 10 15.9999V19C9.95493 19.7586 9.82146 19.9149 9.5 20H7.5C7.14009 19.8538 7.04177 19.6183 7 19V15.9999Z"
                                stroke="#1676A2" />
                            <path
                                d="M3 10.5V4.5C3.65974 2.48879 4.27299 1.6077 6.5 1H15M22.5 8C22.5 5 18.5 1 16 1H15M22.5 8V20C21.7175 22.4962 20.8229 23.3489 18.5 24H8.5M22.5 8H15V1"
                                stroke="#999999" />
                        </svg>
                        <span>{{ scope.row.file_name }}</span>
                    </p>
                </template>
            </el-table-column>
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
                                    v-if="$page.props.auth.user.permissions.includes('Editar usuarios') && editIndex != scope.row.id"
                                    :command="'edit-' + scope.row.id">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                    Editar nombre de archivo
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </template>
            </el-table-column>
        </el-table>
    </section>

    <DialogModal :show="showUploadModal" @close="showUploadModal = false" maxWidth="lg">
        <template #title>
            <h1 class="font-bold">Subir documentos</h1>
        </template>
        <template #content>
            <el-upload drag action="#" :on-change="handleChangeFile" :on-remove="handleRemoveFile" :auto-upload="false"
                v-model:file-list="fileList" multiple>
                <i class="fa-solid fa-cloud-arrow-up text-4xl text-grayD9"></i>
                <div class="el-upload__text">
                    Arrastra y suelta aqui o <em>clic para elegir el archivo</em>
                </div>
                <template #tip>
                    <div class="el-upload__tip flex justify-between">
                        <span>Soporta formatos: PDF, JPG, JPEG</span>
                        <span>Max: 25MB</span>
                    </div>
                </template>
            </el-upload>
            <PrimaryButton @click="storeMedia" class="w-full mt-5" :disabled="form.processing || !form.media.length">
                <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                Continuar
            </PrimaryButton>
        </template>
    </DialogModal>
</template>

<script>
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        const form = useForm({
            media: [],
            media_name: null,
        });

        return {
            form,
            fileList: [],
            disableMassiveActions: true,
            showUploadModal: false,
            editIndex: null,
            // pagination
            itemsPerPage: 10,
            start: 0,
            end: 10,
        }
    },
    components: {
        PrimaryButton,
        DialogModal,
    },
    props: {
        user: Object,
    },
    methods: {
        updateMediaName() {
            this.form.put(route('users.update-media-name', this.editIndex), {
                onSuccess: () => {
                    this.$notify({
                        title: "Nombre actualizado",
                        type: "success"
                    });

                    this.editIndex = null;
                    this.form.reset();
                }
            });
        },
        storeMedia() {
            this.form.post(route('users.store-media', this.user.id), {
                onSuccess: () => {
                    this.$notify({
                        title: "Correcto",
                        type: "success"
                    });

                    this.showUploadModal = false;
                    this.form.reset();
                    this.fileList = [];
                }
            });
        },
        handleChangeFile(file, fileList) {
            this.form.media = fileList.map(item => item.raw); // Actualiza form.media con los archivos
        },
        handleRemoveFile(file) {
            // Remover de form.media
            const mediaIndex = this.form.media.indexOf(file.raw);
            if (mediaIndex !== -1) {
                this.form.media.splice(mediaIndex, 1); // Elimina el archivo de form.media
            }
            // Remover del componente
            const mediaUploadIndex = this.fileList.indexOf(file);
            if (mediaUploadIndex !== -1) {
                this.fileList.splice(mediaUploadIndex, 1); // Elimina el archivo de form.media
            }
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
            if (!this.editIndex) {
                window.open(row.original_url, '_blank');
            }
        },
        handleCommand(command) {
            const commandName = command.split('-')[0];
            const rowId = command.split('-')[1];
            const selectedMedia = this.user.media.find(m => m.id == rowId);

            if (commandName === 'show') {
                this.handleRowClick(selectedMedia);
            } else if (commandName === 'edit') {
                this.form.media_name = selectedMedia.name
                this.editIndex = rowId;
                this.$nextTick(() => {
                    this.$refs.mediaNameInput.focus();
                });
            }
        },
        formatDate(dateString) {
            return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
        },
        updateVacations() {
            this.form.put(route('users.update-vacations', this.user.id), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        type: 'success',
                    })
                }
            });
        },
        async deleteSelections() {
            try {
                const items_ids = this.$refs.multipleTableRef.value.map(item => item.id);
                const response = await axios.post(route('users.massive-delete-media', {
                    items_ids
                }));

                if (response.status === 200) {
                    this.$notify({
                        title: 'Correcto',
                        message: '',
                        type: 'success',
                    });

                    // update list of quotes
                    let deletedIndexes = [];
                    this.user.media.forEach((user, index) => {
                        if (items_ids.includes(user.id)) {
                            deletedIndexes.push(index);
                        }
                    });

                    // Ordenar los índices de forma descendente para evitar problemas de desplazamiento al eliminar elementos
                    deletedIndexes.sort((a, b) => b - a);

                    // Eliminar cotizaciones por índice
                    for (const index of deletedIndexes) {
                        this.user.media.splice(index, 1);
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
