<template>

    <Head :title="`Pre-nomina catorcena ${payroll.biweekly}, ${formatDateToYear(payroll.start_date)}`" />
    <header class="px-6 lg:px-10 py-2">
        <section class="flex items-center justify-between">
            <ApplicationMark class="w-28" />
            <PrimaryButton v-if="templateView" @click="printScreen">Imprimir o guardar en PDF</PrimaryButton>
        </section>
        <section class="text-center mt-1">
            <h1 class="font-bold">Pre-nómina</h1>
            <h2 class="font-bold">Catorcena {{ payroll.biweekly }}. {{ formatDate(payroll.start_date) }} al {{ getEndPeriod(payroll.start_date) }}</h2>
        </section>
    </header>
    <main class="px-6 lg:px-10 mt-1">
        <section>
            <table class="w-full table-fixed mt-2 text-xs">
                <thead>
                    <tr
                        class="*:px-1 *:py-px *:text-start *:text-white *:font-normal *:border *:border-grayD9 rounded-t-[10px] *:bg-[#0B3B51]">
                        <th class="w-[8%]">ID</th>
                        <th class="w-[20%]">Empleado</th>
                        <th class="w-[14%]">Días a pagar</th>
                        <th class="w-[58%]">Incidencias</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in payrollUsers" :key="index"
                        class="*:px-1 *:py-px *:text-start *:border *:border-grayD9 even:bg-grayF2">
                        <td class="w-[8%]">{{ item.user.code }}</td>
                        <td class="w-[20%]">{{ item.user.name }}</td>
                        <td class="w-[14%]">{{ getDaysToPay(item) }}</td>
                        <td class="w-[58%]">
                            <p v-for="(incidence, index2) in getDaysWithIncidence(item)" :key="index2" class="text-gray37">
                                • {{ incidence.incidence }} <span class="text-black">{{ formatDateShort(incidence.date) }}</span>
                            </p>
                            <p v-for="(extra, index2) in getDaysWithExtraTime(item)" :key="index2" class="text-gray37">
                                • Tiempo extra ({{ extra.extra_hours }}h {{ extra.extra_minutes }}m)
                                <span class="text-black">{{ formatDateShort(extra.date) }}</span>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</template>

<script>
import ApplicationMark from '@/Components/ApplicationMark.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head } from '@inertiajs/vue3';
import { format, parseISO, addDays, add } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        return {
            templateView: true,
        }
    },
    components: {
        Head,
        PrimaryButton,
        ApplicationMark,
    },
    props: {
        payrollUsers: Object,
        payroll: Object,
    },
    methods: {
        printScreen() {
            this.templateView = false;
            this.$nextTick(() => {
                window.print();
            });
        },
        handleAfterPrint() {
            this.templateView = true;
        },
        getDaysWithIncidence(payrollUser) {
            const incidencesToNotShow = [
                'Descanso',
                'Sin registro aún',
            ];

            return payrollUser.incidences.filter(i => i.incidence && !incidencesToNotShow.includes(i.incidence));
        },
        getDaysWithExtraTime(payrollUser) {
            return payrollUser.incidences.filter(i => !i.incidence && (i.extra_hours || i.extra_minutes));
        },
        getDaysToPay(payrollUser) {
            return payrollUser.incidences.filter(i => !i.incidence).length;
        },
        getEndPeriod(start) {
            const end = addDays(start, 13);

            return format(add(end, { hours: 6 }), 'dd MMMM yyyy', { locale: es });
        },
        formatDate(dateString) {
            const date = parseISO(dateString);
            return format(add(date, { hours: 6 }), 'dd MMMM yyyy', { locale: es });
        },
        formatDateShort(dateString) {
            const date = parseISO(dateString);
            return format(add(date, { hours: 6 }), 'dd MMM yyyy', { locale: es });
        },
        formatDateToYear(dateString) {
            const date = parseISO(dateString);
            return format(add(date, { hours: 6 }), 'yyyy', { locale: es });
        },
    },
    mounted() {
        window.addEventListener('afterprint', this.handleAfterPrint);
    },
    beforeDestroy() {
        window.removeEventListener('afterprint', this.handleAfterPrint);
    }
}
</script>
