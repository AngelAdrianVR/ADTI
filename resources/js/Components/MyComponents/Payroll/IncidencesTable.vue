<template>
    <section class="border border-grayCC rounded-[10px]">
        <div class="bg-grayED mt-4 px-8 py-1">
            <p class="flex items-center space-x-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <span>{{ payroll.user.name }}</span>
            </p>
            <p class="text-gray99">{{ payroll.user.org_props.department }}</p>
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
                    <tr v-for="(item, index) in payroll.incidences" :key="index" class="*:text-start *:py-1">
                        <td class="pl-8">{{ formatDate(item.date) }}</td>
                        <!-- Verificar si es día de descanso o falta injustificada -->
                        <template v-if="!item.id">
                            <td colspan="4">
                                <p class="text-center rounded-[5px] py-2" :class="getColors(item.incidence)">
                                    {{ item.incidence }}
                                </p>
                            </td>
                        </template>
                        <!-- Mostrar check_in y check_out si hay incidencia -->
                        <template v-else>
                            <td>
                                <input type="time" v-model="item.check_in" class="input w-2/3">
                            </td>
                            <td>
                                <input type="time" v-model="item.check_out" class="input w-2/3">
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
                                        <el-dropdown-item v-if="!getIncidenceByDate(day)" :command="'1-' + day">
                                            Poner asistencia
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'2-' + day">
                                            Home office
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'3-' + day">
                                            Descanso
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'4-' + day">
                                            Falta injustificada
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'5-' + day">
                                            Falta justificada
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'6-' + day">
                                            Incapacidad
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'7-' + day">
                                            Permiso sin goce
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'8-' + day">
                                            Permiso con goce
                                        </el-dropdown-item>
                                        <el-dropdown-item :command="'9-' + day">
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
</template>

<script>
import { format, parseISO, parse, isSameDay, differenceInMinutes, add } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        return {

        }
    },
    props: {
        payroll: Object,
        dates: Array,
    },
    methods: {
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
            return this.payroll.incidences.find(i => isSameDay(i.date, date));
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
        isWeekend(index) {
            // Considerando que los índices 4, 5, 11 y 12 son sábados y domingos
            return index === 4 || index === 5 || index === 11 || index === 12;
        },
        calculateTimes(incidence) {
            if (!incidence.id) return { extra: '-', total: '-' };
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
            const commandName = command.split('-')[0];
            const rowId = command.split('-')[1];

            if (commandName === '1') {
                this.showInactivatigModal = true;
                this.inactivateUserId = rowId;
            } else {
                this.$inertia.get(route('users.' + commandName, rowId));
            }

        },
    }
}
</script>
