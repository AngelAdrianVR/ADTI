<template>

    <Head title="Pre nomina catorcena 20, 2024" />
    <header class="px-6 lg:px-10 py-5">
        <section class="flex items-center justify-between">
            <ApplicationMark class="w-32" />
            <PrimaryButton>Imprimir o guardar en PDF</PrimaryButton>
        </section>
        <section class="text-center mt-5">
            <h1 class="font-bold">Pre-nómina</h1>
            <h2 class="font-bold">Catorcena {{ payroll.biweekly }}. {{ formatDate(payroll.start_date) }} al {{ getEndPeriod(payroll.start_date) }}</h2>
        </section>
    </header>
    <main class="px-6 lg:px-10 mt-10">
        <section>
            <table class="w-full table-fixed mt-2 text-sm">
                <thead>
                    <tr
                        class="*:px-1 *:py-px *:text-start *:text-white *:font-normal *:border *:border-grayD9 rounded-t-[10px] *:bg-[#0B3B51]">
                        <th class="w-[10%]">ID</th>
                        <th class="w-[30%]">Empleado</th>
                        <th class="w-[30%]">Días a pagar</th>
                        <th class="w-[30%]">Incidencias</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in payrollUsers" :key="index"
                        class="*:px-1 *:py-px *:text-start *:border *:border-grayD9 even:bg-grayF2">
                        <td class="w-[10%]">{{ item.user.id }}</td>
                        <td class="w-[30%]">{{ item.user.name }}</td>
                        <td class="w-[30%]">{{ '14' }}</td>
                        <td class="w-[30%]">{{ item.user.id }}</td>
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
        getEndPeriod(start) {
            const end = addDays(start, 13);

            return format(add(end, { hours: 6 }), 'dd MMMM yyyy', { locale: es });
        },
        formatDate(dateString) {
            const date = parseISO(dateString);
            return format(add(date, { hours: 6 }), 'dd MMMM yyyy', { locale: es });
        },
    }
}
</script>
