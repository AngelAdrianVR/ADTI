<template>
    <section class="border border-grayCC rounded-[10px]">
        <div class="bg-grayED mt-4 px-2 md:px-8 py-1">
            <p class="flex items-center space-x-1 text-sm lg:text-base">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <span>{{ payrollUser.user.name }}</span>
            </p>
            <p class="text-gray99">{{ payrollUser.user.org_props.department }}</p>
            <p v-if="payrollUser.user.paused" class="flex justify- space-x-1 text-red-400 text-xs">
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14.25 9v6m-4.5 0V9M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <span>Pausó desde las {{ payrollUser.user.paused }}</span>
            </p>
        </div>
        <div class="border-b">
            <table class="w-full table-fixed text-[11px] md:text-base">
                <thead class="border-b">
                    <tr class="*:text-start *:py-3">
                        <th class="pl-2 md:pl-8">DÍA</th>
                        <th>ENTRADA</th>
                        <th>SALIDA</th>
                        <th>T. EXTRA</th>
                        <th>HORAS TOTALES</th>
                        <th class="pr-2 md:pr-8 w-8 md:w-16"></th>
                    </tr>
                </thead>
                <tbody class="text-xs lg:text-sm">
                    <tr v-for="(item, index) in payrollUser.incidences" :key="index" class="*:text-start *:py-1">
                        <td class="pl-2 md:pl-8">{{ formatDate(item.date) }}</td>
                        <!-- Verificar si es día de descanso o falta injustificada -->
                        <template v-if="item.incidence">
                            <td colspan="4">
                                <p class="text-center rounded-[5px] py-2" :class="getColors(item.incidence)">
                                    {{ item.incidence }}
                                </p>
                            </td>
                        </template>
                        <template v-else>
                            <td>
                                <div class="flex items-center space-x-1">
                                    <div v-if="item.checked_in_platform">
                                        <el-tooltip content="Acceso remoto" placement="top">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="size-4 text-[#F29513]">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                            </svg>
                                        </el-tooltip>
                                    </div>
                                    <el-tooltip v-if="item.late" :content="`${item.late} minutos tarde`"
                                        placement="top">
                                        <span class="text-[#E95C10]">{{ formatTimeTo12Hour(item.check_in) }}</span>
                                    </el-tooltip>
                                    <p v-else>{{ formatTimeTo12Hour(item.check_in) }}</p>
                                </div>
                            </td>
                            <td>
                                <p>{{ formatTimeTo12Hour(item.check_out) }}</p>
                            </td>
                            <td>{{ calculateTimes(item).extra }}</td>
                            <td>{{ calculateTimes(item).total }}</td>
                        </template>
                        <td v-if="$page.props.auth.user.permissions.includes('Editar incidencias')" class="">
                            <el-dropdown trigger="click" @command="handleCommand">
                                <button @click.stop
                                    class="el-dropdown-link justify-center items-center size-8 rounded-full text-primary hover:bg-grayED transition-all duration-200 ease-in-out">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item :command="'1|' + item.date">
                                            {{ item.incidence ? 'Agregar asistencia' : 'Editar asistencia' }}
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="item.late" :command="'quitar-retardo|' + item.date">
                                            Quitar retardo
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
        <div class="px-2 md:px-8 py-2 text-xs md:text-base">
            <section v-if="payrollUser.comments">
                <div class="flex items-center justify-between">
                    <h1 class="font-bold">Comentarios</h1>
                    <div v-if="$page.props.auth.user.permissions.includes('Editar incidencias')"
                        class="flex items-center space-x-1">
                        <button type="button" @click="editComments"
                            class="size-6 rounded-full bg-grayED flex items-center justify-center text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                        <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#0355B5"
                            title="Se borrará el comentario. ¿Continuar?" @confirm="deleteComments">
                            <template #reference>
                                <button type="button"
                                    class="size-6 rounded-full bg-grayED flex items-center justify-center text-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </template>
                        </el-popconfirm>
                    </div>
                </div>
                <p style="white-space: pre-line">{{ payrollUser.comments.comments }}</p>
            </section>
            <ThirthButton v-else @click="createComments" class="!py-1">Agregar comentarios</ThirthButton>
        </div>
    </section>

    <DialogModal :show="showCommentsModal" @close="showCommentsModal = false" maxWidth="lg">
        <template #title>
            <h1>Agregar comentarios para <b class="text-primary">{{ payrollUser.user.name }}</b></h1>
        </template>
        <template #content>
            <div>
                <InputLabel value="Comentarios" />
                <el-input v-model="form.comments" :autosize="{ minRows: 3, maxRows: 6 }" type="textarea"
                    placeholder="Escribe cualquier comentario" :maxlength="1200" show-word-limit clearable />
                <InputError :message="form.errors.comments" />
            </div>
        </template>
        <template #footer>
            <div class="flex items-center space-x-2">
                <CancelButton @click="showCommentsModal = false" :disabled="form.processing">Cancelar</CancelButton>
                <PrimaryButton v-if="payrollUser.comments" @click="updateComments" :disabled="form.processing">
                    <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                    Guardar cambios
                </PrimaryButton>
                <PrimaryButton v-else @click="storeComments" :disabled="form.processing">
                    <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                    Guardar
                </PrimaryButton>
            </div>
        </template>
    </DialogModal>

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
</template>

<script>
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import ThirthButton from '@/Components/MyComponents/ThirthButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { format, parseISO, parse, isSameDay, differenceInMinutes, add } from 'date-fns';
import es from 'date-fns/locale/es';
import InputError from '@/Components/InputError.vue';

export default {
    data() {
        const form = useForm({
            check_in: null,
            check_out: null,
            user_id: this.payrollUser.user.id,
            payroll_id: this.payroll.id,
            date: null,
            incidence: null,
            comments: null,
        });

        return {
            form,
            showAttendanceModal: false,
            showCommentsModal: false,
        }
    },
    components: {
        DialogModal,
        ConfirmationModal,
        InputLabel,
        InputError,
        PrimaryButton,
        CancelButton,
        ThirthButton,
    },
    props: {
        payrollUser: Object,
        payroll: Object,
    },
    methods: {
        removeLate() {
            this.form.post(route('payroll-users.remove-late'), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Retardo removido',
                        type: 'success'
                    })
                },
                onError: (error) => {
                    console.log(error)
                }
            })
        },
        setAttendance() {
            this.form.post(route('payroll-users.set-attendance'), {
                onSuccess: () => {
                    this.showAttendanceModal = false;
                },
                onError: (error) => {
                    console.log(error)
                }
            })
        },
        storeComments() {
            this.form.post(route('payroll-comments.store'), {
                onSuccess: () => {
                    this.showCommentsModal = false;
                }
            })
        },
        createComments() {
            this.form.comments = null;
            this.showCommentsModal = true;
        },
        editComments() {
            this.form.comments = this.payrollUser.comments.comments;
            this.showCommentsModal = true;
        },
        updateComments() {
            this.form.put(route('payroll-comments.update', this.payrollUser.comments.id), {
                onSuccess: () => {
                    this.showCommentsModal = false;
                },
                onError: (error) => {
                    console.log(error);
                }
            });
        },
        deleteComments() {
            this.form.delete(route('payroll-comments.destroy', this.payrollUser.comments.id));
        },
        setIncidence() {
            this.form.put(route('payroll-users.set-incidence'), {
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
            const timeFormat = 'HH:mm';

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
            this.form.date = date.split('T')[0];

            // actualizar incidencia
            if (['Descanso', 'Falta injustificada', 'Falta justificada', 'Incapacidad', 'Permiso sin goce', 'Permiso con goce', 'Vacaciones'].includes(commandName)) {
                this.form.incidence = commandName;
                this.setIncidence();
            } else if (commandName === '1') {
                // cargar hora de entrada y salida si las tiene
                const register = this.payrollUser.incidences.find(i => isSameDay(i.date, date));
                if (register) {
                    // eliminar segundos
                    this.form.check_in = register.check_in ? register.check_in.split(':').slice(0, 2).join(':') : null;
                    this.form.check_out = register.check_out ? register.check_out.split(':').slice(0, 2).join(':') : null;
                }

                this.showAttendanceModal = true;
            } else if (commandName == 'quitar-retardo') {
                this.removeLate();
            }

        },
    }
}
</script>
