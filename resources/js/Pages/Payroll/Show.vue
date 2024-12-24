<template>
    <AppLayout title="Incidencias">
        <header class="mx-2 lg:mx-20 mt-6">
            <Back :to="route('payrolls.index')" />
            <section v-if="payrollUsers.length" class="flex items-center justify-between">
                <h1 class="font-bold text-gray37">Asistencias de empleados</h1>
                <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Ver pre-nominas')"
                    @click="openTemplate">Generar Pre-nómina</PrimaryButton>
            </section>
        </header>
        <main class="mx-2 lg:mx-20 my-6 *:space-y-3">
            <section>
                <IncidencesTable v-for="(item, index) in payrollUsers" :key="index" :payrollUser="item"
                    :payroll="payroll" />
            </section>
            <section v-if="noAttendances.length">
                <h1 class="text-gray37 mt-10 mb-3 font-bold">Colaboradores sin asistencia esta catorcena</h1>
                <NoAttendanceCard v-for="(item, index) in noAttendances" :user="item" :payroll="payroll" :key="index" />
            </section>
        </main>
    </AppLayout>
</template>

<script>
import Back from '@/Components/MyComponents/Back.vue';
import IncidencesTable from '@/Components/MyComponents/Payroll/IncidencesTable.vue';
import NoAttendanceCard from '@/Components/MyComponents/Payroll/NoAttendanceCard.vue';
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
        NoAttendanceCard,
    },
    props: {
        payroll: Object,
        payrollUsers: Array,
        noAttendances: Array,
    },
    methods: {
        openTemplate() {
            const url = route('payrolls.pre-payroll', this.payroll);
            window.open(url, '_blank');
        },
        generateConsecutiveDates() {
            // Agrega 6 horas a la fecha inicial y luego genera las fechas consecutivas
            const startDate = add(new Date(this.payroll.start_date), { hours: 6 });

            this.dates = Array.from({ length: 14 }, (_, i) => {
                return format(addDays(startDate, i), 'yyyy-MM-dd');
            });
        },
    },
    mounted() {
        this.generateConsecutiveDates();
    }
}
</script>
