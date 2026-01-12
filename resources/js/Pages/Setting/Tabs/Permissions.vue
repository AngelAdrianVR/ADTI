<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DialogModal from "@/Components/DialogModal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { ElNotification } from "element-plus";

// Recibimos los permisos agrupados
const props = defineProps({
    permissions: Object,
});

const showPermissionModal = ref(false);
const currentPermission = ref(null);
const editFlag = ref(false);
const form = useForm({
    name: null,
    category: null,
});

const createPermission = () => {
    currentPermission.value = null;
    editFlag.value = false;
    form.reset();
    showPermissionModal.value = true;
};

const editPermission = (permission, category) => {
    currentPermission.value = permission;
    editFlag.value = true;
    form.name = permission.name;
    form.category = category; // Usamos la categoría del grupo
    showPermissionModal.value = true;
};

const storePermission = () => {
    form.post(route('settings.role-permission.store-permission'), {
        onSuccess: () => {
            ElNotification.success('Permiso creado correctamente');
            showPermissionModal.value = false;
            form.reset();
        },
    });
};

const updatePermission = () => {
    form.put(route('settings.role-permission.update-permission', currentPermission.value.id), {
        onSuccess: () => {
            ElNotification.success('Permiso actualizado correctamente');
            showPermissionModal.value = false;
            form.reset();
        },
    });
};

const deletePermission = (permission) => {
    router.delete(route('settings.role-permission.delete-permission', permission.id), {
        onSuccess: () => {
            ElNotification.success('Permiso eliminado');
        },
    });
};
</script>

<template>
    <div class="py-4">
        <!-- Header Actions -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Catálogo de Permisos</h2>
                <p class="text-sm text-gray-500">Define las acciones atómicas que se pueden realizar en el sistema.</p>
            </div>
            <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear permisos')" @click="createPermission">
                <i class="fa-solid fa-plus mr-2"></i> Nuevo Permiso
            </PrimaryButton>
        </div>

        <!-- Grid de Permisos por Categoría -->
        <div v-if="Object.keys(permissions).length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div 
                v-for="(group, categoryName) in permissions" 
                :key="categoryName" 
                class="bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col h-full hover:shadow-md transition-shadow duration-300"
            >
                <div class="px-5 py-3 border-b border-gray-50 bg-gray-50/50 rounded-t-xl">
                    <h3 class="font-bold text-gray-700 capitalize flex items-center">
                        <i class="fa-solid fa-layer-group text-indigo-400 mr-2 text-xs"></i>
                        {{ categoryName.replace(/_/g, ' ') }}
                    </h3>
                </div>
                
                <div class="p-4 flex-1">
                    <ul class="space-y-2">
                        <li 
                            v-for="permission in group" 
                            :key="permission.id" 
                            class="group flex justify-between items-center text-sm p-2 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <span class="text-gray-600 font-medium">{{ permission.name }}</span>
                            
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                                <button 
                                    @click="editPermission(permission, categoryName)" 
                                    class="text-indigo-500 hover:bg-indigo-100 p-1.5 rounded transition-colors"
                                    title="Editar"
                                >
                                    <i class="fa-solid fa-pencil text-xs"></i>
                                </button>
                                
                                <el-popconfirm
                                    v-if="$page.props.auth.user.permissions.includes('Eliminar permisos')"
                                    confirm-button-text="Si"
                                    cancel-button-text="No"
                                    icon-color="#DC2626"
                                    title="¿Eliminar?"
                                    @confirm="deletePermission(permission)"
                                    width="200"
                                >
                                    <template #reference>
                                        <button class="text-red-500 hover:bg-red-100 p-1.5 rounded transition-colors" title="Eliminar">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </template>
                                </el-popconfirm>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div v-else class="text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
            <p class="text-gray-400">No hay permisos registrados.</p>
        </div>

        <!-- Modal Crear/Editar -->
        <DialogModal :show="showPermissionModal" @close="showPermissionModal = false" maxWidth="md">
            <template #title>
                <span class="font-bold text-lg text-gray-800">{{ editFlag ? 'Editar Permiso' : 'Crear Permiso' }}</span>
            </template>

            <template #content>
                <div class="space-y-4">
                    <!-- Categoría -->
                    <div>
                        <InputLabel value="Categoría (Grupo) *" />
                        <TextInput v-model="form.category" class="w-full mt-1" placeholder="Ej. Ventas, Usuarios, etc." />
                        <InputError :message="form.errors.category" class="mt-1" />
                        <p class="text-xs text-gray-400 mt-1">Se usará para agrupar visualmente los permisos.</p>
                    </div>

                    <!-- Nombre -->
                    <div>
                        <InputLabel value="Nombre del Permiso *" />
                        <TextInput v-model="form.name" class="w-full mt-1" placeholder="Ej. Crear usuarios" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="flex gap-2">
                    <SecondaryButton @click="showPermissionModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton @click="editFlag ? updatePermission() : storePermission()" :disabled="form.processing">
                        {{ editFlag ? 'Actualizar' : 'Guardar' }}
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>
    </div>
</template>