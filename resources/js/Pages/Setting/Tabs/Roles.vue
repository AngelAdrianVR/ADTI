<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DialogModal from "@/Components/DialogModal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { ElNotification } from "element-plus";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    roles: Array,
});

// Obtener permisos globales compartidos desde el controlador padre (SettingIndex -> Tabs)
// O hacer una petición si no están disponibles. 
// Asumiremos que se pasan o se pueden inyectar.
// Para simplificar y corregir el error, usaremos los permisos que ya vienen en `roles` para visualización
// Y para el modal, necesitamos la lista completa de permisos disponibles.
// **IMPORTANTE**: Como `Roles.vue` ahora es hijo de `SettingIndex` en la sección "permissions",
// necesitamos acceder a la lista completa de permisos para poder asignarlos.
// Vamos a usar `usePage` para acceder a las props globales si están ahí, o definiremos una prop extra si el padre la pasa.
// El padre (SettingIndex) recibe `permissions` y `roles`. Deberíamos pasar `permissions` a este componente.

// NOTA: Para que esto funcione, asegúrate de pasar `:permissions="permissions"` desde SettingIndex.vue a <Roles />
const permissions = defineModel('permissions', { default: {} }); 

const showRoleModal = ref(false);
const currentRole = ref(null);
const editFlag = ref(false);
const form = useForm({
    name: null,
    permissions: [] // Array de IDs de permisos
});

const formatDate = (dateString) => {
    return format(new Date(dateString), 'dd MMM, yyyy', { locale: es });
};

const createRole = () => {
    currentRole.value = null;
    editFlag.value = false;
    form.reset();
    showRoleModal.value = true;
};

const editRole = (role) => {
    currentRole.value = role;
    editFlag.value = true;
    form.name = role.name;
    // Extraer IDs de permisos actuales
    form.permissions = role.permissions.map(p => p.id);
    showRoleModal.value = true;
};

const storeRole = () => {
    form.post(route('settings.role-permission.store-role'), {
        onSuccess: () => {
            ElNotification.success('Rol creado correctamente');
            showRoleModal.value = false;
            form.reset();
        },
    });
};

const updateRole = () => {
    form.put(route('settings.role-permission.update-role', currentRole.value.id), {
        onSuccess: () => {
            ElNotification.success('Rol actualizado correctamente');
            showRoleModal.value = false;
            form.reset();
        },
    });
};

const deleteRole = (role) => {
    router.delete(route('settings.role-permission.delete-role', role.id), {
        onSuccess: () => {
            ElNotification.success('Rol eliminado');
        },
    });
};

// Helper para convertir el objeto de permisos agrupados en una lista plana para el checkbox si fuera necesario,
// pero aquí lo manejaremos agrupado visualmente.
</script>

<template>
    <div class="py-4">
        <!-- Header Actions -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Gestión de Roles</h2>
                <p class="text-sm text-gray-500">Administra los roles de usuario y sus niveles de acceso.</p>
            </div>
            <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear roles')" @click="createRole">
                <i class="fa-solid fa-plus mr-2"></i> Nuevo Rol
            </PrimaryButton>
        </div>

        <!-- Tabla de Roles -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 font-bold">Nombre del Rol</th>
                            <th class="px-6 py-3 font-bold text-center">Permisos Asignados</th>
                            <th class="px-6 py-3 font-bold text-center">Fecha Creación</th>
                            <th class="px-6 py-3 font-bold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mr-3 font-bold uppercase text-xs">
                                        {{ role.name.substring(0, 2) }}
                                    </div>
                                    {{ role.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-gray-100 text-gray-600 py-1 px-3 rounded-full text-xs font-semibold">
                                    {{ role.permissions.length }} permisos
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                {{ formatDate(role.created_at) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <button 
                                        v-if="$page.props.auth.user.permissions.includes('Editar roles')"
                                        @click="editRole(role)" 
                                        class="text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 p-2 rounded-full transition-colors"
                                        title="Editar"
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    
                                    <el-popconfirm
                                        v-if="$page.props.auth.user.permissions.includes('Eliminar roles')"
                                        confirm-button-text="Si"
                                        cancel-button-text="No"
                                        icon-color="#DC2626"
                                        title="¿Eliminar este rol?"
                                        @confirm="deleteRole(role)"
                                    >
                                        <template #reference>
                                            <button class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-full transition-colors" title="Eliminar">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </template>
                                    </el-popconfirm>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="roles.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-gray-400">
                                No hay roles registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Crear/Editar Rol -->
        <DialogModal :show="showRoleModal" @close="showRoleModal = false" maxWidth="2xl">
            <template #title>
                <span class="font-bold text-lg text-gray-800">{{ editFlag ? 'Editar Rol' : 'Crear Nuevo Rol' }}</span>
            </template>

            <template #content>
                <div class="space-y-6">
                    <!-- Nombre -->
                    <div>
                        <InputLabel value="Nombre del Rol *" />
                        <TextInput v-model="form.name" class="w-full mt-1" placeholder="Ej. Administrador de Ventas" autofocus />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <!-- Permisos (Selector Agrupado) -->
                    <div>
                        <InputLabel value="Asignar Permisos" class="mb-3" />
                        
                        <!-- CORRECCIÓN ERROR: v-if="permissions" asegura que exista el objeto antes de iterar -->
                        <div v-if="permissions && Object.keys(permissions).length > 0" class="border rounded-lg border-gray-200 max-h-96 overflow-y-auto bg-gray-50 p-4 space-y-5">
                            <div v-for="(group, groupName) in permissions" :key="groupName" class="bg-white p-3 rounded-md shadow-sm border border-gray-100">
                                <h3 class="font-bold text-sm text-gray-700 mb-3 border-b border-gray-100 pb-1 capitalize">
                                    {{ groupName.replace(/_/g, ' ') }}
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div v-for="permission in group" :key="permission.id" class="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            :value="permission.id" 
                                            v-model="form.permissions"
                                            :id="'perm-' + permission.id"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer"
                                        >
                                        <label :for="'perm-' + permission.id" class="ml-2 text-sm text-gray-600 cursor-pointer select-none">
                                            {{ permission.name }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-gray-500 italic p-4 text-center border rounded bg-gray-50">
                            No hay permisos disponibles para asignar.
                        </div>
                        <InputError :message="form.errors.permissions" class="mt-1" />
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="flex gap-2">
                    <SecondaryButton @click="showRoleModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton @click="editFlag ? updateRole() : storeRole()" :disabled="form.processing">
                        {{ editFlag ? 'Actualizar' : 'Guardar' }}
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>
    </div>
</template>