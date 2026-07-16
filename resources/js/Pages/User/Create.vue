<template>
    <AppLayout title="Nuevo usuario">
        <div class="px-3 md:px-16 py-8">
            <el-button
                @click="$inertia.visit(route('users.index'))"
                circle
                class="!border-gray-200 !text-gray-500 hover:!text-indigo-600 hover:!border-indigo-300 !shadow-sm"
            >
                <i class="fa-solid fa-angle-left text-base"></i>
            </el-button>

            <UserForm 
                :form="form" 
                :roles="roles" 
                :departments="departments" 
                :job_positions="job_positions" 
                :users="users"
                @submit="store" 
            />
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import UserForm from "./Partials/UserForm.vue";
import { useForm } from "@inertiajs/vue3";

export default {
    components: {
        AppLayout,
        UserForm,
    },
    props: {
        roles: Array,
        departments: Array,
        job_positions: Array,
        users: Array,
    },
    data() {
        return {
            form: useForm({
                //datos personales
                name: null,
                email: null,
                phone: null,
                birthdate: null,
                civil_state: null,
                address: null,
                rfc: null,
                curp: null,
                ssn: null,
                //datos laborales
                code: null,
                org_props: {
                    entry_date: null,
                    position: null,
                    department: null,
                    work_shift: 'Diurno', // Valor por defecto
                    email: null,
                    phone: null,
                    biweekly_complement: null,
                    month_complement: null,
                    net_salary: null,
                    vacations: null,
                    updated_date_vacations: null,
                },
                employees_in_charge: [],
                image: null,
                roles: [],
            })
        }
    },
    methods: {
        store() {
            this.form.post(route("users.store"), {
                onSuccess: () => {
                    this.$notify({
                        title: "Correcto",
                        message: "Usuario creado exitosamente",
                        type: "success",
                        position: "bottom-right",
                    });
                },
            });
        },
    }
}
</script>