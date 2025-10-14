<template>
    <section class="lg:flex mt-4 mb-10 mx-4 text-xs lg:text-sm">
        <div class="lg:w-1/2 lg:border-r border-grayD9 grid grid-cols-2 gap-x-3 gap-y-2 lg:pr-16 self-start">
            <h1 class="font-bold text-gray37 col-span-full">Datos personales</h1>
            <p class="text-[#6D6E72]">Correo electrónico personal:</p>
            <p>{{ user.email ?? '-' }}</p>
            <p class="text-[#6D6E72]">Teléfono:</p>
            <p>{{ user.phone ?? '-' }}</p>
            <p class="text-[#6D6E72]">Fecha de nacimiento:</p>
            <p>{{ user.birthdate ? formatDate(user.birthdate) : "-" }}</p>
            <p class="text-[#6D6E72]">Estado civil:</p>
            <p>{{ user.civil_state ?? '-' }}</p>
            <p class="text-[#6D6E72]">Domicilio:</p>
            <p>{{ user.address ?? '-' }}</p>
            <p class="text-[#6D6E72]">RFC:</p>
            <p>{{ user.rfc ?? '-' }}</p>
            <p class="text-[#6D6E72]">CURP:</p>
            <p>{{ user.curp ?? '-' }}</p>
            <p class="text-[#6D6E72]">Número de seguro social:</p>
            <p>{{ user.ssn ?? '-' }}</p>
        </div>
        <div class="lg:w-1/2 grid grid-cols-2 gap-x-3 gap-y-2 lg:pl-16 mt-2 lg:mt-0 self-start">
            <h1 class="font-bold text-gray37 col-span-full">Datos laborales</h1>
            <p class="text-[#6D6E72]">Acceso remoto:</p>
            <p>
                <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#373737"
                    :title="user.home_office ? '¿Desactivar acceso remoto?' : '¿Activar acceso remoto?'"
                    @confirm="$inertia.put(route('users.toggle-home-office', user.id))">
                    <template #reference>
                        <button>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-4 mr-1"
                                :class="user.home_office ? 'text-[#F29513]' : 'text-grayCC'">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                            </svg>
                        </button>
                    </template>
                </el-popconfirm>
            </p>
            <p class="text-[#6D6E72]">Código de empleado:</p>
            <p>{{ user.code ?? "-" }}</p>
            <p class="text-[#6D6E72]">Estatus:</p>
            <p v-if="user.is_active" class="text-[#35AC11]">Activo</p>
            <p v-else class="text-[#cf3939]">Inactivo</p>
            <p class="text-[#6D6E72]">Fecha de ingreso:</p>
            <p>{{ user.org_props.entry_date
                ? formatDate(user.org_props.entry_date)
                : '-' }}</p>
            <p class="text-[#6D6E72]">Departamento:</p>
            <p>{{ user.org_props.department ?? '-' }}</p>
            <p class="text-[#6D6E72]">Puesto:</p>
            <p>{{ user.org_props.position ?? '-' }}</p>
            <p class="text-[#6D6E72]">Correo electrónico empresarial:</p>
            <p>{{ user.org_props.email ?? '-' }}</p>
            <p v-if="$page.props.auth.user.permissions.includes('Ver sueldos')" class="text-[#6D6E72]">Sueldo neto:</p>
            <p v-if="$page.props.auth.user.permissions.includes('Ver sueldos')">${{ user.org_props.net_salary ?
                parseFloat(user.org_props.net_salary).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")
                : '0.0' }}</p>
            <p v-if="$page.props.auth.user.permissions.includes('Ver sueldos')" class="text-[#6D6E72]">Complemento
                catorcenal:</p>
            <p v-if="$page.props.auth.user.permissions.includes('Ver sueldos')">${{ user.org_props.biweekly_complement ?
                parseFloat(user.org_props.biweekly_complement).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")
                : '0.0' }}</p>
            <p v-if="$page.props.auth.user.permissions.includes('Ver sueldos')" class="text-[#6D6E72]">Complemento
                mensual:</p>
            <p v-if="$page.props.auth.user.permissions.includes('Ver sueldos')">${{ user.org_props.biweekly_complement ?
                parseFloat(user.org_props.month_complement).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")
                : '0.0' }}</p>
            <p class="text-[#6D6E72]">Vacaciones:</p>
            <el-dropdown trigger="click">
                <button class="focus:border-0 focus:outline-none">
                    <p class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-4 mr-1 text-primary">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span class="text-black">{{ Math.floor(user.org_props.vacations) }} dias disponibles</span>
                    </p>
                </button>
                <template #dropdown>
                    <el-dropdown-menu>
                        <section class="mx-4 my-1 w-72">
                            <h1 class="font-bold text-secondary text-sm">Resumen de vacaciones</h1>
                            <h2 class="flex items-center space-x-2 mt-2">
                                <span class="text-gray37 font-semibold">Vacaciones disponibles</span>
                                <button v-if="!editVacations" @click="editVacations = true" type="button"
                                    class="text-primary underline">Modificar</button>
                            </h2>
                            <div v-if="editVacations" class="flex items-center space-x-3">
                                <el-input v-model="form.vacations" placeholder="No dejar vacio" size="small"
                                    class="!w-1/2" clearable
                                    :formatter="(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, '')"
                                    :parser="(value) => value.replace(/[^\d.]/g, '')">
                                    <template #append>
                                        <p>dias</p>
                                    </template>
                                </el-input>
                                <div class="flex items-center space-x-1">
                                    <el-tooltip content="Actualizar" placement="top">
                                        <button type="button" @click="updateVacations"
                                            class="flex items-center justify-center bg-primary text-white size-5 text-[10px] rounded-full"
                                            :disabled="form.processing">
                                            <i v-if="form.processing"
                                                class="fa-sharp fa-solid fa-circle-notch fa-spin text-white"></i>
                                            <i v-else class="fa-solid fa-check"></i>
                                        </button>
                                    </el-tooltip>
                                    <el-tooltip content="Cancelar" placement="top">
                                        <button type="button" @click="editVacations = false; form.reset()"
                                            class="flex items-center justify-center bg-grayED text-gray37 size-5 text-[10px] rounded-full"
                                            :disabled="form.processing"><i class="fa-solid fa-xmark"></i></button>
                                    </el-tooltip>
                                </div>
                            </div>
                            <p v-else>{{ Math.floor(user.org_props.vacations) }} dias</p>
                            <h2 class="text-gray37 font-semibold">Vacaciones tomadas</h2>
                            <el-tree :data="vacations" :props="defaultProps">
                                <template #default="{ node, data }">
                                    <div class="w-full flex items-center justify-between text-xs">
                                        <p :title="node.label">{{ node.label }}</p>
                                    </div>
                                </template>
                            </el-tree>
                        </section>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>
        </div>
    </section>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        const form = useForm({
            vacations: this.user.org_props.vacations,
        });

        return {
            form,
            defaultProps: {
                children: 'children',
                label: 'label',
            },
            editVacations: false,
        }
    },
    components: {

    },
    props: {
        user: Object,
        vacations: Array,
    },
    methods: {
        formatDate(dateString) {
            return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
        },
        updateVacations() {
            this.form.put(route('users.update-vacations', this.user.id), {
                onSuccess: () => {
                    this.$notify({
                        title: 'Correcto',
                        type: 'success',
                    });

                    this.editVacations = false;
                }
            });
        }
    }
}
</script>
