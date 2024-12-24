<template>
    <section class="border bg-grayED border-grayCC rounded-[10px] flex items-center justify-between md:px-8 px-2">
        <div class="py-3">
            <p class="flex items-center space-x-1 text-sm lg:text-base">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <span>{{ user.name }}</span>
            </p>
            <p class="text-gray99">{{ user.org_props.department }}</p>
        </div>
        <div v-if="payroll.is_active">
            <PrimaryButton @click="handleRegisterAttendance">Registrar asistencia</PrimaryButton>
        </div>
    </section>

    <DialogModal :show="showAttendanceModal" @close="showAttendanceModal = false" maxWidth="lg">
        <template #title>
            <h1>Asistencia para hoy de <b class="text-primary">{{ user.name }}</b></h1>
        </template>
        <template #content>
            <form @submit.prevent="setAttendance" class="grid grid-cols-2 gap-3">
                <div>
                    <InputLabel value="Entrada" />
                    <input type="time" v-model="form.check_in" class="input">
                </div>
                <div>
                    <InputLabel value="Salida" />
                    <input type="time" v-model="form.check_out" class="input">
                </div>
            </form>
        </template>
        <template #footer>
            <div class="flex items-center space-x-2">
                <CancelButton @click="showAttendanceModal = false" :disabled="form.processing">Cancelar</CancelButton>
                <PrimaryButton @click="setAttendance" :disabled="form.processing">
                    <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                    Guardar
                </PrimaryButton>
            </div>
        </template>
    </DialogModal>
</template>

<script>
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { format } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        const form = useForm({
            check_in: null,
            check_out: null,
            user_id: this.user.id,
            payroll_id: this.payroll.id,
            date: format(new Date(), "yyyy-MM-dd"), // Establece la fecha de hoy por defecto
        });

        return {
            form,
            showAttendanceModal: false,
        }
    },
    components: {
        DialogModal,
        InputLabel,
        PrimaryButton,
        CancelButton,
    },
    props: {
        user: Object,
        payroll: Object,
    },
    methods: {
        handleRegisterAttendance() {
            this.showAttendanceModal = true;
        },
        setAttendance() {
            this.form.post(route('payrolls.set-attendance'), {
                onSuccess: () => {
                    this.showAttendanceModal = false;
                }
            })
        },
        formatDate(date) {
            return format(date, 'eee, dd MMM yyyy', { locale: es });
        },
    }
}
</script>
