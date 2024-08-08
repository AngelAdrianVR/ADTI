<template>
    <PublicLayout :title="subcategory.name">
        <main class="px-2 lg:p-28 xl:py-8 xl:px-48 py-7">
            <!-- Decorations  -->
            <figure>
                <img class="absolute top-40 left-0" src="@/../../public/images/home_decoration1.png" alt="">
                <img class="hidden md:block absolute top-20 left-0" src="@/../../public/images/home_decoration2.png" alt="">
                <img class="absolute top-20 right-0" src="@/../../public/images/home_decoration3.png" alt="">
            </figure>
            <!-- ------------ -->

            <!-- bread crumbles -->
            <div class="flex items-center space-x-3 text-sm text-gray99 mb-5 mx-2 md:mx-6">
                <p>Inicio</p>
                <i class="fa-solid fa-angle-right text-xs"></i>
                <p>{{ subcategory.category.name }}</p>
                <i class="fa-solid fa-angle-right text-xs"></i>
                <p>{{ subcategory.name }}</p>
            </div>

            <body class="mx-2 md:mx-6">
                <h1 class="font-bold text-lg mb-2">{{ subcategory.name }}</h1>

                <section>
                    <div class="md:grid md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-7 gap-5 py-5">
                        <PublicSubcategoryCard class="z-10" v-for="subcategory in handleSubcategoryArray" :key="subcategory" :subcategory="subcategory" />
                    </div>
                </section>
            </body>
        </main>
    </PublicLayout>
</template>

<script>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PublicSubcategoryCard from '@/Components/MyComponents/PublicLayout/PublicSubcategoryCard.vue';

export default {
data() {
    return {

    }
},
components:{
    PublicLayout,
    PublicSubcategoryCard,
},
props:{
    subcategory: Object
},
methods:{
    
},
computed:{
    handleSubcategoryArray() {
        //si la subcategoría es de nivel 1 recupera las subcategorías relacionadas a esa 1 nivel arriba
        if ( this.subcategory.level === 1 ) {
            return this.subcategory?.category?.subcategories?.filter(sb => sb.level === 2 && sb.prev_subcategory_id === this.subcategory.id)
        } else { //si la subcategoría es de nivel 2 recupera las subcategorías relacionadas a esa 1 nivel arriba
            return this.subcategory?.category?.subcategories?.filter(sb => sb.level === 3 && sb.prev_subcategory_id === this.subcategory.id)
        }
    }
},
mounted() {
    
}
}
</script>
