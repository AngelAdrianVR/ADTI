<script setup>
import { computed } from 'vue';

// Gráfica de línea ligera en SVG (sin dependencias externas).
// Recibe etiquetas (x) y series { name, color, values }.
const props = defineProps({
    labels: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    height: { type: Number, default: 280 },
    suffix: { type: String, default: 'h' },
});

const PAD = { top: 18, right: 18, bottom: 32, left: 54 };
const W = 820;

const maxValue = computed(() => {
    const all = props.series.flatMap(s => s.values || []);
    const m = Math.max(0, ...all);
    if (m === 0) return 1;
    const pow = Math.pow(10, Math.floor(Math.log10(m)));
    return Math.ceil(m / pow) * pow;
});

const innerW = computed(() => W - PAD.left - PAD.right);
const innerH = computed(() => props.height - PAD.top - PAD.bottom);
const stepX = computed(() => (props.labels.length > 1 ? innerW.value / (props.labels.length - 1) : innerW.value));

const x = (i) => (props.labels.length > 1 ? PAD.left + (i * stepX.value) : PAD.left + innerW.value / 2);
const y = (v) => PAD.top + innerH.value - ((v || 0) / maxValue.value) * innerH.value;

const gridLines = computed(() => {
    const lines = [];
    const steps = 4;
    for (let i = 0; i <= steps; i++) {
        const value = (maxValue.value / steps) * i;
        lines.push({ y: y(value), value });
    }
    return lines;
});

const pathFor = (s) => s.values.map((v, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(2)},${y(v).toFixed(2)}`).join(' ');

const formatTick = (v) => {
    if (v >= 1000) return `${(v / 1000).toFixed(0)}k`;
    return Number.isInteger(v) ? v : v.toFixed(1);
};
</script>

<template>
    <div>
        <!-- Leyenda -->
        <div v-if="series.length > 0" class="flex flex-wrap items-center gap-4 mb-3">
            <div v-for="s in series" :key="s.name" class="flex items-center gap-1.5 text-xs text-gray-600">
                <span class="w-4 h-1 rounded" :style="{ backgroundColor: s.color }"></span>
                <span>{{ s.name }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <svg :viewBox="`0 0 ${W} ${height}`" class="w-full" style="min-width: 600px" role="img">
                <!-- Líneas de cuadrícula -->
                <g v-for="(line, i) in gridLines" :key="'g' + i">
                    <line :x1="PAD.left" :x2="W - PAD.right" :y1="line.y" :y2="line.y" stroke="#f1f5f9" stroke-width="1" />
                    <text :x="PAD.left - 8" :y="line.y + 4" text-anchor="end" fill="#9ca3af" font-size="10">{{ formatTick(line.value) }}</text>
                </g>

                <!-- Series -->
                <g v-for="s in series" :key="s.name">
                    <path :d="pathFor(s)" fill="none" :stroke="s.color" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    <circle v-for="(v, i) in s.values" :key="i" :cx="x(i)" :cy="y(v)" r="3.5" :fill="s.color">
                        <title>{{ labels[i] }} — {{ s.name }}: {{ v }}{{ suffix }}</title>
                    </circle>
                </g>

                <!-- Etiquetas X -->
                <text v-for="(label, i) in labels" :key="'l' + i" :x="x(i)" :y="height - 8" text-anchor="middle" fill="#6b7280" font-size="10">{{ label }}</text>
            </svg>
        </div>
    </div>
</template>
