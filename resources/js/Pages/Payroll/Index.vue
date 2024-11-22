<template>
    <AppLayout title="Incidencias">
        <div class="mx-2 lg:mx-10 mt-6">
            <h1 class="font-bold my-3 ml-4 text-lg">Incidencias</h1>
            <section>
                <div class="lg:flex justify-between ml-16">
                    <!-- pagination -->
                    <el-pagination @current-change="handlePagination" layout="prev, pager, next"
                        :total="payrolls.length" hide-on-single-page />
                </div>
                <el-table :data="payrolls" @row-click="handleRowClick" max-height="670" style="width: 90%"
                    class="mx-auto mt-2" ref="multipleTableRef" :row-class-name="tableRowClassName">
                    <el-table-column prop="id" label="ID" width="80" />
                    <el-table-column prop="biweekly" label="Catorcena"  width="120" />
                    <el-table-column prop="start_date" label="Periodo">
                        <template #default="scope">
                            <p>
                                {{ formatDate(scope.row.start_date) }} - {{ getEndPeriod(scope.row.start_date) }}
                            </p>
                        </template>
                    </el-table-column>
                    <el-table-column align="right">
                        <template #default="scope">
                            <el-dropdown trigger="click" @command="handleCommand">
                                <button @click.stop
                                    class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-primary hover:bg-grayED transition-all duration-200 ease-in-out">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item :command="'show-' + scope.row.id">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            Ver
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>
                </el-table>
            </section>
        </div>
    </AppLayout>
</template>

<script>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format, parseISO, addDays, add } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
    data() {
        return {
            // buscador
            searchQuery: null,
            searchedWord: null,
            search: null,
            // pagination
            itemsPerPage: 10,
            start: 0,
            end: 10,
        }
    },
    components: {
        AppLayout,
        PrimaryButton,
    },
    props: {
        payrolls: Array
    },
    methods: {
        getEndPeriod(start) {
            const end = addDays(start, 13);

            return format(add(end, { hours: 6 }), 'dd MMMM, yyyy', { locale: es });
        },
        formatDate(dateString) {
            const date = parseISO(dateString);
            return format(add(date, { hours: 6 }), 'dd MMMM, yyyy', { locale: es });
        },
        handleSearch() {
            this.search = this.searchQuery;
        },
        handlePagination(val) {
            this.start = (val - 1) * this.itemsPerPage;
            this.end = val * this.itemsPerPage;
        },
        tableRowClassName({ row, rowIndex }) {
            return 'cursor-pointer text-xs';
        },
        handleRowClick(row) {
            this.$inertia.visit(route('payrolls.show', row));
        },
        handleCommand(command) {
            const commandName = command.split('-')[0];
            const rowId = command.split('-')[1];

            if (commandName === 'inactivate') {
                this.showInactivatigModal = true;
                this.inactivateUserId = rowId;
            } else {
                this.$inertia.get(route('payrolls.' + commandName, rowId));
            }

        },
    }
}
</script>
