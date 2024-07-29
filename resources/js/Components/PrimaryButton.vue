<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="text-center px-4 py-2 bg-primary border border-transparent rounded-full text-xs text-white tracking-widest active:scale-95 disabled:active:scale-100 disabled:cursor-not-allowed disabled:text-white disabled:bg-[#999999] focus:outline-1 focus:ring-1 focus:ring-primary transition ease-in-out duration-100">
        <slot />
    </button>
</template>
