<script setup>
import { format, isSameDay, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    day: { type: Object, required: true },
    canEdit: { type: Boolean, default: true },
    canManageIncidence: { type: Function, required: true },
    getIncidencePermission: { type: Function, required: true },
    getDayApprovalSummary: { type: Function, required: true },
    getApprovalStatusBadge: { type: Function, required: true },
    isValidLocation: { type: Function, required: true },
    getLocationError: { type: Function, required: true },
    incidences: { type: Array, required: true },
    projects: { type: Array, default: () => [] },
    canSeeMoney: { type: Boolean, default: false },
});

const emit = defineEmits(['command']);

const formatDate = (date) => format(new Date(date), 'dd MMM', { locale: es });
const getDayName = (date) => format(new Date(date), 'EEEE', { locale: es });

// ─── Formateador de dinero con separadores de miles ───
const formatMoney = (value) => {
    if (value === null || value === undefined || value === 0) return '0.00';
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getIncidenceColor = (incidence) => {
    if (!incidence) return 'bg-white';
    if (incidence.break_start && !incidence.break_end) return 'bg-orange-50 border-orange-300';
    if (incidence.check_in && incidence.check_out) return 'bg-green-50 border-green-200';
    if (incidence.incidence === 'Falta injustificada') return 'bg-red-50 border-red-200';
    if (incidence.incidence === 'Vacaciones') return 'bg-blue-50 border-blue-200';
    if (incidence.incidence === 'Descanso') return 'bg-gray-50 border-gray-200';
    if (incidence.incidence === 'Salió de Viaje') return 'bg-purple-50 border-purple-200';
    return 'bg-amber-50 border-amber-200';
};

const handleCommand = (cmd) => emit('command', cmd);
</script>

<template>
    <div class="flex flex-col w-48 border rounded-lg bg-white overflow-hidden shadow-sm transition-all hover:shadow-md relative"
         :class="getIncidenceColor(day)">
        <!-- Indicador de Comentario -->
        <div v-if="day.comment" class="absolute top-1 left-1 z-10">
            <el-tooltip :content="day.comment.comments" placement="top" effect="dark">
                <i class="fa-solid fa-comment-dots text-indigo-500 text-xs drop-shadow-sm"></i>
            </el-tooltip>
        </div>

        <!-- Fecha Header -->
        <div class="text-center py-1.5 text-[10px] uppercase font-bold tracking-wider border-b border-gray-100"
             :class="day.check_in ? 'bg-gray-50 text-gray-600' : 'bg-white text-gray-400'">
            {{ getDayName(day.date) }} <span class="text-gray-800">{{ formatDate(day.date) }}</span>
        </div>

        <!-- Contenido Día -->
        <div class="flex-1 p-2 flex flex-col justify-center items-center text-xs gap-1 min-h-[85px] relative group">

            <!-- Dropdown de Acciones -->
            <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity z-10" v-if="canEdit">
                <el-dropdown trigger="click" @command="handleCommand" size="small">
                    <button class="p-1 hover:bg-gray-200 rounded text-gray-400 hover:text-gray-600">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <template #dropdown>
                        <el-dropdown-menu>

                            <!-- ACCIONES TIEMPO EXTRA -->
                            <template v-if="$page.props.auth.user.permissions.includes('Aprobar tiempo extra')">
                                <el-dropdown-item
                                    v-if="(day.extra_hours || day.extra_minutes) && !day.approved_at && canManageIncidence(day)"
                                    :command="`approve_extra_time|${day.date}`">
                                    <i class="fa-solid fa-list-check mr-2 text-indigo-600"></i> Gestionar extra
                                </el-dropdown-item>
                                <el-dropdown-item
                                    v-if="(day.extra_hours || day.extra_minutes) && !day.approved_at && !canManageIncidence(day)"
                                    disabled>
                                    <i class="fa-solid fa-lock mr-2 text-gray-400"></i>
                                    {{ getIncidencePermission(day).reason || 'Sin permisos para gestionar' }}
                                </el-dropdown-item>
                                <el-dropdown-item v-if="day.approved_at && (day.extra_hours || day.extra_minutes) && canManageIncidence(day)" :command="`revert_extra_time|${day.date}`">
                                    <i class="fa-solid fa-rotate-left mr-2 text-red-600"></i> Revertir resolución
                                </el-dropdown-item>
                                <el-dropdown-item divided v-if="day.extra_hours || day.extra_minutes"></el-dropdown-item>
                            </template>

                            <!-- OTRAS ACCIONES -->
                            <el-dropdown-item :command="`edit_time|${day.date}`">
                                <i class="fa-regular fa-clock mr-2"></i> Editar/eliminar horas
                            </el-dropdown-item>
                            <el-dropdown-item v-if="day.extra_hours || day.extra_minutes" :command="`clear_extra_time|${day.date}`" divided>
                                <i class="fa-solid fa-trash-can mr-2 text-red-500"></i> Eliminar tiempo extra
                            </el-dropdown-item>
                            <el-dropdown-item :command="`edit_comment|${day.date}`">
                                <i class="fa-regular fa-comment mr-2"></i> Comentario
                            </el-dropdown-item>
                            <el-dropdown-item v-if="day.late" :command="`remove_late|${day.date}`">
                                <i class="fa-solid fa-eraser mr-2"></i> Quitar retardo
                            </el-dropdown-item>
                            <el-dropdown-item divided disabled>Incidencias</el-dropdown-item>
                            <el-dropdown-item v-for="inc in incidences" :key="inc" :command="`${inc}|${day.date}`">
                                {{ inc }}
                            </el-dropdown-item>

                            <!-- OPCIONES DE PROYECTO -->
                            <el-dropdown-item divided disabled>Proyecto</el-dropdown-item>
                            <el-dropdown-item v-if="!day.project_id" :command="`link_project|${day.date}`">
                                <i class="fa-solid fa-link mr-2"></i> Vincular proyecto
                            </el-dropdown-item>
                            <el-dropdown-item v-if="day.project_id" :command="`change_project|${day.date}`">
                                <i class="fa-solid fa-pen-to-square mr-2 text-indigo-500"></i> Cambiar proyecto
                            </el-dropdown-item>
                            <el-dropdown-item v-if="day.project_id" :command="`unlink_project|${day.date}`">
                                <i class="fa-solid fa-link-slash mr-2 text-red-400"></i> Desvincular proyecto
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>

            <!-- Horas / Incidencia -->
            <template v-if="day.check_in || day.check_out || day.incidence === 'Salió de Viaje'">
                <div v-if="day.incidence === 'Salió de Viaje'" class="text-[9px] text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded border border-purple-200 font-semibold mb-1 w-full text-center">
                    <i class="fa-solid fa-plane-departure mr-1"></i> Salió de viaje
                </div>

                <!-- Horas -->
                <div class="flex items-center gap-1.5 justify-center mt-0.5">
                    <div class="flex items-center gap-0.5">
                        <span class="text-gray-800 font-mono font-bold text-[11px]">{{ day.check_in?.substring(0, 5) || '??' }}</span>
                        <el-tooltip v-if="isValidLocation(day.check_in_location)" content="Ver ubicación de entrada" placement="top">
                            <a :href="`https://www.google.com/maps/search/?api=1&query=${day.check_in_location}`" target="_blank" class="text-blue-500 hover:text-blue-700 transition-colors" @click.stop>
                                <i class="fa-solid fa-location-dot text-[9px]"></i>
                            </a>
                        </el-tooltip>
                        <el-tooltip v-else-if="getLocationError(day.check_in_location)" :content="`Error GPS: ${getLocationError(day.check_in_location)}`" placement="top">
                            <i class="fa-solid fa-location-crosshairs text-red-400 text-[9px] cursor-help"></i>
                        </el-tooltip>
                    </div>
                    <span class="text-gray-400 text-[10px]">-</span>
                    <div class="flex items-center gap-0.5">
                        <span class="text-gray-800 font-mono font-bold text-[11px]">{{ day.check_out?.substring(0, 5) || '??' }}</span>
                        <el-tooltip v-if="isValidLocation(day.check_out_location)" content="Ver ubicación de salida" placement="top">
                            <a :href="`https://www.google.com/maps/search/?api=1&query=${day.check_out_location}`" target="_blank" class="text-blue-500 hover:text-blue-700 transition-colors" @click.stop>
                                <i class="fa-solid fa-location-dot text-[9px]"></i>
                            </a>
                        </el-tooltip>
                        <el-tooltip v-else-if="getLocationError(day.check_out_location)" :content="`Error GPS: ${getLocationError(day.check_out_location)}`" placement="top">
                            <i class="fa-solid fa-location-crosshairs text-red-400 text-[9px] cursor-help"></i>
                        </el-tooltip>
                    </div>
                </div>

                <!-- Tiempo de Comida / Break -->
                <div v-if="day.break_start" class="mt-1 text-[9px] text-orange-700 bg-orange-50 px-1.5 py-0.5 rounded border border-orange-200 text-center leading-tight w-full">
                    <div class="flex items-center justify-center gap-1">
                        <i class="fa-solid fa-utensils text-[8px]"></i>
                        <span class="font-semibold">{{ day.break_start?.substring(0, 5) }}</span>
                        <span v-if="day.break_end" class="text-orange-400">-</span>
                        <span v-if="day.break_end" class="font-semibold">{{ day.break_end?.substring(0, 5) }}</span>
                        <span v-else class="text-orange-400 animate-pulse">en curso...</span>
                    </div>
                    <div v-if="day.break_minutes" class="text-[8px] text-orange-600 mt-0.5 font-medium">
                        {{ day.break_minutes }} min
                    </div>
                </div>

                <div v-if="day.late" class="text-[10px] text-red-500 bg-red-50 px-1.5 rounded border border-red-100 mt-1">Retardo: {{ day.late }}m</div>

                <!-- UI de Tiempo Extra -->
                <div v-if="day.approved_at && (day.extra_hours || day.extra_minutes)">
                    <div v-if="day.approved_extra_hours === 0 && day.approved_extra_minutes === 0" class="text-[10px] text-red-700 bg-red-100 px-1.5 py-0.5 rounded border border-red-300 font-semibold text-center leading-tight w-full mt-1">
                        T.E. Rechazado <i class="fa-solid fa-xmark ml-0.5"></i>
                        <div class="text-[8px] mt-0.5 font-normal text-red-700 border-t border-red-200 pt-0.5">Por: {{ day.approver?.name?.split(' ')[0] || 'Admin' }}</div>
                        <span v-if="canSeeMoney && day.cost_per_hour" class="block text-[8px] text-red-500 mt-0.5">${{ formatMoney(day.cost_per_hour) }}/hr · $0.00</span>
                    </div>
                    <div v-else class="text-[10px] text-green-700 bg-green-100 px-1.5 py-0.5 rounded border border-green-300 font-semibold text-center leading-tight w-full mt-1">
                        T.E. Aprobado:<br>{{ day.approved_extra_hours }}h {{ day.approved_extra_minutes }}m <i class="fa-solid fa-check-circle ml-0.5"></i>
                        <div class="text-[8px] mt-0.5 font-normal text-green-700 border-t border-green-200 pt-0.5">Por: {{ day.approver?.name?.split(' ')[0] || 'Admin' }}</div>
                        <span v-if="canSeeMoney && day.extra_amount" class="block text-[8px] text-green-600 font-bold mt-0.5">${{ formatMoney(day.extra_amount) }}</span>
                    </div>
                </div>
                <div v-else-if="day.extra_hours || day.extra_minutes" class="text-[10px] text-amber-600 bg-amber-50 px-1.5 rounded border border-amber-200 text-center leading-tight mt-1">
                    Extra:<br>{{ day.extra_hours }}h {{ day.extra_minutes }}m
                    <span v-if="canSeeMoney && day.cost_per_hour" class="block text-[8px] text-amber-500 mt-0.5">${{ formatMoney(day.cost_per_hour) }}/hr</span>
                    <span v-if="canSeeMoney && day.extra_amount" class="block text-[8px] text-amber-600 font-bold mt-0.5">${{ formatMoney(day.extra_amount) }}</span>
                </div>

                <!-- Pipeline de aprobación -->
                <div v-if="(day.extra_hours || day.extra_minutes) && getDayApprovalSummary(day)" class="mt-1.5 border-t border-gray-100 pt-1">
                    <div v-if="getDayApprovalSummary(day).firstPendingLevel && getDayApprovalSummary(day).globalStatus === 'pending'"
                         class="text-[8px] text-blue-600 font-semibold mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-arrow-right text-[6px]"></i>
                        Toca: {{ getDayApprovalSummary(day).firstPendingLevel.name || 'Nivel ' + getDayApprovalSummary(day).firstPendingLevel.level }}
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <div v-for="level in getDayApprovalSummary(day).levels" :key="level.id"
                             class="flex items-center gap-1 text-[8px]"
                             :class="{ 'bg-blue-50/50 rounded px-1 -mx-1 py-0.5': getDayApprovalSummary(day).firstPendingLevel?.id === level.id && level.status !== 'approved' }"
                             :title="`${level.name || 'Nivel ' + level.level}: ${getApprovalStatusBadge(level.status).label}`">
                            <span class="flex-shrink-0 w-3.5 h-3.5 rounded-full flex items-center justify-center text-[6px]"
                                  :class="getApprovalStatusBadge(level.status).bg + ' ' + getApprovalStatusBadge(level.status).text">
                                <i :class="'fa-solid ' + getApprovalStatusBadge(level.status).icon"></i>
                            </span>
                            <span class="text-gray-500 font-medium truncate">{{ level.name || 'N' + level.level }}:</span>
                            <div class="flex -space-x-1.5">
                                <el-tooltip v-for="approver in level.approvers" :key="approver.id" :content="approver.name" placement="top">
                                    <img :src="approver.profile_photo_url" class="w-4 h-4 rounded-full border border-white object-cover" :alt="approver.name">
                                </el-tooltip>
                            </div>
                            <span v-if="level.decisions.length > 0" class="ml-auto flex -space-x-1">
                                <span v-for="dec in level.decisions" :key="dec.id"
                                      class="w-3 h-3 rounded-full border border-white flex items-center justify-center text-[6px]"
                                      :class="dec.status === 'approved' ? 'bg-green-400 text-white' : 'bg-red-400 text-white'"
                                      :title="dec.approver.name + ': ' + (dec.status === 'approved' ? 'Aprobó' : 'Rechazó')">
                                    <i :class="dec.status === 'approved' ? 'fa-solid fa-check' : 'fa-solid fa-xmark'"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Proyecto vinculado -->
            <div v-if="day.project" class="mt-1.5 border-t border-gray-100 pt-1">
                <el-tooltip :content="`${day.project.name} - ${day.project.client || 'Sin cliente'}`" placement="top">
                    <div class="text-[8px] text-teal-700 bg-teal-50 px-1.5 py-0.5 rounded border border-teal-200 font-semibold text-center leading-tight w-full truncate">
                        <i class="fa-solid fa-diagram-project mr-1"></i>{{ day.project.name }}
                    </div>
                </el-tooltip>
            </div>

            <!-- Sin horas -->
            <template v-else>
                <span class="text-center font-medium" :class="{
                    'text-red-500': day.incidence === 'Falta injustificada',
                    'text-purple-600': day.incidence === 'Salió de Viaje',
                    'text-gray-400': !['Falta injustificada', 'Salió de Viaje'].includes(day.incidence)
                }">{{ day.incidence || '-' }}</span>

                <!-- Proyecto vinculado (días sin horas) -->
                <div v-if="day.project" class="mt-1.5 border-t border-gray-100 pt-1">
                    <el-tooltip :content="`${day.project.name} - ${day.project.client || 'Sin cliente'}`" placement="top">
                        <div class="text-[8px] text-teal-700 bg-teal-50 px-1.5 py-0.5 rounded border border-teal-200 font-semibold text-center leading-tight w-full truncate">
                            <i class="fa-solid fa-diagram-project mr-1"></i>{{ day.project.name }}
                        </div>
                    </el-tooltip>
                </div>
            </template>
        </div>
    </div>
</template>
