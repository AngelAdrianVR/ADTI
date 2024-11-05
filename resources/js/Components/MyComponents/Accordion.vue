<template>
    <div>
        <div @click.prevent="toggleAccordion" :aria-expanded="accordionOpen" :aria-controls="`accordion-text-${id}`"
            :title="title" class="flex items-center space-x-3 px-3 rounded-lg mb-2 ml-2 py-1"
            :class="{
                'justify-center': position == 'center',
                'justify-end': position == 'end',
                'font-bold text-primary bg-[#c5c5c5]': active
                }">
            <slot name="trigger">
                <!-- Default Trigger Button -->
                <button :id="`accordion-title-${id}`"
                    class="w-full text-start flex justify-between text-xs rounded-md py-1">
                    <p class="truncate flex items-center space-x-2 text-gray-700"><span v-html="icon"></span> <span>{{ title }}</span></p>
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
