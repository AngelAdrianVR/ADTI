<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ElNotification } from "element-plus";
import axios from 'axios';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    users: Array,
});

// State
const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const selectedItems = ref([]);
const showInactivateModal = ref(false);
const userToInactivate = ref(null);

const inactivateForm = useForm({
    inactivate_date: new Date().toISOString().split('T')[0], // Fecha de hoy por defecto
    inactivate_reason: '',
});

// Computed
const filteredUsers = computed(() => {
    if (!search.value) return props.users;
    const lowerSearch = search.value.toLowerCase();
    
    return props.users.filter(user => 
        user.name?.toLowerCase().includes(lowerSearch) ||
        user.email?.toLowerCase().includes(lowerSearch) ||
        user.org_props?.department?.toLowerCase().includes(lowerSearch) ||
        user.org_props?.position?.toLowerCase().includes(lowerSearch)
    );
});

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredUsers.value.slice(start, end);
});

// Methods
const handleSelectionChange = (val) => {
    selectedItems.value = val;
};

const handleRowClick = (row) => {
    router.visit(route('users.show', row.id));
};

const handlePageChange = (val) => {
    currentPage.value = val;
};

const deleteSelections = async () => {
    try {
        const items_ids = selectedItems.value.map(item => item.id);
        const response = await axios.post(route('users.massive-delete'), { items_ids });

        if (response.status === 200) {
            ElNotification.success({
                title: 'Operación exitosa',
                message: 'Usuarios eliminados correctamente',
            });
            router.reload();
        }
    } catch (err) {
        ElNotification.error({
            title: 'Error',
            message: 'No se pudo eliminar la selección',
        });
        console.error(err);
    }
};

const editUser = (id) => {
    router.visit(route('users.edit', id));
};

const toggleHomeOffice = (user) => {
    router.put(route('users.toggle-home-office', user.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            ElNotification.success({
                title: 'Actualizado',
                message: `Acceso remoto ${user.home_office ? 'habilitado' : 'deshabilitado'} para ${user.name}`
            });
        },
        onError: () => {
            user.home_office = !user.home_office; // Revertir visualmente si falla
            ElNotification.error('No se pudo actualizar el estatus');
        }
    });
};

const openInactivateModal = (user) => {
    userToInactivate.value = user;
    inactivateForm.reset();
    showInactivateModal.value = true;
};

const submitInactivate = () => {
    inactivateForm.put(route('users.inactivate', userToInactivate.value.id), {
        onSuccess: () => {
            ElNotification.success({
                title: 'Usuario dado de baja',
                message: 'El usuario ha sido movido a la lista de inactivos.',
            });
            showInactivateModal.value = false;
        },
        onError: () => {
            ElNotification.error('Revisa los campos del formulario');
        }
    });
};
</script>

<template>
    <div class="px-2">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
            <div class="relative w-full sm:w-72">
                <input 
                    v-model="search" 
                    type="text" 
                    placeholder="Buscar por nombre, correo o puesto..." 
                    class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2] text-sm shadow-sm"
                >
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-sm"></i>
            </div>

            <div class="flex items-center gap-2" v-if="selectedItems.length > 0">
                <span class="text-xs text-gray-500 mr-2">{{ selectedItems.length }} seleccionados</span>
                <el-popconfirm 
                    v-if="$page.props.auth.user.permissions?.includes('Eliminar usuarios')"
                    confirm-button-text="Sí, eliminar" 
                    cancel-button-text="No" 
                    icon-color="#DC2626" 
                    title="¿Eliminar usuarios seleccionados?"
                    @confirm="deleteSelections"
                >
                    <template #reference>
                        <button class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-md text-xs font-medium transition-colors border border-red-200">
                            <i class="fa-solid fa-trash mr-1"></i> Eliminar
                        </button>
                    </template>
                </el-popconfirm>
            </div>
        </div>

        <!-- Tabla -->
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <el-table 
                :data="paginatedUsers" 
                @selection-change="handleSelectionChange"
                @row-click="handleRowClick"
                style="width: 100%"
                class="cursor-pointer"
                :row-class-name="'hover:bg-gray-50 transition-colors'"
            >
                <el-table-column v-if="$page.props.auth.user.permissions?.includes('Eliminar usuarios')" type="selection" width="40" />
                
                <el-table-column label="Usuario" min-width="200">
                    <template #default="scope">
                        <div class="flex items-center gap-3">
                            <img :src="scope.row.profile_photo_url" class="h-9 w-9 rounded-full object-cover border border-gray-200" alt="">
                            <div>
                                <p class="font-bold text-gray-800 text-sm leading-tight">{{ scope.row.name }}</p>
                                <p class="text-xs text-gray-500">{{ scope.row.email }}</p>
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Puesto / Departamento" min-width="180">
                    <template #default="scope">
                        <div>
                            <p class="text-sm text-gray-700 font-medium">{{ scope.row.org_props?.position || 'N/A' }}</p>
                            <p class="text-xs text-gray-500">{{ scope.row.org_props?.department || 'General' }}</p>
                        </div>
                    </template>
                </el-table-column>

                <!-- Columna Home Office (Nueva) -->
                <el-table-column label="Acceso Remoto" width="130" align="center">
                    <template #default="scope">
                        <div @click.stop>
                            <el-switch
                                v-model="scope.row.home_office"
                                @change="toggleHomeOffice(scope.row)"
                                inline-prompt
                                active-text="Sí"
                                inactive-text="No"
                                style="--el-switch-on-color: #1676A2;"
                            />
                        </div>
                    </template>
                </el-table-column>

                <el-table-column align="right" width="120">
                    <template #default="scope">
                        <div class="flex items-center justify-end gap-1">
                            <!-- Botón Editar -->
                            <button 
                                v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                                @click.stop="editUser(scope.row.id)" 
                                class="p-2 text-gray-400 hover:text-[#1676A2] hover:bg-blue-50 rounded-full transition-colors"
                                title="Editar"
                            >
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>

                            <!-- Botón Dar de Baja (Nuevo) -->
                            <button 
                                v-if="$page.props.auth.user.permissions.includes('Inactivar usuarios')"
                                @click.stop="openInactivateModal(scope.row)" 
                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-full transition-colors"
                                title="Dar de baja"
                            >
                                <i class="fa-solid fa-user-minus"></i>
                            </button>
                        </div>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-end mt-4">
            <el-pagination 
                layout="prev, pager, next" 
                :total="filteredUsers.length" 
                :page-size="itemsPerPage"
                @current-change="handlePageChange"
                background
            />
        </div>

        <!-- Modal Dar de Baja -->
        <DialogModal :show="showInactivateModal" @close="showInactivateModal = false" maxWidth="md">
            <template #title>
                <span class="font-bold text-gray-800">Dar de Baja Usuario</span>
            </template>
            <template #content>
                <div v-if="userToInactivate" class="space-y-4">
                    <p class="text-sm text-gray-600">
                        Estás a punto de desactivar a <span class="font-bold text-gray-800">{{ userToInactivate.name }}</span>. 
                        Este usuario perderá acceso al sistema inmediatamente.
                    </p>

                    <div>
                        <InputLabel value="Fecha de Baja *" />
                        <TextInput 
                            v-model="inactivateForm.inactivate_date" 
                            type="date" 
                            class="w-full mt-1" 
                        />
                        <InputError :message="inactivateForm.errors.inactivate_date" />
                    </div>

                    <div>
                        <InputLabel value="Motivo de Baja *" />
                        <textarea 
                            v-model="inactivateForm.inactivate_reason" 
                            rows="3"
                            class="w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-[#1676A2] focus:ring-[#1676A2] text-sm"
                            placeholder="Describe el motivo..."
                        ></textarea>
                        <InputError :message="inactivateForm.errors.inactivate_reason" />
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex gap-2">
                    <SecondaryButton @click="showInactivateModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton 
                        @click="submitInactivate" 
                        :disabled="inactivateForm.processing"
                        class="!bg-red-600 hover:!bg-red-700 border-transparent"
                    >
                        Confirmar Baja
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>

    </div>
</template>