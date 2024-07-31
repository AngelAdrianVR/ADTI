<template>
    <AppLayout title="Productos">
        <main ref="scrollContainer" style="height: 93vh; overflow-y: scroll;" @scroll="handleScroll" class="px-2 lg:px-10 py-7">
            <h1 class="font-bold my-3 ml-4 text-lg">Productos</h1>
            <section class="md:flex justify-between items-center">
                <article class="flex items-center space-x-5 lg:w-1/3">
                    <div class="lg:w-full relative">
                        <input v-model="searchQuery" @keydown.enter="searchProducts" class="input w-full pl-9"
                            placeholder="Buscar por nombre o número de parte" type="search" ref="scanInput" />
                        <i class="fa-solid fa-magnifying-glass text-xs text-gray99 absolute top-[10px] left-4"></i>
                    </div>
                    <el-tag @close="closedTag" v-if="searchedWord" closable type="primary">
                        {{ searchedWord }}
                    </el-tag>
                </article>
                <div class="my-4 lg:my-0 flex items-center justify-end space-x-3">
                    <PrimaryButton id="start" @click="$inertia.get(route('products.create'))">Crear producto</PrimaryButton>
                </div>
            </section>
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

export default {
data() {
    return {

    }
},
components:{
    AppLayout,
    PrimaryButton
},
props:{

},
methods:{
    handleScroll() {
        const container = this.$refs.scrollContainer;
        // const scrollHeight = container.scrollHeight;
        const scrollTop = container.scrollTop;
        // const clientHeight = container.clientHeight;

        // Determinar si has llegado al final de la vista
        if (scrollTop > 500) {
            this.showScrollButton = true;
        } else {
            this.showScrollButton = false;
        }
    },
    scrollToTop() {
        const section = document.getElementById('start');
        section.scrollIntoView({ behavior: 'smooth' });
    },
}
}
</script>
