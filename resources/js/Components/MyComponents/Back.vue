<template>
  <button @click="handleRedirection()"
    class="flex justify-center items-center rounded-full size-9 focus:outline-none hover-3dbuttom z-50">
    <i class="fa-solid fa-angle-left text-black text-base"></i>
  </button>
</template>

<script>
export default {
  props: {
    to: {
      type: String,
      default: null
    }
  },
  methods: {
    handleRedirection() {
      if (this.to) {
        this.$inertia.visit(this.to);
      } else {
        window.history.back();
      }
    },
  },
};
</script>
