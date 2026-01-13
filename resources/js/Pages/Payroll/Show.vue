<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import IncidencesTable from '@/Components/MyComponents/Payroll/IncidencesTable.vue';
import NoAttendanceCard from '@/Components/MyComponents/Payroll/NoAttendanceCard.vue';

const props = defineProps({
    payroll: Object,
    payrollUsers: Array,
    noAttendances: Array,
});

const openTemplate = () => {
    const url = route('payrolls.pre-payroll', props.payroll.id);
    window.open(url, '_blank');
};
</script>

<template>
    <AppLayout :title="`Nómina #${payroll.id}`">
        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                    <div class="flex items-center self-start md:self-auto">
                        <Back :route="route('payrolls.index')" class="mr-4" />
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Detalle de Nómina</h1>
                            <p class="text-xs text-gray-500 mt-1">Periodo del {{ payroll.start_date.split('T')[0] }} (14 días)</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <PrimaryButton 
                            v-if="$page.props.auth.user.permissions.includes('Ver pre-nominas')"
                            @click="openTemplate"
                            class="!bg-indigo-600 hover:!bg-indigo-700"
                        >
                            <i class="fa-solid fa-file-invoice-dollar mr-2"></i> Generar Pre-nómina
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Sección: Asistencias (Lista Acordeón) -->
                <section v-if="payrollUsers.length" class="space-y-4 mb-12">
                    <div class="flex items-center justify-between px-2">
                        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider">
                            Colaboradores Activos ({{ payrollUsers.length }})
                        </h2>
                    </div>
                    
                    <div class="space-y-3">
                        <IncidencesTable 
                            v-for="(item, index) in payrollUsers" 
                            :key="item.user.id" 
                            :payrollUser="item"
                            :payroll="payroll" 
                        />
                    </div>
                </section>

                <!-- Sección: Sin Asistencia -->
                <section v-if="noAttendances.length" class="space-y-4">
                    <div class="flex items-center gap-2 px-2 text-amber-600">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <h2 class="text-sm font-bold uppercase tracking-wider">
                            Sin registros de asistencia ({{ noAttendances.length }})
                        </h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <NoAttendanceCard 
                            v-for="(item, index) in noAttendances" 
                            :user="item" 
                            :payroll="payroll" 
                            :key="item.id" 
                        />
                    </div>
                </section>

            </div>
        </main>
    </AppLayout>
</template>