<script setup>
import { ref, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DialogModal from "@/Components/DialogModal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { ElNotification } from "element-plus";

const props = defineProps({
    departments: Array,
});

const showModal = ref(false);
const editFlag = ref(false);
const currentItem = ref(null);
const form = useForm({
    name: null,
});

// --- Lógica del modal de reasignación ---
const page = usePage();
const showReassignModal = ref(false);
const reassignData = ref(null);
const reassignForm = useForm({
    new_department_id: null,
});

// Detectar cuando el backend devuelve datos de reasignación (flash)
watch(() => page.props.reassignData, (data) => {
    if (data) {
        reassignData.value = data;
        reassignForm.new_department_id = null;
        showReassignModal.value = true;
    }
}, { immediate: true });

// --- Métodos ---

const openCreate = () => {
    editFlag.value = false;
    currentItem.value = null;
    form.reset();
    showModal.value = true;
};

const openEdit = (item) => {
    editFlag.value = true;
    currentItem.value = item;
    form.name = item.name;
    showModal.value = true;
};

const store = () => {
    form.post(route('departments.store'), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Departamento creado correctamente' });
            showModal.value = false;
            form.reset();
        },
    });
};

const update = () => {
    form.put(route('departments.update', currentItem.value.id), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Departamento actualizado correctamente' });
            showModal.value = false;
            form.reset();
        },
    });
};

const deleteItem = (item) => {
    router.delete(route('departments.destroy', item.id), {
        onSuccess: () => {
            // Si hay reassignData en los props, significa que el backend
            // detectó tareas y no eliminó — el modal de reasignación se abrirá
            // automáticamente gracias al watch(). Si no hay reassignData,
            // la eliminación fue exitosa.
            if (!page.props.reassignData) {
                ElNotification.success({ title: 'Éxito', message: 'Departamento eliminado' });
            }
        },
        onError: () => {
            ElNotification.error({ title: 'Error', message: 'No se pudo eliminar el departamento.' });
        },
    });
};

const confirmReassign = () => {
    if (!reassignForm.new_department_id) {
        ElNotification.warning({ title: 'Atención', message: 'Debes seleccionar un departamento de destino.' });
        return;
    }

    reassignForm.post(route('departments.reassign-and-delete', reassignData.value.departmentId), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Departamento eliminado y tareas reasignadas correctamente.' });
            showReassignModal.value = false;
            reassignData.value = null;
        },
        onError: () => {
            ElNotification.error({ title: 'Error', message: 'No se pudo completar la operación.' });
        },
    });
};

const cancelReassign = () => {
    showReassignModal.value = false;
    reassignData.value = null;
    // Recargar la página para limpiar los datos flash
    router.reload();
};
</script>

<template>
    <div class="py-6 animate-fade-in">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h2 class="text-lg font-bold text-[#1676A2]">Departamentos</h2>
                <p class="text-xs text-[#6D6E72]">Gestiona las áreas organizacionales de la empresa.</p>
            </div>
            <button 
                v-if="$page.props.auth.user.permissions.includes('Crear departamentos')"
                @click="openCreate"
                class="bg-[#1676A2] hover:bg-[#125d80] text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2"
            >
                <i class="fa-solid fa-plus"></i> Nuevo Departamento
            </button>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-[#6D6E72] uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-3">Nombre del Departamento</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in departments" :key="item.id" class="hover:bg-gray-50 transition-colors group">
                            <td class="px-6 py-4 font-medium text-gray-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#1676A2] flex items-center justify-center">
                                        <i class="fa-solid fa-building text-xs"></i>
                                    </div>
                                    {{ item.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button 
                                        v-if="$page.props.auth.user.permissions.includes('Editar departamentos')"
                                        @click="openEdit(item)" 
                                        class="text-[#1676A2] hover:bg-blue-50 p-2 rounded-lg transition-colors"
                                        title="Editar"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    
                                    <el-popconfirm
                                        v-if="$page.props.auth.user.permissions.includes('Eliminar departamentos')"
                                        title="¿Eliminar este departamento?"
                                        confirm-button-text="Sí, eliminar"
                                        cancel-button-text="No"
                                        icon-color="#DC2626"
                                        @confirm="deleteItem(item)"
                                        width="220"
                                    >
                                        <template #reference>
                                            <button class="text-[#6D6E72] hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Eliminar">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </template>
                                    </el-popconfirm>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!departments.length">
                            <td colspan="2" class="px-6 py-12 text-center text-[#6D6E72] italic bg-gray-50/50">
                                No hay departamentos registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <DialogModal :show="showModal" @close="showModal = false" maxWidth="md">
            <template #title>
                <span class="font-bold text-[#1676A2]">{{ editFlag ? 'Editar Departamento' : 'Nuevo Departamento' }}</span>
            </template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Nombre del Departamento *" />
                        <TextInput v-model="form.name" class="w-full mt-1 border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2]" placeholder="Ej. Recursos Humanos" autofocus />
                        <InputError :message="form.errors.name" />
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showModal = false" class="mr-2 border-gray-300 text-[#6D6E72] hover:text-gray-800">Cancelar</SecondaryButton>
                <PrimaryButton 
                    @click="editFlag ? update() : store()" 
                    :disabled="form.processing"
                    class="bg-[#1676A2] hover:bg-[#125d80] border-transparent"
                >
                    {{ editFlag ? 'Guardar Cambios' : 'Crear' }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Modal de Reasignación (aparece si el departamento tiene tareas) -->
        <DialogModal :show="showReassignModal" @close="cancelReassign" maxWidth="lg">
            <template #title>
                <span class="font-bold text-amber-600 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    No se puede eliminar el departamento
                </span>
            </template>
            <template #content>
                <div class="space-y-4">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <p class="text-sm text-gray-700">
                            El departamento <strong class="text-amber-700">"{{ reassignData?.departmentName }}"</strong> 
                            tiene <strong class="text-amber-700">{{ reassignData?.taskCount }} tarea(s)</strong> asignada(s) 
                            y no puede ser eliminado directamente.
                        </p>
                    </div>

                    <p class="text-sm text-gray-600">
                        Para continuar, selecciona otro departamento al que se reasignarán las tareas:
                    </p>

                    <div>
                        <InputLabel value="Reasignar tareas a:" />
                        <select
                            v-model="reassignForm.new_department_id"
                            class="w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-[#1676A2] focus:ring-[#1676A2] text-sm"
                        >
                            <option :value="null" disabled>Selecciona un departamento...</option>
                            <option
                                v-for="dept in reassignData?.otherDepartments"
                                :key="dept.id"
                                :value="dept.id"
                            >
                                {{ dept.name }}
                            </option>
                        </select>
                        <InputError :message="reassignForm.errors.new_department_id" />
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="cancelReassign" class="mr-2 border-gray-300 text-[#6D6E72] hover:text-gray-800">
                    Cancelar eliminación
                </SecondaryButton>
                <PrimaryButton
                    @click="confirmReassign"
                    :disabled="reassignForm.processing || !reassignForm.new_department_id"
                    class="bg-amber-600 hover:bg-amber-700 border-transparent"
                >
                    <i v-if="reassignForm.processing" class="fa-solid fa-circle-notch fa-spin mr-2"></i>
                    Reasignar y eliminar
                </PrimaryButton>
            </template>
        </DialogModal>

    </div>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>