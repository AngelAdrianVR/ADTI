<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="text-center px-4 py-2 bg-secondary border border-transparent rounded-full text-xs tracking-widest active:scale-95 disabled:active:scale-100 disabled:cursor-not-allowed text-white disabled:bg-[#999999] focus:outline-1 focus:ring-1 focus:ring-secondary transition ease-in-out duration-100">
        <slot />
    </button>
</template>
