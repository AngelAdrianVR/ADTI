<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ElNotification } from 'element-plus';

const props = defineProps({
    user: Object,
    payroll: Object,
});

const showAttendanceModal = ref(false);

const form = useForm({
    check_in: null,
    check_out: null,
    user_id: props.user.id,
    payroll_id: props.payroll.id,
    date: format(new Date(), "yyyy-MM-dd"), // Fecha de hoy por defecto
});

const handleRegisterAttendance = () => {
    showAttendanceModal.value = true;
};

const setAttendance = () => {
    form.post(route('payroll-users.set-attendance'), {
        onSuccess: () => {
            ElNotification.success({
                title: 'Éxito',
                message: 'Asistencia registrada correctamente'
            });
            showAttendanceModal.value = false;
            form.reset();
        },
        onError: () => {
            ElNotification.error({
                title: 'Error',
                message: 'No se pudo registrar la asistencia'
            });
        }
    });
};

const formatDate = (date) => {
    return format(date, 'eee, dd MMM yyyy', { locale: es });
};
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-between gap-4 hover:shadow-md transition-shadow">
        
        <div class="flex items-center gap-3 overflow-hidden">
            <!-- Avatar -->
            <div class="shrink-0">
                <img 
                    :src="user.profile_photo_url" 
                    :alt="user.name"
                    class="h-10 w-10 rounded-full object-cover border border-gray-100"
                >
            </div>
            
            <!-- Info -->
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-gray-800 truncate" :title="user.name">
                    {{ user.name }}
                </h3>
                <p class="text-xs text-gray-500 truncate" :title="user.org_props?.department">
                    {{ user.org_props?.department || 'Sin departamento' }}
                </p>
            </div>
        </div>

        <!-- Action -->
        <div v-if="payroll.is_active" class="shrink-0">
            <button 
                @click="handleRegisterAttendance"
                class="text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 p-2 rounded-lg transition-colors text-xs font-bold flex items-center gap-1"
                title="Registrar asistencia manual"
            >
                <i class="fa-solid fa-plus-circle text-sm"></i>
                <span class="hidden sm:inline">Asistencia</span>
            </button>
        </div>

        <!-- Modal -->
        <DialogModal :show="showAttendanceModal" @close="showAttendanceModal = false" maxWidth="md">
            <template #title>
                <span class="font-bold text-gray-800">Registrar Asistencia Manual</span>
            </template>
            
            <template #content>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">
                        Registrar entrada para <b>{{ user.name }}</b> el día de hoy ({{ formatDate(new Date()) }}).
                    </p>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Hora Entrada *" />
                            <TextInput 
                                type="time" 
                                v-model="form.check_in" 
                                class="w-full mt-1" 
                            />
                            <p v-if="form.errors.check_in" class="text-xs text-red-500 mt-1">{{ form.errors.check_in }}</p>
                        </div>
                        <div>
                            <InputLabel value="Hora Salida" />
                            <TextInput 
                                type="time" 
                                v-model="form.check_out" 
                                class="w-full mt-1" 
                            />
                        </div>
                    </div>
                </div>
            </template>
            
            <template #footer>
                <div class="flex gap-2">
                    <SecondaryButton @click="showAttendanceModal = false">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton @click="setAttendance" :disabled="form.processing">
                        Guardar Registro
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>

    </div>
</template>