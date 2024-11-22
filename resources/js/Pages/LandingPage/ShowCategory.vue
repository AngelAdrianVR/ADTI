<template>
    <PublicLayout :title="category.name">
        <main class="px-2 lg:p-8 xl:px-48 py-7">

            <!-- Decorations  -->
            <!-- <figure class="-z-20">
                <img class="absolute top-40 left-0" src="@/../../public/images/home_decoration1.png" alt="">
                <img class="hidden md:block absolute top-20 left-0" src="@/../../public/images/home_decoration2.png" alt="">
                <img class="absolute top-20 right-0" src="@/../../public/images/home_decoration3.png" alt="">
            </figure> -->

            <!-- bread crumbles -->
            <div class="flex items-center space-x-3 text-sm text-gray99 mb-5 mx-2 md:mx-6">
                <p class="cursor-pointer hover:text-primary" @click="$inertia.get(route('welcome'))">Inicio</p>
                <i class="fa-solid fa-angle-right text-xs"></i>
                <p class="text-primary font-bold">{{ category.name }}</p>
            </div>

            <body class="mx-2 md:mx-6">
                <h1 class="font-bold text-lg mb-2">{{ category.name }}</h1>

                <section v-for="subcategory_1 in category.subcategories?.filter(sb => sb.level === 1)" :key="subcategory_1">
                    <div class="bg-[#F2F2F2] text-[#6D6E72] flex items-center">
                        <i class="fa-solid fa-play text-2xl "></i>
                        <p class="py-1 pl-4">{{ subcategory_1.name }}</p>
                    </div>

                    <div class="grid grid-cols-3 lg:grid-cols-5 xl:grid-cols-7 gap-4 py-5">
                        <PublicSubcategoryCard class="z-10" v-for="subcategory in category.subcategories?.filter(sb => sb.level === 2 && sb.prev_subcategory_id === subcategory_1.id)" :key="subcategory" 
                            :subcategory="subcategory"
                            :subcategories_lvl_3="category.subcategories?.filter(sb => sb.level === 3)" />
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
    category: Object
},
methods:{

}
}
</script>
