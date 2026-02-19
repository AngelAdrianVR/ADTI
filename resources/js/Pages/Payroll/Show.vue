<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import IncidencesTable from '@/Components/MyComponents/Payroll/IncidencesTable.vue';
import NoAttendanceCard from '@/Components/MyComponents/Payroll/NoAttendanceCard.vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    payroll: {
        type: Object,
        required: true
    },
    payrollUsers: {
        type: Array,
        default: () => []
    },
    noAttendances: {
        type: Array,
        default: () => []
    },
    adjacentPayrolls: {
        type: Object,
        default: () => ({ prev: null, next: null })
    }
});

// --- Loading State Global ---
const isLoading = ref(false);

// --- Scroll & Pagination State ---
const limit = ref(5); // Cantidad inicial de usuarios a mostrar
const loadMoreTrigger = ref(null); // Referencia al elemento DOM "centinela"
let observer = null;

onMounted(() => {
    // Escuchar eventos de navegación de Inertia
    const removeStartListener = router.on('start', () => {
        isLoading.value = true;
    });
    
    const removeFinishListener = router.on('finish', () => {
        isLoading.value = false;
    });

    // Configurar IntersectionObserver para Infinite Scroll
    observer = new IntersectionObserver((entries) => {
        const entry = entries[0];
        if (entry.isIntersecting) {
            loadMore();
        }
    }, {
        rootMargin: '120px', // Cargar más antes de llegar al fondo absoluto
    });

    // Observar el trigger si existe
    if (loadMoreTrigger.value) {
        observer.observe(loadMoreTrigger.value);
    }

    onUnmounted(() => {
        removeStartListener();
        removeFinishListener();
        if (observer) observer.disconnect();
    });
});

// --- Estado de Filtros (Client-Side) ---
const search = ref('');
const selectedDepartment = ref('');
const selectedUsers = ref([]);
const selectAll = ref(false);

// --- Estado Modal Comentarios ---
const showCommentModal = ref(false);
const commentForm = useForm({
    payroll_id: null,
    user_id: null,
    date: null,
    comments: '',
});
const editingUserName = ref('');
const editingDate = ref('');

// --- Computed: Departamentos Disponibles ---
const availableDepartments = computed(() => {
    const depts = props.payrollUsers
        .map(item => item.user.org_props?.department)
        .filter(dept => dept); 
    return [...new Set(depts)].sort();
});

// --- Computed: Usuarios Filtrados (Total coincidente) ---
const filteredPayrollUsers = computed(() => {
    if (!search.value && !selectedDepartment.value) {
        return props.payrollUsers;
    }
    const lowerSearch = search.value.toLowerCase();
    return props.payrollUsers.filter(item => {
        const userName = item.user.name?.toLowerCase() || '';
        const userCode = item.user.code?.toLowerCase() || '';
        const userDept = item.user.org_props?.department || '';
        const matchesSearch = !search.value || userName.includes(lowerSearch) || userCode.includes(lowerSearch);
        const matchesDept = !selectedDepartment.value || userDept === selectedDepartment.value;
        return matchesSearch && matchesDept;
    });
});

// --- Computed: Usuarios Visibles (Paginados) ---
// Esta es la lista que realmente se renderiza en el DOM
const visiblePayrollUsers = computed(() => {
    return filteredPayrollUsers.value.slice(0, limit.value);
});

// --- Lógica de Scroll Infinito ---
const loadMore = () => {
    if (limit.value < filteredPayrollUsers.value.length) {
        limit.value += 5; // Cargar 5 más
    }
};

// Reiniciar límite si cambian los filtros
watch([search, selectedDepartment], () => {
    limit.value = 5;
    selectedUsers.value = [];
    selectAll.value = false;
    // Re-observar el trigger después de que el DOM se actualice (por si desapareció)
    nextTick(() => {
        if (loadMoreTrigger.value && observer) {
            observer.disconnect();
            observer.observe(loadMoreTrigger.value);
        }
    });
});

// --- Lógica de Selección (Checkbox) ---
const toggleSelectAll = (val) => {
    if (val) {
        // Seleccionar TODOS los filtrados (incluso los no visibles aún)
        selectedUsers.value = filteredPayrollUsers.value.map(item => item.user.id);
    } else {
        selectedUsers.value = [];
    }
};

const handleSingleSelect = () => {
    if (filteredPayrollUsers.value.length > 0 && selectedUsers.value.length === filteredPayrollUsers.value.length) {
        selectAll.value = true;
    } else {
        selectAll.value = false;
    }
};

// --- Acciones ---
const openTemplate = () => {
    const params = {};
    if (selectedUsers.value.length > 0) {
        params.user_ids = selectedUsers.value;
    }
    const url = route('payrolls.pre-payroll', { 
        payroll: props.payroll.id,
        ...params 
    });
    window.open(url, '_blank');
};

// --- Manejo de Comentarios ---
const openCommentModal = (data) => {
    commentForm.payroll_id = props.payroll.id;
    commentForm.user_id = data.userId;
    commentForm.date = data.date;
    commentForm.comments = data.comments || '';
    
    editingUserName.value = data.userName;
    editingDate.value = data.date;
    showCommentModal.value = true;
};

const saveComment = () => {
    commentForm.post(route('payroll-comments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCommentModal.value = false;
            ElMessage.success('Comentario guardado exitosamente');
        },
        onError: () => {
             ElMessage.error('No se pudo guardar el comentario');
        }
    });
};
</script>

<template>
    <AppLayout :title="`Nómina #${payroll.id}`">
        <!-- Overlay de carga global -->
        <div v-if="isLoading" class="fixed inset-0 bg-white/80 z-[9999] flex flex-col items-center justify-center backdrop-blur-sm transition-opacity duration-300">
            <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-indigo-600 mb-4"></div>
            <p class="text-lg font-semibold text-gray-700">Cargando información de nómina...</p>
            <p class="text-sm text-gray-500 max-w-md text-center mt-2">
                Procesando registros de asistencia. Por favor espere.
            </p>
        </div>

        <main class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8 relative">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header con Navegación -->
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <Back :route="route('payrolls.index')" class="mr-4" />
                    <div class="flex items-center w-full md:w-auto">
                        
                        <div class="flex items-center gap-4">
                            <Link 
                                v-if="adjacentPayrolls.prev" 
                                :href="route('payrolls.show', adjacentPayrolls.prev)"
                                class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-indigo-600 transition-all shadow-sm"
                            >
                                <i class="fa-solid fa-chevron-left"></i>
                            </Link>
                            <span v-else class="w-8 h-8 flex items-center justify-center rounded-full border border-gray-100 text-gray-200 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left"></i>
                            </span>

                            <div>
                                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                                    Nómina #{{ payroll.id }}
                                    <span v-if="!payroll.is_active" class="text-[10px] bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full uppercase tracking-wide border border-gray-300">Cerrada</span>
                                </h1>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Catorcena {{ payroll.biweekly }} | Inicio: {{ payroll.start_date.split('T')[0] }}
                                </p>
                            </div>

                            <Link 
                                v-if="adjacentPayrolls.next" 
                                :href="route('payrolls.show', adjacentPayrolls.next)"
                                class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-indigo-600 transition-all shadow-sm"
                            >
                                <i class="fa-solid fa-chevron-right"></i>
                            </Link>
                            <span v-else class="w-8 h-8 flex items-center justify-center rounded-full border border-gray-100 text-gray-200 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-right"></i>
                            </span>
                        </div>
                    </div>

                    <div class="w-full md:w-auto flex justify-end">
                        <PrimaryButton 
                            v-if="$page.props.auth.user.permissions.includes('Ver pre-nominas')"
                            @click="openTemplate"
                            class="!bg-indigo-600 hover:!bg-indigo-700 w-full md:w-auto justify-center"
                        >
                            <i class="fa-solid fa-file-invoice-dollar mr-2"></i> 
                            {{ selectedUsers.length > 0 ? `Generar (${selectedUsers.length})` : 'Generar Pre-nómina' }}
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Barra de Filtros -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    <div class="md:col-span-5">
                        <el-input v-model="search" placeholder="Buscar por nombre o código..." clearable size="large">
                            <template #prefix><i class="fa-solid fa-magnifying-glass text-gray-400"></i></template>
                        </el-input>
                    </div>
                    <div class="md:col-span-4">
                        <el-select v-model="selectedDepartment" placeholder="Todos los departamentos" clearable filterable size="large" class="w-full">
                            <el-option v-for="dept in availableDepartments" :key="dept" :label="dept" :value="dept" />
                        </el-select>
                    </div>
                    <div class="md:col-span-3 text-right text-xs text-gray-500">
                        Mostrando <span class="font-bold text-gray-800 text-base">{{ visiblePayrollUsers.length }}</span> de {{ filteredPayrollUsers.length }} (Total: {{ payrollUsers.length }})
                    </div>
                </div>

                <!-- Sección: Asistencias (Lista Infinita) -->
                <section v-if="payrollUsers.length" class="space-y-4 mb-12">
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg border border-gray-200/60 sticky top-0 z-10 backdrop-blur-sm bg-gray-50/90">
                        <div class="flex items-center gap-3">
                             <el-checkbox v-model="selectAll" @change="toggleSelectAll" :label="`Seleccionar todos los resultados (${filteredPayrollUsers.length})`" size="large" />
                        </div>
                    </div>
                    
                    <div v-if="filteredPayrollUsers.length > 0" class="space-y-3">
                        <!-- Iteramos sobre visiblePayrollUsers (Paginado) en lugar de todo el array -->
                        <div 
                            v-for="(item, index) in visiblePayrollUsers" 
                            :key="item.user.id" 
                            class="flex items-start gap-3 transition-all duration-300 animate-in fade-in slide-in-from-bottom-2"
                        >
                            <div class="pt-4 pl-2">
                                <el-checkbox :value="item.user.id" v-model="selectedUsers" @change="handleSingleSelect" size="large" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <IncidencesTable 
                                    :payrollUser="item"
                                    :payroll="payroll" 
                                    @edit-comment="openCommentModal"
                                />
                            </div>
                        </div>

                        <!-- Elemento "Centinela" para el Scroll Infinito -->
                        <div ref="loadMoreTrigger" class="py-6 text-center text-gray-400 text-xs h-16 flex items-center justify-center">
                            <span v-if="limit < filteredPayrollUsers.length" class="flex items-center gap-2">
                                <i class="fa-solid fa-circle-notch animate-spin text-indigo-500"></i> 
                                Cargando más colaboradores...
                            </span>
                            <span v-else class="text-gray-300 italic">
                                — Fin de los resultados —
                            </span>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
                        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                            <i class="fa-solid fa-user-slash text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No se encontraron colaboradores</p>
                        <button @click="search = ''; selectedDepartment = ''" class="mt-4 text-indigo-600 hover:text-indigo-700 text-sm font-semibold underline">Limpiar filtros</button>
                    </div>
                </section>

                <section v-if="noAttendances.length" class="space-y-4 mt-8">
                    <div class="flex items-center gap-2 px-2 text-amber-600">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <h2 class="text-sm font-bold uppercase tracking-wider">Sin registros de asistencia ({{ noAttendances.length }})</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <NoAttendanceCard v-for="(item, index) in noAttendances" :user="item" :payroll="payroll" :key="item.id" />
                    </div>
                </section>
            </div>

            <!-- Modal para Comentarios -->
            <el-dialog
                v-model="showCommentModal"
                :title="`Comentario para ${editingUserName}`"
                width="500px"
                class="!rounded-xl"
            >
                <div class="space-y-4">
                    <div class="flex items-center gap-2 text-sm text-gray-500 bg-gray-50 p-2 rounded border border-gray-100">
                        <i class="fa-regular fa-calendar"></i>
                        <span>Incidencia del día: <strong>{{ editingDate }}</strong></span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Comentario / Justificación</label>
                        <el-input
                            v-model="commentForm.comments"
                            type="textarea"
                            :rows="4"
                            placeholder="Escribe aquí los detalles..."
                        />
                        <p class="text-xs text-gray-400 mt-1 text-right">{{ commentForm.comments.length }}/1200</p>
                    </div>
                </div>

                <template #footer>
                    <div class="flex justify-end gap-2">
                        <el-button @click="showCommentModal = false">Cancelar</el-button>
                        <el-button 
                            type="primary" 
                            @click="saveComment" 
                            :loading="commentForm.processing"
                            class="!bg-indigo-600 !border-indigo-600"
                        >
                            Guardar Comentario
                        </el-button>
                    </div>
                </template>
            </el-dialog>

        </main>
    </AppLayout>
</template>