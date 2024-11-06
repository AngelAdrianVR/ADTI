<template>
    <section class="mt-10 lg:mt-20">
        <article class="grid grid-cols-2 lg:grid-cols-5 mt-4 mx-4 lg:mx-36 text-xs lg:text-base gap-3">
            <span class="text-secondary">ID:</span>
            <span class="lg:col-span-4">{{ user.id }}</span>
            <span class="text-secondary">Puesto:</span>
            <span class="lg:col-span-4">{{ user.org_props.position }}</span>
            <span class="text-secondary">Correo electrónico:</span>
            <span class="lg:col-span-4">{{ user.email }}</span>
            <span class="text-secondary">Teléfono:</span>
            <span class="lg:col-span-4">{{ user.phone }}</span>
        </article>
    </section>
</template>

<script>

export default {
    data() {
        return {
        }
    },
    components: {

    },
    props: {
        user: Object,
    },
    methods: {

    }
}
</script>
