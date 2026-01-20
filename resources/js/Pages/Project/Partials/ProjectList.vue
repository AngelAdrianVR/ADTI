<script setup>
import { ref, computed } from 'vue';
import {
    MoreFilled,
    View,
    Delete,
    EditPen,
    VideoPlay,
    CircleCheck,
    OfficeBuilding
} from '@element-plus/icons-vue';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    projects: Array,
    activeEntry: Object, // Para saber qué botón mostrar (Play/Stop)
    canEdit: Boolean,
    canDelete: Boolean
});

// Definimos los eventos que este componente puede disparar hacia el padre (Index.vue)
const emit = defineEmits(['edit', 'delete', 'view', 'start', 'stop']);

// --- Estado de Paginación Local ---
const currentPage = ref(1);
const itemsPerPage = ref(10);

const paginatedProjects = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return props.projects.slice(start, end);
});

// --- Helpers ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return format(parseISO(dateString), 'dd MMM, yyyy', { locale: es });
};

const getProgress = (project) => {
    if (!project.budgeted_hours || project.budgeted_hours == 0) return 0;
    const percent = (project.consumed_hours / project.budgeted_hours) * 100;
    return Math.min(100, Math.round(percent));
};

const getProgressColor = (percent) => {
    if (percent >= 100) return '#F56C6C';
    if (percent > 80) return '#E6A23C';
    return '#1676A2';
};

const handlePageChange = (val) => {
    currentPage.value = val;
};
</script>

<template>
    <div class="px-2 pb-4">
        <el-table :data="paginatedProjects" @row-click="(row) => emit('view', row)" style="width: 100%"
            class="cursor-pointer" :row-class-name="'hover:bg-gray-50 transition-colors'">
            <!-- Columna: Proyecto/Cliente -->
            <el-table-column label="Proyecto" min-width="240">
                <template #default="scope">
                    <div>
                        <p class="font-bold text-gray-800 text-sm leading-tight mb-1">{{ scope.row.name }}</p>
                        <div class="flex items-center text-xs text-gray-500">
                            <el-icon class="mr-1">
                                <OfficeBuilding />
                            </el-icon>
                            {{ scope.row.client }}
                        </div>
                    </div>
                </template>
            </el-table-column>

            <!-- Columna: Progreso -->
            <el-table-column label="Presupuesto vs Real" min-width="200">
                <template #default="scope">
                    <div class="pr-4">
                        <div class="flex justify-between text-[10px] mb-1 text-gray-500 font-mono">
                            <span>{{ scope.row.consumed_hours }}h cons.</span>
                            <span>{{ scope.row.budgeted_hours }}h total</span>
                        </div>
                        <el-progress :percentage="getProgress(scope.row)" :color="getProgressColor" :show-text="false"
                            :stroke-width="8" />
                    </div>
                </template>
            </el-table-column>

            <!-- Columna: Fechas -->
            <el-table-column label="Fechas" min-width="170">
                <template #default="scope">
                    <div class="text-xs text-gray-600 space-y-1">
                        <div class="flex items-center">
                            <span class="w-12 text-gray-400">Inicio:</span>
                            <span class="font-medium">{{ formatDate(scope.row.start_date) }}</span>
                        </div>
                        <div class="flex items-center" v-if="scope.row.estimated_end_date">
                            <span class="w-12 text-gray-400">Fin Est:</span>
                            <span>{{ formatDate(scope.row.estimated_end_date) }}</span>
                        </div>
                    </div>
                </template>
            </el-table-column>

            <!-- Acciones -->
            <el-table-column fixed="right" label="Acciones" width="160" align="right">
                <template #default="scope">
                    <div class="flex items-center justify-end gap-2" @click.stop>

                        <!-- Botón Play/Stop -->
                        <el-tooltip v-if="scope.row.status === 'active'"
                            :content="activeEntry?.project_id === scope.row.id ? 'Detener Trabajo' : 'Iniciar Trabajo'"
                            placement="top">
                            <!-- Si este proyecto es el activo, botón ROJO de STOP -->
                            <el-button v-if="activeEntry?.project_id === scope.row.id" type="danger" circle size="small"
                                @click="emit('stop', scope.row)">
                                <el-icon>
                                    <CircleCheck />
                                </el-icon>
                            </el-button>

                            <!-- Si no, botón VERDE de START -->
                            <el-button v-else type="success" circle size="small" @click="emit('start', scope.row)">
                                <el-icon>
                                    <VideoPlay />
                                </el-icon>
                            </el-button>
                        </el-tooltip>

                        <!-- Menú Extra -->
                        <el-dropdown trigger="click">
                            <el-button text circle size="small">
                                <el-icon class="rotate-90">
                                    <MoreFilled />
                                </el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item @click="emit('view', scope.row)">
                                        <el-icon>
                                            <View />
                                        </el-icon> Ver Detalles
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="canEdit" @click="emit('edit', scope.row)">
                                        <el-icon>
                                            <EditPen />
                                        </el-icon> Editar
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="canDelete" divided class="text-red-500"
                                        @click="emit('delete', scope.row)">
                                        <el-icon>
                                            <Delete />
                                        </el-icon> Eliminar
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                </template>
            </el-table-column>
        </el-table>

        <!-- Paginación -->
        <div class="flex justify-end mt-4 px-2">
            <el-pagination layout="prev, pager, next" :total="projects.length" :page-size="itemsPerPage"
                @current-change="handlePageChange" background />
        </div>
    </div>
</template>