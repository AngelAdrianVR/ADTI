<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({
    href: String,
    as: String,
    notifications: Boolean,
    active: Boolean,
});

const hovering = ref(false);

const iconClass = computed(() => {
  return 'fa-solid fa-circle fa-flip text-' + (hovering.value ? 'white' : 'primary') + ' text-[7px]';
});

</script>

<template>
    <div @onmouseover="hover.value = true">
        <Link @mouseenter="hovering = true" @mouseleave="hovering = false" :href="href"
            class="rounded-md px-2 py-[4px] my-1 text-xs leading-4 text-gray-700 hover:bg-primary hover:text-white focus:outline-none focus:bg-primary transition duration-150 ease-in-out flex justify-between items-center"
            :class="active ? 'bg-primary text-white' : ''">
            <slot />
            <i v-if="notifications" :class="iconClass"></i>
        </Link>
    </div>
</template>
