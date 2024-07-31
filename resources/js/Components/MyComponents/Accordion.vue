<template>
    <div>
        <div @click.prevent="toggleAccordion" :aria-expanded="accordionOpen" :aria-controls="`accordion-text-${id}`"
            :title="title" class="flex items-center space-x-3" :class="{'justify-center': position == 'center', 'justify-end': position == 'end'}">
            <slot name="trigger">
                <!-- Default Trigger Button -->
                <button :id="`accordion-title-${id}`"
                    class="w-full text-start px-2 mb-1 flex justify-between text-xs rounded-md py-1"
                    :class="active ? 'font-bold text-[#FD8827]' : ''">
                    <p class="truncate"><span v-html="icon"></span> {{ title }}</p>
                </button>
            </slot>
            <i class="fa-solid fa-angle-down transform origin-center transition duration-200 ease-out text-xs text-primary"
                :class="{ '!rotate-180': accordionOpen }"></i>
        </div>
        <div :id="`accordion-text-${id}`" role="region" :aria-labelledby="`accordion-title-${id}`"
            class="grid text-sm overflow-hidden transition-all duration-300 ease-in-out"
            :class="accordionOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'">
            <div class="overflow-hidden">
                <p class="pb-1">
                    <slot name="content" />
                </p>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    name: 'Accordion',
    data() {
        return {
            accordionOpen: this.active,
        }
    },
    props: {
        title: String,
        id: Number,
        active: Boolean,
        icon: String,
        position: String,
    },
    components: {
    },
    methods: {
        toggleAccordion() {
            this.accordionOpen = !this.accordionOpen;
        }
    }
}
</script>
