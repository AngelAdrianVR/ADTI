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
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    roles: Array,
    permissions: Object, // Agrupados por categoría
});

// --- Estado General ---
const activeTab = ref('roles'); // 'roles' | 'permissions'

// --- Estado Roles ---
const showRoleModal = ref(false);
const currentRole = ref(null);
const editRoleFlag = ref(false);
const roleForm = useForm({
    name: null,
    permissions: [] // IDs
});

// --- Estado Permisos ---
const showPermissionModal = ref(false);
const currentPermission = ref(null);
const editPermissionFlag = ref(false);
const permissionForm = useForm({
    name: null,
    category: null,
});

// --- Helpers ---
const formatDate = (dateString) => {
    return format(new Date(dateString), 'dd MMM, yyyy', { locale: es });
};

// ==========================
// LÓGICA DE ROLES
// ==========================
const createRole = () => {
    currentRole.value = null;
    editRoleFlag.value = false;
    roleForm.reset();
    showRoleModal.value = true;
};

const editRole = (role) => {
    currentRole.value = role;
    editRoleFlag.value = true;
    roleForm.name = role.name;
    roleForm.permissions = role.permissions.map(p => p.id);
    showRoleModal.value = true;
};

const storeRole = () => {
    roleForm.post(route('settings.role-permission.store-role'), {
        onSuccess: () => {
            ElNotification.success('Rol creado correctamente');
            showRoleModal.value = false;
            roleForm.reset();
        },
    });
};

const updateRole = () => {
    roleForm.put(route('settings.role-permission.update-role', currentRole.value.id), {
        onSuccess: () => {
            ElNotification.success('Rol actualizado correctamente');
            showRoleModal.value = false;
            roleForm.reset();
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

// ==========================
// LÓGICA DE PERMISOS
// ==========================
const createPermission = () => {
    currentPermission.value = null;
    editPermissionFlag.value = false;
    permissionForm.reset();
    showPermissionModal.value = true;
};

const editPermission = (permission, category) => {
    currentPermission.value = permission;
    editPermissionFlag.value = true;
    permissionForm.name = permission.name;
    permissionForm.category = category;
    showPermissionModal.value = true;
};

const storePermission = () => {
    permissionForm.post(route('settings.role-permission.store-permission'), {
        onSuccess: () => {
            ElNotification.success('Permiso creado correctamente');
            showPermissionModal.value = false;
            permissionForm.reset();
        },
    });
};

const updatePermission = () => {
    permissionForm.put(route('settings.role-permission.update-permission', currentPermission.value.id), {
        onSuccess: () => {
            ElNotification.success('Permiso actualizado correctamente');
            showPermissionModal.value = false;
            permissionForm.reset();
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        
        <!-- Pestañas Internas -->
        <div class="border-b border-gray-100">
            <el-tabs v-model="activeTab" class="px-6 pt-2 custom-tabs">
                <el-tab-pane name="roles">
                    <template #label>
                        <span class="flex items-center gap-2 font-medium">
                            <i class="fa-solid fa-user-shield"></i> Roles y Accesos
                        </span>
                    </template>
                </el-tab-pane>
                <el-tab-pane name="permissions">
                    <template #label>
                        <span class="flex items-center gap-2 font-medium">
                            <i class="fa-solid fa-key"></i> Catálogo de Permisos
                        </span>
                    </template>
                </el-tab-pane>
            </el-tabs>
        </div>

        <div class="p-6">
            
            <!-- VISTA ROLES -->
            <div v-if="activeTab === 'roles'" class="animate-fade-in">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Roles del Sistema</h2>
                        <p class="text-sm text-gray-500">Administra los perfiles y asigna permisos específicos.</p>
                    </div>
                    <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear roles')" @click="createRole">
                        <i class="fa-solid fa-plus mr-2"></i> Nuevo Rol
                    </PrimaryButton>
                </div>

                <!-- Tabla Roles -->
                <div class="overflow-x-auto border rounded-lg border-gray-100">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50/50 border-b">
                            <tr>
                                <th class="px-6 py-3 font-bold">Rol</th>
                                <th class="px-6 py-3 font-bold text-center">Permisos</th>
                                <th class="px-6 py-3 font-bold text-center">Creación</th>
                                <th class="px-6 py-3 font-bold text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs uppercase border border-indigo-100">
                                            {{ role.name.substring(0, 2) }}
                                        </div>
                                        {{ role.name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ role.permissions.length }} asignados
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    {{ formatDate(role.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button v-if="$page.props.auth.user.permissions.includes('Editar roles')"
                                            @click="editRole(role)" class="text-indigo-600 hover:bg-indigo-50 p-2 rounded-lg transition-colors" title="Editar Permisos">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar roles')"
                                            title="¿Eliminar este rol permanentemente?" confirm-button-text="Sí" cancel-button-text="No"
                                            icon-color="#DC2626" @confirm="deleteRole(role)">
                                            <template #reference>
                                                <button class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Eliminar Rol">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
                                            </template>
                                        </el-popconfirm>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="roles.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-gray-400 italic">
                                    No hay roles definidos aún.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VISTA PERMISOS -->
            <div v-if="activeTab === 'permissions'" class="animate-fade-in">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Listado de Permisos</h2>
                        <p class="text-sm text-gray-500">Define las acciones atómicas disponibles en el sistema.</p>
                    </div>
                    <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Crear permisos')" @click="createPermission">
                        <i class="fa-solid fa-plus mr-2"></i> Crear Permiso
                    </PrimaryButton>
                </div>

                <div v-if="permissions && Object.keys(permissions).length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div v-for="(group, categoryName) in permissions" :key="categoryName" class="border border-gray-200 rounded-lg overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="font-bold text-gray-700 capitalize text-sm">
                                {{ categoryName.replace(/_/g, ' ') }}
                            </h3>
                            <span class="text-xs bg-white border border-gray-200 px-2 py-0.5 rounded text-gray-500">
                                {{ group.length }}
                            </span>
                        </div>
                        <ul class="divide-y divide-gray-100 flex-1">
                            <li v-for="permission in group" :key="permission.id" class="px-4 py-2 text-sm text-gray-600 flex justify-between items-center group hover:bg-gray-50">
                                <span>{{ permission.name }}</span>
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                                    <button @click="editPermission(permission, categoryName)" class="text-blue-500 hover:text-blue-700 p-1">
                                        <i class="fa-solid fa-pencil text-xs"></i>
                                    </button>
                                    <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar permisos')"
                                        title="¿Borrar?" confirm-button-text="Sí" cancel-button-text="No"
                                        @confirm="deletePermission(permission)" width="160">
                                        <template #reference>
                                            <button class="text-red-500 hover:text-red-700 p-1">
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                        </template>
                                    </el-popconfirm>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                <div v-else class="text-center py-12 border-2 border-dashed border-gray-200 rounded-xl">
                    <p class="text-gray-400">No hay permisos registrados en el sistema.</p>
                </div>
            </div>

        </div>

        <!-- ================= MODALES ================= -->

        <!-- Modal Roles -->
        <DialogModal :show="showRoleModal" @close="showRoleModal = false" maxWidth="2xl">
            <template #title>
                <span class="font-bold text-gray-800">{{ editRoleFlag ? 'Editar Rol' : 'Nuevo Rol' }}</span>
            </template>
            <template #content>
                <div class="space-y-5">
                    <div>
                        <InputLabel value="Nombre del Rol" />
                        <TextInput v-model="roleForm.name" class="w-full mt-1" placeholder="Ej. Gerente de Ventas" autofocus />
                        <InputError :message="roleForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Asignación de Permisos" class="mb-2" />
                        <div v-if="permissions && Object.keys(permissions).length > 0" class="border border-gray-200 rounded-lg max-h-[400px] overflow-y-auto bg-gray-50 p-4 space-y-4">
                            <div v-for="(group, categoryName) in permissions" :key="categoryName" class="bg-white p-3 rounded border border-gray-100 shadow-sm">
                                <h4 class="font-bold text-xs text-gray-500 uppercase mb-2 border-b border-gray-50 pb-1">
                                    {{ categoryName.replace(/_/g, ' ') }}
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <label v-for="perm in group" :key="perm.id" class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-1 rounded">
                                        <input type="checkbox" :value="perm.id" v-model="roleForm.permissions" 
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer">
                                        <span class="text-sm text-gray-700 select-none">{{ perm.name }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500 italic p-4 text-center border rounded">
                            No hay permisos disponibles. Crea permisos primero en la pestaña "Catálogo de Permisos".
                        </p>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showRoleModal = false" class="mr-2">Cancelar</SecondaryButton>
                <PrimaryButton @click="editRoleFlag ? updateRole() : storeRole()" :disabled="roleForm.processing">
                    {{ editRoleFlag ? 'Guardar Cambios' : 'Crear Rol' }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Modal Permisos -->
        <DialogModal :show="showPermissionModal" @close="showPermissionModal = false" maxWidth="md">
            <template #title>
                <span class="font-bold text-gray-800">{{ editPermissionFlag ? 'Editar Permiso' : 'Nuevo Permiso' }}</span>
            </template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Categoría (Agrupador)" />
                        <TextInput v-model="permissionForm.category" class="w-full mt-1" placeholder="Ej. Ventas" />
                        <InputError :message="permissionForm.errors.category" />
                    </div>
                    <div>
                        <InputLabel value="Nombre del Permiso" />
                        <TextInput v-model="permissionForm.name" class="w-full mt-1" placeholder="Ej. Crear cotizaciones" />
                        <InputError :message="permissionForm.errors.name" />
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showPermissionModal = false" class="mr-2">Cancelar</SecondaryButton>
                <PrimaryButton @click="editPermissionFlag ? updatePermission() : storePermission()" :disabled="permissionForm.processing">
                    {{ editPermissionFlag ? 'Guardar' : 'Crear' }}
                </PrimaryButton>
            </template>
        </DialogModal>

    </div>
</template>

<style scoped>
.custom-tabs :deep(.el-tabs__item) {
    font-size: 15px;
    color: #6b7280;
}
.custom-tabs :deep(.el-tabs__item.is-active) {
    color: #4f46e5;
    font-weight: 600;
}
.custom-tabs :deep(.el-tabs__nav-wrap::after) {
    height: 2px;
    background-color: transparent;
}
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>