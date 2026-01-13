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
    permissions: Object, // Objeto agrupado: { 'categoria': [permisos...] }
});

// --- Configuración de colores (Clases personalizadas en style) ---
// Primary: #1676A2
// Secondary: #6D6E72

// Estado Tab
const activeTab = ref('roles');

// Estado Roles
const showRoleModal = ref(false);
const editRoleFlag = ref(false);
const currentRoleId = ref(null);
const roleForm = useForm({
    name: null,
    permissions: [] // IDs
});

// Estado Permisos
const showPermissionModal = ref(false);
const editPermissionFlag = ref(false);
const currentPermissionId = ref(null);
const permissionForm = useForm({
    name: null,
    category: null,
});

// Helpers
const formatDate = (dateString) => {
    return format(new Date(dateString), 'dd MMM, yyyy', { locale: es });
};

// ==========================
// MÉTODOS DE ROLES
// ==========================
const openCreateRole = () => {
    editRoleFlag.value = false;
    currentRoleId.value = null;
    roleForm.reset();
    showRoleModal.value = true;
};

const openEditRole = (role) => {
    editRoleFlag.value = true;
    currentRoleId.value = role.id;
    roleForm.name = role.name;
    // Extraer solo los IDs de los permisos asignados
    roleForm.permissions = role.permissions?.map(p => p.id) || [];
    showRoleModal.value = true;
};

const storeRole = () => {
    roleForm.post(route('settings.role-permission.store-role'), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Rol creado correctamente' });
            showRoleModal.value = false;
            roleForm.reset();
        },
    });
};

const updateRole = () => {
    roleForm.put(route('settings.role-permission.update-role', currentRoleId.value), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Rol actualizado correctamente' });
            showRoleModal.value = false;
            roleForm.reset();
        },
    });
};

const deleteRole = (role) => {
    router.delete(route('settings.role-permission.delete-role', role.id), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Rol eliminado' });
        },
    });
};

// ==========================
// MÉTODOS DE PERMISOS
// ==========================
const openCreatePermission = () => {
    editPermissionFlag.value = false;
    currentPermissionId.value = null;
    permissionForm.reset();
    showPermissionModal.value = true;
};

const openEditPermission = (permission) => {
    editPermissionFlag.value = true;
    currentPermissionId.value = permission.id;
    permissionForm.name = permission.name;
    permissionForm.category = permission.category;
    showPermissionModal.value = true;
};

const storePermission = () => {
    permissionForm.post(route('settings.role-permission.store-permission'), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Permiso creado correctamente' });
            showPermissionModal.value = false;
            permissionForm.reset();
        },
    });
};

const updatePermission = () => {
    permissionForm.put(route('settings.role-permission.update-permission', currentPermissionId.value), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Permiso actualizado correctamente' });
            showPermissionModal.value = false;
            permissionForm.reset();
        },
    });
};

const deletePermission = (permission) => {
    router.delete(route('settings.role-permission.delete-permission', permission.id), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Permiso eliminado' });
        },
    });
};
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        
        <!-- Navegación de Pestañas -->
        <div class="border-b border-gray-100">
            <div class="flex">
                <button 
                    @click="activeTab = 'roles'"
                    class="px-6 py-4 text-sm font-medium transition-colors border-b-2 flex items-center gap-2 outline-none focus:outline-none"
                    :class="activeTab === 'roles' ? 'border-[#1676A2] text-[#1676A2]' : 'border-transparent text-[#6D6E72] hover:text-[#1676A2]'"
                >
                    <i class="fa-solid fa-user-shield"></i>
                    Roles y Accesos
                </button>
                <button 
                    @click="activeTab = 'permissions'"
                    class="px-6 py-4 text-sm font-medium transition-colors border-b-2 flex items-center gap-2 outline-none focus:outline-none"
                    :class="activeTab === 'permissions' ? 'border-[#1676A2] text-[#1676A2]' : 'border-transparent text-[#6D6E72] hover:text-[#1676A2]'"
                >
                    <i class="fa-solid fa-key"></i>
                    Catálogo de Permisos
                </button>
            </div>
        </div>

        <div class="p-6 min-h-[400px]">
            
            <!-- ================= VISTA ROLES ================= -->
            <div v-if="activeTab === 'roles'" class="animate-fade-in">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-[#1676A2]">Gestión de Roles</h2>
                        <p class="text-xs text-[#6D6E72]">Administra los perfiles de usuario y sus niveles de acceso al sistema.</p>
                    </div>
                    <button 
                        v-if="$page.props.auth.user.permissions.includes('Crear roles')"
                        @click="openCreateRole"
                        class="bg-[#1676A2] hover:bg-[#125d80] text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2"
                    >
                        <i class="fa-solid fa-plus"></i> Nuevo Rol
                    </button>
                </div>

                <!-- Tabla de Roles -->
                <div class="overflow-x-auto border border-gray-100 rounded-lg">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-[#6D6E72] uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-3">Rol</th>
                                <th class="px-6 py-3 text-center">Permisos Asignados</th>
                                <th class="px-6 py-3 text-center">Fecha Creación</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-800">
                                    <div class="flex items-center gap-3">
                                        {{ role.name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-[#6D6E72]">
                                        {{ role.permissions?.length || 0 }} permisos
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-[#6D6E72]">
                                    {{ formatDate(role.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            v-if="$page.props.auth.user.permissions.includes('Editar roles')"
                                            @click="openEditRole(role)" 
                                            class="text-[#1676A2] hover:bg-blue-50 p-2 rounded-lg transition-colors"
                                            title="Editar Rol"
                                        >
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <el-popconfirm
                                            v-if="$page.props.auth.user.permissions.includes('Eliminar roles')"
                                            title="¿Eliminar este rol permanentemente?"
                                            confirm-button-text="Sí, eliminar"
                                            cancel-button-text="No"
                                            icon-color="#DC2626"
                                            @confirm="deleteRole(role)"
                                            width="220"
                                        >
                                            <template #reference>
                                                <button class="text-[#6D6E72] hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Eliminar Rol">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
                                            </template>
                                        </el-popconfirm>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!roles.length">
                                <td colspan="4" class="px-6 py-12 text-center text-[#6D6E72] italic bg-gray-50/50">
                                    No hay roles definidos aún. Comienza creando uno.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================= VISTA PERMISOS ================= -->
            <div v-if="activeTab === 'permissions'" class="animate-fade-in">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-[#1676A2]">Catálogo de Permisos</h2>
                        <p class="text-xs text-[#6D6E72]">Define las acciones atómicas disponibles en el sistema agrupadas por categoría.</p>
                    </div>
                    <button 
                        v-if="$page.props.auth.user.permissions.includes('Crear permisos')"
                        @click="openCreatePermission"
                        class="bg-[#1676A2] hover:bg-[#125d80] text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2"
                    >
                        <i class="fa-solid fa-plus"></i> Nuevo Permiso
                    </button>
                </div>

                <!-- Grid de Permisos -->
                <div v-if="permissions && Object.keys(permissions).length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="(group, categoryName) in permissions" :key="categoryName" class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-all duration-300 bg-white group/card">
                        
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-bold text-gray-700 text-sm capitalize flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#1676A2]"></span>
                                {{ categoryName.replace(/_/g, ' ') }}
                            </h3>
                            <span class="text-xs font-mono bg-white border border-gray-200 px-2 py-0.5 rounded text-[#6D6E72]">
                                {{ group.length }}
                            </span>
                        </div>
                        
                        <ul class="divide-y divide-gray-50 max-h-64 overflow-y-auto custom-scrollbar">
                            <li v-for="permission in group" :key="permission.id" class="px-4 py-2.5 text-sm text-gray-600 flex justify-between items-center hover:bg-blue-50/50 transition-colors group/item">
                                <span class="truncate pr-2" :title="permission.name">{{ permission.name }}</span>
                                
                                <div class="opacity-0 group-hover/item:opacity-100 transition-opacity flex items-center gap-1">
                                    <button 
                                        @click="openEditPermission(permission)" 
                                        class="text-[#1676A2] hover:bg-white p-1 rounded shadow-sm"
                                        title="Editar"
                                    >
                                        <i class="fa-solid fa-pencil text-xs"></i>
                                    </button>
                                    <el-popconfirm
                                        v-if="$page.props.auth.user.permissions.includes('Eliminar permisos')"
                                        title="¿Eliminar?"
                                        confirm-button-text="Sí"
                                        cancel-button-text="No"
                                        icon-color="#DC2626"
                                        @confirm="deletePermission(permission)"
                                        width="150"
                                    >
                                        <template #reference>
                                            <button class="text-[#6D6E72] hover:text-red-500 hover:bg-white p-1 rounded shadow-sm" title="Borrar">
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                        </template>
                                    </el-popconfirm>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div v-else class="text-center py-16 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                    <i class="fa-solid fa-layer-group text-4xl text-gray-300 mb-3"></i>
                    <p class="text-[#6D6E72] font-medium">No hay permisos registrados.</p>
                    <p class="text-xs text-gray-400">Crea el primer permiso para comenzar.</p>
                </div>

            </div>

        </div>

        <!-- ================= MODAL ROLES ================= -->
        <DialogModal :show="showRoleModal" @close="showRoleModal = false" maxWidth="2xl">
            <template #title>
                <div class="flex items-center gap-2 text-[#1676A2]">
                    <i class="fa-solid fa-user-shield"></i>
                    <span class="font-bold">{{ editRoleFlag ? 'Editar Rol' : 'Crear Nuevo Rol' }}</span>
                </div>
            </template>
            <template #content>
                <div class="space-y-5">
                    <div>
                        <InputLabel value="Nombre del Rol *" />
                        <TextInput v-model="roleForm.name" class="w-full mt-1 border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2]" placeholder="Ej. Administrador de Ventas" autofocus />
                        <InputError :message="roleForm.errors.name" />
                    </div>
                    
                    <div>
                        <InputLabel value="Asignación de Permisos" class="mb-2" />
                        
                        <!-- Lista de permisos con checkbox -->
                        <div v-if="permissions && Object.keys(permissions).length > 0" 
                             class="border border-gray-200 rounded-lg max-h-[400px] overflow-y-auto bg-gray-50 p-4 space-y-4 custom-scrollbar">
                            
                            <div v-for="(group, categoryName) in permissions" :key="categoryName" class="bg-white p-3 rounded-lg border border-gray-100 shadow-sm">
                                <h4 class="font-bold text-xs text-[#6D6E72] uppercase mb-3 border-b border-gray-50 pb-1 flex items-center gap-2">
                                    <i class="fa-solid fa-folder text-gray-300"></i>
                                    {{ categoryName.replace(/_/g, ' ') }}
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <label v-for="perm in group" :key="perm.id" 
                                           class="flex items-center space-x-2 cursor-pointer hover:bg-blue-50 p-1.5 rounded transition-colors group">
                                        <input type="checkbox" :value="perm.id" v-model="roleForm.permissions" 
                                            class="rounded border-gray-300 text-[#1676A2] shadow-sm focus:ring-[#1676A2] cursor-pointer">
                                        <span class="text-sm text-gray-600 group-hover:text-gray-800 select-none">{{ perm.name }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-sm text-[#6D6E72] italic p-6 text-center border border-dashed rounded bg-gray-50">
                            No hay permisos disponibles para asignar.
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showRoleModal = false" class="mr-2 border-gray-300 text-[#6D6E72] hover:text-gray-800">Cancelar</SecondaryButton>
                <PrimaryButton 
                    @click="editRoleFlag ? updateRole() : storeRole()" 
                    :disabled="roleForm.processing"
                    class="bg-[#1676A2] hover:bg-[#125d80] border-transparent"
                >
                    {{ editRoleFlag ? 'Guardar Cambios' : 'Crear Rol' }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- ================= MODAL PERMISOS ================= -->
        <DialogModal :show="showPermissionModal" @close="showPermissionModal = false" maxWidth="md">
            <template #title>
                <div class="flex items-center gap-2 text-[#1676A2]">
                    <i class="fa-solid fa-key"></i>
                    <span class="font-bold">{{ editPermissionFlag ? 'Editar Permiso' : 'Nuevo Permiso' }}</span>
                </div>
            </template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Categoría *" />
                        <TextInput v-model="permissionForm.category" class="w-full mt-1 border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2]" placeholder="Ej. Ventas, Usuarios..." />
                        <InputError :message="permissionForm.errors.category" />
                        <p class="text-xs text-[#6D6E72] mt-1">Agrupador para organizar los permisos visualmente.</p>
                    </div>
                    <div>
                        <InputLabel value="Nombre del Permiso *" />
                        <TextInput v-model="permissionForm.name" class="w-full mt-1 border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2]" placeholder="Ej. Crear cotizaciones" />
                        <InputError :message="permissionForm.errors.name" />
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showPermissionModal = false" class="mr-2 border-gray-300 text-[#6D6E72] hover:text-gray-800">Cancelar</SecondaryButton>
                <PrimaryButton 
                    @click="editPermissionFlag ? updatePermission() : storePermission()" 
                    :disabled="permissionForm.processing"
                    class="bg-[#1676A2] hover:bg-[#125d80] border-transparent"
                >
                    {{ editPermissionFlag ? 'Guardar' : 'Crear' }}
                </PrimaryButton>
            </template>
        </DialogModal>

    </div>
</template>

<style scoped>
/* Transición suave para el cambio de pestañas */
.animate-fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Scrollbar personalizada */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>