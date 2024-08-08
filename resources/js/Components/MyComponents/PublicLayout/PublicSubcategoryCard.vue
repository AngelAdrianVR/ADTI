<template>
    <main>
        <!-- Imagen de la subcategoría -->
        <figure @click="$inertia.get(route('public.show-subcategory', subcategory.id))" class="border border-grayD9 hover:border-primary rounded-md p-2 bg-white cursor-pointer flex flex-col justify-center items-center">
            <div class="flex justify-center items-center size-24">
                <img v-if="subcategory.media?.length" class="mx-auto h-full object-contain"
                    :src="subcategory.media[0]?.original_url" alt="subcategory's image cover">
                <div class="text-center" v-else>
                    <i class="fa-regular fa-image text-7xl text-gray-200"></i>
                </div>
            </div>

            <p class="text-center text-sm text-primary mt-3">{{ subcategory.name }}</p>
        </figure>

        <!-- Enlista las subcategorías de nivel 3 -->
        <!-- <ul class="mt-1">
            <li v-for="subcategory_3 in subcategories_lvl_3.filter(sb3 => sb3.prev_subcategory_id === subcategory.id)" :key="subcategory_3"
                class="text-sm text-primary pl-4 hover:underline cursor-pointer">
                - {{ subcategory_3.name }}
            </li>
        </ul> -->
    </main>
</template>

<script>
export default {
data() {
    return {

    }
},
components:{

},
props:{
    subcategory: Object,
    subcategories_lvl_3: {
        default: null,
        type: Array
    }
},
methods:{

}
}
</script>