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

const props = defineProps({
    jobPositions: Array,
});

const showModal = ref(false);
const editFlag = ref(false);
const currentItem = ref(null);
const form = useForm({
    name: null,
});

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
    form.post(route('job-positions.store'), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Puesto creado correctamente' });
            showModal.value = false;
            form.reset();
        },
    });
};

const update = () => {
    form.put(route('job-positions.update', currentItem.value.id), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Puesto actualizado correctamente' });
            showModal.value = false;
            form.reset();
        },
    });
};

const deleteItem = (item) => {
    router.delete(route('job-positions.destroy', item.id), {
        onSuccess: () => {
            ElNotification.success({ title: 'Éxito', message: 'Puesto eliminado' });
        },
    });
};
</script>

<template>
    <div class="py-6 animate-fade-in">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h2 class="text-lg font-bold text-[#1676A2]">Puestos de Trabajo</h2>
                <p class="text-xs text-[#6D6E72]">Gestiona los roles y jerarquías laborales para los empleados.</p>
            </div>
            <button 
                v-if="$page.props.auth.user.permissions.includes('Crear puestos')"
                @click="openCreate"
                class="bg-[#1676A2] hover:bg-[#125d80] text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2"
            >
                <i class="fa-solid fa-plus"></i> Nuevo Puesto
            </button>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-[#6D6E72] uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-3">Título del Puesto</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in jobPositions" :key="item.id" class="hover:bg-gray-50 transition-colors group">
                            <td class="px-6 py-4 font-medium text-gray-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                                        <i class="fa-solid fa-id-badge text-xs"></i>
                                    </div>
                                    {{ item.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button 
                                        v-if="$page.props.auth.user.permissions.includes('Editar puestos')"
                                        @click="openEdit(item)" 
                                        class="text-[#1676A2] hover:bg-blue-50 p-2 rounded-lg transition-colors"
                                        title="Editar"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    
                                    <el-popconfirm
                                        v-if="$page.props.auth.user.permissions.includes('Eliminar puestos')"
                                        title="¿Eliminar este puesto?"
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
                        <tr v-if="!jobPositions.length">
                            <td colspan="2" class="px-6 py-12 text-center text-[#6D6E72] italic bg-gray-50/50">
                                No hay puestos registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <DialogModal :show="showModal" @close="showModal = false" maxWidth="md">
            <template #title>
                <span class="font-bold text-[#1676A2]">{{ editFlag ? 'Editar Puesto' : 'Nuevo Puesto' }}</span>
            </template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Título del Puesto *" />
                        <TextInput v-model="form.name" class="w-full mt-1 border-gray-300 focus:border-[#1676A2] focus:ring-[#1676A2]" placeholder="Ej. Gerente de Ventas" autofocus />
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