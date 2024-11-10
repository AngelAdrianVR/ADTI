<template>
    <section class="border border-grayCC rounded-[10px]">
        <div class="bg-grayED mt-4 px-8 py-1">
            <p class="flex items-center space-x-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <span>{{ payrollUser.user.name }}</span>
            </p>
            <p class="text-gray99">{{ payrollUser.user.org_props.department }}</p>
        </div>
        <div>
            <table class="w-full table-fixed">
                <thead class="border-b">
                    <tr class="*:text-start *:py-3">
                        <th class="pl-8">DÍA</th>
                        <th>ENTRADA</th>
                        <th>SALIDA</th>
                        <th>T. EXTRA</th>
                        <th>HORAS TOTALES</th>
                        <th class="pr-8 w-16"></th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <tr v-for="(item, index) in payrollUser.incidences" :key="index" class="*:text-start *:py-1">
                        <td class="pl-8">{{ formatDate(item.date) }}</td>
                        <!-- Verificar si es día de descanso o falta injustificada -->
                        <template v-if="item.incidence">
                            <td colspan="4">
                                <p class="text-center rounded-[5px] py-2" :class="getColors(item.incidence)">
                                    {{ item.incidence }}
                                </p>
                            </td>
                        </template>
                        <template v-else>
                            <td class="relative">
                                <div v-if="item.checked_in_platform" class="!absolute -left-5 top-3">
                                    <el-tooltip content="Home office" placement="top">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="size-4 text-[#F29513]">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                        </svg>
                                    </el-tooltip>
                                </div>
                                <p>{{ formatTimeTo12Hour(item.check_in) }}</p>
                                <!-- <input type="time" v-model="item.check_in" disabled class="input w-2/3"> -->
                            </td>
                            <td>
                                <p>{{ formatTimeTo12Hour(item.check_out) }}</p>
                                <!-- <input type="time" v-model="item.check_out" disabled class="input w-2/3"> -->
                            </td>
                            <td>{{ calculateTimes(item).extra }}</td>
                            <td>{{ calculateTimes(item).total }}</td>
                        </template>
                        <td class="pr-8 w-16">
                            <el-dropdown trigger="click" @command="handleCommand">
                                <button @click.stop
                                    class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-primary hover:bg-grayED transition-all duration-200 ease-in-out">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item :command="'1|' + item.date">
                                            {{ item.incidence ? 'Agregar asistencia' : 'Editar asistencia' }}
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'Descanso|' + item.date">
                                            Descanso
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'Falta injustificada|' + item.date">
                                            Falta injustificada
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'Falta justificada|' + item.date">
                                            Falta justificada
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'Incapacidad|' + item.date">
                                            Incapacidad
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'Permiso sin goce|' + item.date">
                                            Permiso sin goce
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'Permiso con goce|' + item.date">
                                            Permiso con goce
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'Vacaciones|' + item.date">
                                            Vacaciones
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <DialogModal :show="showAttendanceModal" @close="showAttendanceModal = false" maxWidth="lg">
        <template #title>
            <h1>Asistencia de <b class="text-primary">{{ formatDate(form.date) }}</b></h1>
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

    <ConfirmationModal :show="showVacationsConfirmation" @close="showVacationsConfirmation = false" maxWidth="lg">
        <template #title>
            <h1>Vacaciones para <b class="text-primary">{{ formatDate(form.date) }}</b></h1>
        </template>
        <template #content>
            <p>
                Al continuar se descontará 1 día de las vacaciones registradas en el sistema para el usuario
                seleccionado. ¿Deseas continuar?
            </p>
        </template>
        <template #footer>
            <div class="flex items-center space-x-2">
                <CancelButton @click="showVacationsConfirmation = false" :disabled="form.processing">Cancelar</CancelButton>
                <PrimaryButton @click="setIncidence" :disabled="form.processing">
                    <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                    Continuar
                </PrimaryButton>
            </div>
        </template>
    </ConfirmationModal>
</template>

<script>
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { format, parseISO, parse, isSameDay, differenceInMinutes, add } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        const form = useForm({
            check_in: null,
            check_out: null,
            user_id: this.payrollUser.user.id,
            payroll_id: this.payroll.id,
            date: null,
            incidence: null,
        });

        return {
            form,
            showAttendanceModal: false,
            showVacationsConfirmation: false,
        }
    },
    components: {
        DialogModal,
        ConfirmationModal,
        InputLabel,
        PrimaryButton,
        CancelButton,
    },
    props: {
        payrollUser: Object,
        payroll: Object,
    },
    methods: {
        setAttendance() {
            this.form.post(route('payrolls.set-attendance'), {
                onSuccess: () => {
                    this.showAttendanceModal = false;
                }
            })
        },
        setIncidence() {
            this.form.put(route('payrolls.set-incidence'), {
                onSuccess: () => {
                    this.showVacationsConfirmation = false;
                }
            })
        },
        getColors(incidence) {
            if (['Falta injustificada', 'Falta justificada'].includes(incidence)) {
                return 'bg-[#FDB3B3] text-[#DB0909]';
            } else if (['Incapacidad', 'Vacaciones'].includes(incidence)) {
                return 'bg-[#F2FEA8] text-[#C3A502]';
            } else if (['Permiso sin goce', 'Permiso con goce'].includes(incidence)) {
                return 'bg-blue-100 text-blue-400';
            } else if (['Sin registro aún'].includes(incidence)) {
                return 'bg-gray-100 text-gray-400';
            } else {
                return 'bg-[#C8FEC7] text-[#179E15]';
            }
        },
        getIncidenceByDate(date) {
            return this.payrollUser.incidences.find(i => isSameDay(i.date, date));
        },
        isSameDay(date1, date2) {
            const parsedDate1 = typeof date1 === 'string' ? parseISO(date1) : date1;
            const parsedDate2 = typeof date2 === 'string' ? parseISO(date2) : date2;
            return isSameDay(parsedDate1, parsedDate2);
        },
        formatDate(dateString) {
            const date = parseISO(dateString);
            return format(add(date, { hours: 6 }), 'eee, dd MMM yyyy', { locale: es });
        },
        formatTimeTo12Hour(timeString) {
            // Verificar si el valor es un string de tiempo (por ejemplo, '12:18:00')
            if (typeof timeString === 'string') {
                // Crear un objeto Date usando una fecha arbitraria y la hora
                const timeAsDate = new Date(`1970-01-01T${timeString}`);

                // Formatear a 12 horas
                return format(timeAsDate, 'hh:mm aa');
            }

            // Si no es un string, retorna un mensaje o lanza un error
            return '-';
        },
        calculateTimes(incidence) {
            if (!incidence.id || !incidence.check_in) return { extra: '-', total: '-' };
            // Formato de entrada para los tiempos de check_in y check_out
            const timeFormat = 'HH:mm:ss';

            // Convertimos check_in y check_out a objetos de fecha
            const checkInDate = parse(incidence.check_in, timeFormat, new Date());
            const checkOutDate = incidence.check_out
                ? parse(incidence.check_out, timeFormat, new Date())
                : new Date(); // Si no hay check_out, se toma la hora actual

            const extraHours = incidence.extra_hours ?? 0;
            const extraMinutes = incidence.extra_minutes ?? 0;
            // Calculamos el total de minutos trabajados
            const totalMinutesWorked = differenceInMinutes(checkOutDate, checkInDate) + extraHours * 60 + extraMinutes;

            // Calculamos horas y minutos para el total trabajado
            const totalHours = Math.floor(totalMinutesWorked / 60);
            const totalMinutes = (totalMinutesWorked % 60);
            const totalWorkedFormatted = `${totalHours}h ${totalMinutes}m`;

            // Calculamos horas y minutos para el tiempo extra
            const extraFormatted = `${incidence.extra_hours ?? 0}h ${incidence.extra_minutes ?? 0}m`;

            return {
                extra: extraFormatted,
                total: totalWorkedFormatted
            };
        },
        handleCommand(command) {
            const commandName = command.split('|')[0];
            const date = command.split('|')[1];
            this.form.date = date;

            // actualizar incidencia
            if (['Descanso', 'Falta injustificada', 'Falta justificada', 'Incapacidad', 'Permiso sin goce', 'Permiso con goce'].includes(commandName)) {
                this.form.incidence = commandName;
                this.setIncidence();
            } else if (commandName === 'Vacaciones') {
                this.form.incidence = commandName;
                this.showVacationsConfirmation = true;
            } else if (commandName === '1') {
                // cargar hora de entrada y salida si las tiene
                const register = this.payrollUser.incidences.find(i => isSameDay(i.date, date));
                if (register) {
                    this.form.check_in = register.check_in;
                    this.form.check_out = register.check_out;
                }
                this.showAttendanceModal = true;
            }

        },
    }
}
</script>
