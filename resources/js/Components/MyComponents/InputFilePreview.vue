<template>
  <div class="inline">
    <figure @click="triggerImageInput"
      class="flex items-center justify-center rounded-md border border-grayD9 relative"
      :class="{
        'cursor-not-allowed': disabled,
        'cursor-pointer': !disabled,
        'w-56 h-44': image,
        'size-14': !image
      }">
      <i v-if="image && canDelete && !disabled" @click.stop="clearImage"
        class="fa-solid fa-xmark absolute p-1 top-1 right-1 z-10 text-sm"></i>
      <i v-if="!image" class="fa-solid fa-plus text-gray-400 text-xl"></i>
      <img v-if="image" :src="image" :alt="alt" class="w-full h-full object-contain bg-no-repeat rounded-md opacity-50" />
      <input :disabled="disabled" ref="fileInput" type="file" @change="handleImageUpload" class="hidden" />
    </figure>
  </div>
</template>

<script>
export default {
  data() {
    return {
      image: null,
      formData: {
        file: null,
      },
    };
  },
  props: {
    alt: {
      type: String,
      default: "Vista previa no disponible",
    },
    canDelete: {
      type: Boolean,
      default: true,
    },
    imageUrl: {
      type: String,
      default: null,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },
  watch: {
    imageUrl: {
      immediate: true,
      handler(newImageUrl) {
        this.image = newImageUrl;
      },
    },
  },
  emits: ['imagen', 'cleared'],
  methods: {
    triggerImageInput() {
      this.$refs.fileInput.click();
    },
    handleImageUpload(event) {
      const file = event.target.files[0];
      this.formData.file = file;

      if (file) {
        const imageURL = URL.createObjectURL(file);
        this.image = imageURL;
        // Emitir evento al componente padre con la imagen
        this.$emit("imagen", file);
      }

    },
    clearImage() {
      this.image = null;
      this.formData.file = null;
      this.$emit("cleared");
    },
  },
};
</script>

