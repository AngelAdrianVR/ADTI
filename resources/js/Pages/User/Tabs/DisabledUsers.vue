<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElNotification } from "element-plus";
import axios from 'axios';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    users: Array,
});

// State
const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const selectedItems = ref([]);

// Computed
const filteredUsers = computed(() => {
    if (!search.value) return props.users;
    const lowerSearch = search.value.toLowerCase();
    
    return props.users.filter(user => 
        user.name?.toLowerCase().includes(lowerSearch) ||
        user.email?.toLowerCase().includes(lowerSearch)
    );
});

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredUsers.value.slice(start, end);
});

// Methods
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM, yyyy', { locale: es });
};

const handleSelectionChange = (val) => {
    selectedItems.value = val;
};

const handleRowClick = (row) => {
    router.visit(route('users.show', row.id)); // Opcional si se quiere ver detalle de inactivos
    // router.visit(route('users.reactivation', row.id)); // Si se quiere ir directo a reactivar
};

const handlePageChange = (val) => {
    currentPage.value = val;
};

const deleteSelections = async () => {
    try {
        const items_ids = selectedItems.value.map(item => item.id);
        const response = await axios.post(route('users.massive-delete'), { items_ids });

        if (response.status === 200) {
            ElNotification.success({ title: 'Éxito', message: 'Usuarios eliminados permanentemente' });
            router.reload();
        }
    } catch (err) {
        ElNotification.error({ title: 'Error', message: 'No se pudo completar la solicitud' });
    }
};

const reactivateUser = (id) => {
    router.visit(route('users.reactivation', id));
};
</script>

<template>
    <div class="px-2">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
            <div class="relative w-full sm:w-72">
                <input 
                    v-model="search" 
                    type="text" 
                    placeholder="Buscar usuario inactivo..." 
                    class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 focus:border-red-400 focus:ring-red-400 text-sm shadow-sm"
                >
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-sm"></i>
            </div>

            <div class="flex items-center gap-2" v-if="selectedItems.length > 0">
                <span class="text-xs text-gray-500 mr-2">{{ selectedItems.length }} seleccionados</span>
                <el-popconfirm 
                    v-if="$page.props.auth.user.permissions?.includes('Eliminar usuarios')"
                    confirm-button-text="Sí, borrar" 
                    cancel-button-text="No" 
                    icon-color="#DC2626" 
                    title="¿Eliminar permanentemente?"
                    @confirm="deleteSelections"
                >
                    <template #reference>
                        <button class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-md text-xs font-medium transition-colors border border-red-200">
                            <i class="fa-solid fa-trash mr-1"></i> Borrar Definitivo
                        </button>
                    </template>
                </el-popconfirm>
            </div>
        </div>

        <!-- Tabla -->
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <el-table 
                :data="paginatedUsers" 
                @selection-change="handleSelectionChange"
                @row-click="handleRowClick"
                style="width: 100%"
                class="cursor-pointer"
                :row-class-name="'hover:bg-gray-50 transition-colors opacity-75'"
            >
                <el-table-column v-if="$page.props.auth.user.permissions?.includes('Eliminar usuarios')" type="selection" width="40" />
                
                <el-table-column label="Usuario" min-width="200">
                    <template #default="scope">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <img :src="scope.row.profile_photo_url" class="h-9 w-9 rounded-full object-cover border border-gray-200 grayscale" alt="">
                                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full"></span>
                            </div>
                            <div>
                                <p class="font-bold text-gray-700 text-sm leading-tight">{{ scope.row.name }}</p>
                                <p class="text-xs text-gray-400">{{ scope.row.email }}</p>
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Fecha Baja" width="150">
                    <template #default="scope">
                        <span class="text-xs text-red-500 font-medium bg-red-50 px-2 py-1 rounded">
                            {{ formatDate(scope.row.inactivate_date) }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column label="Motivo" min-width="200">
                    <template #default="scope">
                        <p class="text-xs text-gray-500 truncate" :title="scope.row.inactivate_reason">
                            {{ scope.row.inactivate_reason || 'Sin motivo registrado' }}
                        </p>
                    </template>
                </el-table-column>

                <el-table-column align="right" width="120">
                    <template #default="scope">
                        <button 
                            @click.stop="reactivateUser(scope.row.id)" 
                            class="text-[#1676A2] hover:underline text-xs font-bold"
                        >
                            Reactivar
                        </button>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-end mt-4">
            <el-pagination 
                layout="prev, pager, next" 
                :total="filteredUsers.length" 
                :page-size="itemsPerPage"
                @current-change="handlePageChange"
                background
            />
        </div>
    </div>
</template>