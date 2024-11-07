<template>
    <AppLayout title="Días festivos">
        <main class="px-2 md:px-10 pt-1 pb-16">
            <h1 class="font-bold my-3 ml-4 text-lg">Días festivos</h1>

            <section class="md:flex justify-between items-center">
                <article class="flex items-center space-x-5 lg:w-1/3">
                    <div class="mb-3 md:mb-0 w-full relative">
                        <input v-model="searchQuery" @keydown.enter="handleSearch" class="input w-full pl-9"
                            placeholder="Buscar día" type="search"
                            ref="searchInput" />
                        <i class="fa-solid fa-magnifying-glass text-xs text-gray99 absolute top-[10px] left-4"></i>
                    </div>
                    <el-tag @close="closedTag" v-if="searchedWord" closable type="primary">
                        {{ searchedWord }}
                    </el-tag>
                </article>
                
                <div class="my-4 lg:my-0 flex items-center justify-end space-x-3">
                    <PrimaryButton v-if="$page.props.auth.user.permissions?.includes('Crear dias festivos')" 
                        @click="editFlag = false; showModal = true;">Agregar día festivo</PrimaryButton>
                </div>
            </section>

            <!-- tabla -->
            <div class="lg:w-5/6 mx-auto mt-6">
                <div class="flex justify-between">
                    <!-- pagination -->
                    <div>
                        <el-pagination @current-change="handlePagination" layout="prev, pager, next"
                            :total="holidays.length" />
                    </div>

                    <!-- buttons -->
                    <div>
                        <el-popconfirm confirm-button-text="Si" cancel-button-text="No" icon-color="#0355B5"
                            title="¿Continuar?" @confirm="deleteSelections">
                            <template #reference>
                                <el-button type="danger" plain class="mb-3"
                                    :disabled="disableMassiveActions">Eliminar</el-button>
                            </template>
                        </el-popconfirm>
                    </div>
                </div>
                <el-table :data="filteredTableData" @row-click="handleRowClick" max-height="670" style="width: 100%"
                    @selection-change="handleSelectionChange" ref="multipleTableRef" :row-class-name="tableRowClassName">
                    <el-table-column type="selection" width="45" />
                    <el-table-column prop="id" label="ID" width="70" />
                    <el-table-column label="Nombre">
                        <template #default="scope">
                            <div class="flex items-center space-x-1">
                                <el-tooltip v-if="!scope.row.is_active" content="Deshabilitado" placement="left" effect="dark">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <mask id="mask0_14872_40" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="14" height="14">
                                            <rect width="14" height="14" fill="#D9D9D9"/>
                                        </mask>
                                        <g mask="url(#mask0_14872_40)">
                                            <path d="M11.538 13.1973L10.2109 11.8702C9.73455 12.1813 9.22656 12.4195 8.68698 12.5848C8.1474 12.75 7.58594 12.8327 7.0026 12.8327C6.19566 12.8327 5.43733 12.6796 4.7276 12.3733C4.01788 12.0671 3.40052 11.6514 2.87552 11.1264C2.35052 10.6014 1.9349 9.98407 1.62865 9.27435C1.3224 8.56463 1.16927 7.80629 1.16927 6.99935C1.16927 6.41602 1.25191 5.85456 1.41719 5.31497C1.58247 4.77539 1.82066 4.2674 2.13177 3.79102L0.804688 2.46393L1.63594 1.63268L12.3693 12.366L11.538 13.1973ZM7.0026 11.666C7.42066 11.666 7.82656 11.6125 8.22031 11.5056C8.61406 11.3987 8.99566 11.2382 9.3651 11.0243L2.9776 4.63685C2.76372 5.00629 2.6033 5.38789 2.49635 5.78164C2.38941 6.17539 2.33594 6.58129 2.33594 6.99935C2.33594 8.2924 2.79045 9.39345 3.69948 10.3025C4.60851 11.2115 5.70955 11.666 7.0026 11.666ZM11.8734 10.2077L11.0276 9.36185C11.2415 8.9924 11.4019 8.61081 11.5089 8.21706C11.6158 7.82331 11.6693 7.4174 11.6693 6.99935C11.6693 5.70629 11.2148 4.60525 10.3057 3.69622C9.3967 2.7872 8.29566 2.33268 7.0026 2.33268C6.58455 2.33268 6.17865 2.38615 5.7849 2.4931C5.39115 2.60004 5.00955 2.76046 4.6401 2.97435L3.79427 2.12852C4.27066 1.8174 4.77865 1.57921 5.31823 1.41393C5.85781 1.24865 6.41927 1.16602 7.0026 1.16602C7.80955 1.16602 8.56788 1.31914 9.2776 1.62539C9.98733 1.93164 10.6047 2.34727 11.1297 2.87227C11.6547 3.39727 12.0703 4.01463 12.3766 4.72435C12.6828 5.43407 12.8359 6.1924 12.8359 6.99935C12.8359 7.58268 12.7533 8.14414 12.588 8.68372C12.4227 9.22331 12.1845 9.73129 11.8734 10.2077Z" fill="#D90537"/>
                                        </g>
                                    </svg>
                                </el-tooltip>
                                <span :class="{'ml-[18px]': scope.row.is_active }">{{ scope.row.name }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="Fecha">
                        <template #default="scope">
                            <p v-if="scope.row.is_custom_date">{{ scope.row.ordinal + ' ' + scope.row.week_day + ' de ' + scope.row.month }}</p>
                            <p v-else>{{ formatDate(scope.row.date) ?? '-' }}</p>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <!-- tabla -->

            <!-- -------------- Modal Create starts----------------------- -->
            <DialogModal :show="showModal" @close="showModal = false">
                <template #title>
                    <p v-if="editFlag">Editar Día festivo "{{ itemClicked.name }}"</p>
                    <p v-else>Crear día festivo</p>
                </template>
                <template #content>
                    <form @submit.prevent="editFlag ? update() : store()">
                        <div>
                            <InputLabel value="Nombre del día*" class="ml-3 mb-1" />
                            <el-input v-model="form.name" placeholder="Ej. Navidad" clearable />
                            <InputError :message="form.errors.name" />
                        </div>

                        <el-checkbox @change="resetForm()" class="my-2" v-model="form.is_custom_date" label="Fecha personalizada" size="small" />

                        <div v-if="!form.is_custom_date" class="md:flex items-center md:space-x-3 space-y-3 md:space-y-0 mt-2">
                            <div class="w-full">
                                <InputLabel value="Día*" class="ml-3 mb-1" />
                                <el-select v-model="form.day" :teleported="false" clearable placeholder="Selecciona el dia">
                                    <el-option v-for="item in 31" :key="item" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.day" />
                            </div>

                            <div class="w-full">
                                <InputLabel value="Mes*" class="ml-3 mb-1" />
                                <el-select v-model="form.month" :teleported="false" clearable placeholder="Selecciona el mes">
                                    <el-option v-for="(item, index) in months" :key="index" :label="item.label"
                                        :value="item.value" />
                                </el-select>
                                <InputError :message="form.errors.month" />
                            </div>
                        </div>

                        <div v-else class="md:flex items-center md:space-x-3 space-y-3 md:space-y-0 mt-2">
                            <div class="w-full">
                                <InputLabel value="Ordinal*" class="ml-3 mb-1" />
                                <el-select v-model="form.ordinal" :teleported="false" clearable placeholder="Selecciona el dia">
                                    <el-option v-for="item in Ordinals" :key="item" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.ordinal" />
                            </div>

                            <div class="w-full">
                                <InputLabel value="Día de la semana*" class="ml-3 mb-1" />
                                <el-select v-model="form.week_day" :teleported="false" clearable placeholder="Selecciona el dia">
                                    <el-option v-for="item in weekDays" :key="item" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.week_day" />
                            </div>

                            <div class="w-full">
                                <InputLabel value="Mes*" class="ml-3 mb-1" />
                                <el-select v-model="form.month" :teleported="false" clearable placeholder="Selecciona el mes">
                                    <el-option v-for="(item, index) in months" :key="index" :label="item.label"
                                        :value="item.label" />
                                </el-select>
                                <InputError :message="form.errors.month" />
                            </div>
                        </div>
                    </form>
                </template>
                <template #footer>
                    <div class="w-full flex justify-between">
                        <el-switch v-model="form.is_active" inline-prompt size="medium"
                            style="--el-switch-on-color: #1676A2; --el-switch-off-color: #CCCCCC" active-text="Activo"
                            inactive-text="Inactivo" />

                        <div class="flex items-center space-x-3">
                            <CancelButton @click="showModal = false; form.reset(); editFlag = false;" :disabled="form.processing">
                                Cancelar
                            </CancelButton>

                            <PrimaryButton @click="editFlag ? update() : store()" :disabled="form.processing">
                                <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                                {{ editFlag ? 'Actualizar' : 'Crear' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </template>
            </DialogModal>
            <!-- --------------------------- Modal Create ends ------------------------------------ -->
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import Loading from "@/Components/MyComponents/Loading.vue";
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import DialogModal from "@/Components/DialogModal.vue";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { useForm, Link } from '@inertiajs/vue3';

export default {
data() {
    const form = useForm({
        name: null,
        day: null,
        month: null,
        ordinal: null,
        week_day: null,
        is_active: true,
        is_custom_date: false,
    });

    return {
        // buscador
        search: '',
        searchQuery: null,
        searchedWord: null,

        // tabla
        disableMassiveActions: true,
        loading: false,
        inputSearch: '',
        search: null,

        // pagination
        // totalPagination: null, //el componente toma 10 items por pagina pero aqui le pusimos 30, por eso se divide entre 3
        itemsPerPage: 30,
        start: 0,
        end: 30,

        //general
        form,
        showModal: false,
        editFlag: false,
        showModal: false,
        itemClicked: null,
        months: [
            { label: "Enero", value: "01" },
            { label: "Febrero", value: "02" },
            { label: "Marzo", value: "03" },
            { label: "Abril", value: "04" },
            { label: "Mayo", value: "05" },
            { label: "Junio", value: "06" },
            { label: "Julio", value: "07" },
            { label: "Agosto", value: "08" },
            { label: "Septiembre", value: "09" },
            { label: "Octubre", value: "10" },
            { label: "Noviembre", value: "11" },
            { label: "Diciembre", value: "12" },
        ],
        weekDays: [
            'Lunes',
            'Martes',
            'Miércoles',
            'Jueves',
            'Viernes',
            'Sábado',
            'Domingo',
        ],
        Ordinals: [
            'Primer',
            'Segundo',
            'Tercer',
            'Cuarto',
            'Quinto',
        ],
    }
},
components:{
    PrimaryButton,
    CancelButton,
    DialogModal,
    InputError,
    InputLabel,
    AppLayout,
    Loading,
    Link,
},
props:{
    holidays: Array
},
methods:{
    update() {
        this.form.put(route('holidays.update', this.itemClicked), {
            onSuccess: () => {
                this.$notify({
                    title: 'Correcto',
                    message: '',
                    type: 'success'
                });
                this.form.reset();
                this.showModal = false;
            }
        });
    },
    store() {
        this.form.post(route('holidays.store'), {
            onSuccess: () => {
                this.$notify({
                    title: 'Correcto',
                    message: '',
                    type: 'success'
                });
                this.form.reset();
                this.showModal = false;
            }
        });
    },
    formatDate(date) {
        if ( date ) {
            const parsedDate = new Date(date);
            return format(parsedDate, 'dd MMMM', { locale: es }); // Formato personalizado
        }
    },
    handleSearch() {
        this.search = this.searchQuery;
        this.searchedWord = this.searchQuery;
        this.searchQuery = null;
    },
    closedTag() {
        this.search = null
        this.searchedWord = null;
    },
    resetForm() {
        this.form.day = null;
        this.form.month = null;
        this.form.ordinal = null;
        this.form.week_day = null;
    },
    handlePagination(val) {
        this.start = (val - 1) * this.itemsPerPage;
        this.end = val * this.itemsPerPage;
    },
    async deleteSelections() {
        try {
            const response = await axios.post(route('holidays.massive-delete', {
                holidays: this.$refs.multipleTableRef.value
            }));

            if (response.status == 200) {
                this.$notify({
                    title: 'Éxito',
                    message: response.data.message,
                    type: 'success'
                });

                // update list of quotes
                let deletedIndexes = [];
                this.holidays.forEach((holiday, index) => {
                    if (this.$refs.multipleTableRef.value.includes(holiday)) {
                        deletedIndexes.push(index);
                    }
                });

                // Ordenar los índices de forma descendente para evitar problemas de desplazamiento al eliminar elementos
                deletedIndexes.sort((a, b) => b - a);

                // Eliminar cotizaciones por índice
                for (const index of deletedIndexes) {
                    this.holidays.splice(index, 1);
                }

            } else {
                this.$notify({
                    title: 'Algo salió mal',
                    message: response.data.message,
                    type: 'error'
                });
            }

        } catch (err) {
            this.$notify({
                title: 'Algo salió mal',
                message: err.message,
                type: 'error'
            });
            console.log(err);
        }
    },
    handleRowClick(row) {
        this.itemClicked = row;
        console.log(this.itemClicked);
        this.editFlag = true;
        this.showModal = true;

        this.form.name = row.name;
        this.form.day = this.itemClicked.is_custom_date ? null : parseInt(row.date.split('-')[2].split('T')[0]);
        this.form.ordinal = row.ordinal;
        this.form.week_day = row.week_day;
        this.form.month = this.itemClicked.is_custom_date ? row.month : row.date.split('-')[1];
        this.form.is_active = Boolean(row.is_active);
        this.form.is_custom_date = Boolean(row.is_custom_date);
    },
    handleSelectionChange(val) {
        this.$refs.multipleTableRef.value = val;

        if (!this.$refs.multipleTableRef.value.length) {
            this.disableMassiveActions = true;
        } else {
            this.disableMassiveActions = false;
        }
    },
    tableRowClassName({ row, rowIndex }) {
        let classes = 'cursor-pointer';
        return classes;
    },
},
computed: {
    filteredTableData() {
        if (!this.search) {
            return this.holidays.filter((item, index) => index >= this.start && index < this.end);
        } else {
            return this.holidays.filter(
                (holiday) =>
                    holiday.name.toLowerCase().includes(this.search.toLowerCase()) ||
                    holiday.id.toString().toLowerCase().includes(this.search.toLowerCase())
            )
        }
    }
},
}
</script>