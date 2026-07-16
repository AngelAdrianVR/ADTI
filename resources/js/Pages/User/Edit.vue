<template>
    <AppLayout title="Editar usuario">
        <div class="px-3 md:px-16 py-8">
            <el-button
                @click="$inertia.visit(route('users.show', user.id))"
                circle
                class="!border-gray-200 !text-gray-500 hover:!text-indigo-600 hover:!border-indigo-300 !shadow-sm"
            >
                <i class="fa-solid fa-angle-left text-base"></i>
            </el-button>
            
            <UserForm 
                :form="form" 
                :is-edit="true" 
                :roles="roles" 
                :departments="departments" 
                :job_positions="job_positions" 
                :users="users"
                :user-image-url="user.profile_photo_url"
                @submit="update" 
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
        user: Object,
        user_roles: Array,
        departments: Array,
        job_positions: Array,
        users: Array,
    },
    data() {
        return {
            form: useForm({
                //datos personales
                name: this.user.name,
                email: this.user.email,
                phone: this.user.phone,
                birthdate: this.user.birthdate,
                civil_state: this.user.civil_state,
                address: this.user.address,
                rfc: this.user.rfc,
                curp: this.user.curp,
                ssn: this.user.ssn,
                //datos laborales
                code: this.user.code,
                org_props: {
                    entry_date: this.user.org_props.entry_date,
                    position: this.user.org_props.position,
                    department: this.user.org_props.department,
                    work_shift: this.user.org_props.work_shift || 'Diurno', // Recupera el turno guardado o pone por defecto
                    email: this.user.org_props.email,
                    phone: this.user.org_props.phone,
                    net_salary: this.user.org_props.net_salary,
                    biweekly_complement: this.user.org_props.biweekly_complement,
                    month_complement: this.user.org_props.month_complement,
                    vacations: this.user.org_props.vacations,
                    updated_date_vacations: this.user.org_props.updated_date_vacations,
                },
                employees_in_charge: this.user.employees_in_charge || [],
                image: null,
                roles: this.user_roles,
                selectedImage: this.user.profile_photo_url
            })
        }
    },
    methods: {
        update() {
            if (this.form.image) {
                this.form.post(route("users.update-with-media", this.user.id), {
                    method: '_put',
                    onSuccess: () => {
                        this.$notify({
                            title: "Correcto",
                            message: "Usuario actualizado",
                            type: "success",
                        });
                    },
                });
            } else {
                this.form.put(route("users.update", this.user.id), {
                    onSuccess: () => {
                        this.$notify({
                            title: "Correcto",
                            message: "Usuario actualizado",
                            type: "success",
                        });
                    },
                });
            }
        },
    },
}
</script>