<template>
    <AppLayout title="Incidencias">
        <header class="mx-2 lg:mx-20 mt-6">
            <Back :to="route('payrolls.index')" />
            <section class="flex items-center justify-between">
                <h1 class="font-bold text-gray37">Asistencias de empleados</h1>
                <PrimaryButton>Generar Pre-nómina</PrimaryButton>
            </section>
        </header>
        <main class="mx-2 lg:mx-20 my-6 space-y-3">
            <IncidencesTable v-for="(item, index) in users" :key="index" :payrollUser="item" :payroll="payroll" />
        </main>
    </AppLayout>
</template>

<script>
import Back from '@/Components/MyComponents/Back.vue';
import IncidencesTable from '@/Components/MyComponents/Payroll/IncidencesTable.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format, addDays, add } from 'date-fns';

export default {
    data() {
        return {
            dates: [],
        }
    },
    components: {
        AppLayout,
        PrimaryButton,
        Back,
        IncidencesTable,
    },
    props: {
        payroll: Object,
        users: Array,
    },
    methods: {
        generateConsecutiveDates() {
            // Agrega 6 horas a la fecha inicial y luego genera las fechas consecutivas
            const startDate = add(new Date(this.payroll.start_date), { hours: 6 });

            this.dates = Array.from({ length: 14 }, (_, i) => {
                return format(addDays(startDate, i), 'yyyy-MM-dd');
            });
        },
        // handleCommand(command) {
        //     const commandName = command.split('-')[0];
        //     const rowId = command.split('-')[1];

        //     if (commandName === 'inactivate') {
        //         this.showInactivatigModal = true;
        //         this.inactivateUserId = rowId;
        //     } else {
        //         this.$inertia.get(route('payrolls.' + commandName, rowId));
        //     }

        // },
    },
    mounted() {
        this.generateConsecutiveDates();
    }
}
</script>
