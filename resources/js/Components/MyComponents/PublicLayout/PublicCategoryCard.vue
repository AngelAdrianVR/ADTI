<template>
    <main class="p-3">
        <!-- Imagen de la categoría -->
        <figure @click="$inertia.get(route('public.show-category', category.id))" 
            class="border border-grayD9 rounded-md flex justify-center items-center p-3 bg-white cursor-pointer hover:border-primary h-56">
            <img v-if="category.media?.length" class="mx-auto h-full object-contain"
                :src="category.media[0]?.original_url" alt="category's image cover">
            <div v-else>
                <i class="fa-regular fa-image text-9xl text-gray-200"></i>
                <p class="text-sm text-gray-300">Imagen no disponible</p>
            </div>
        </figure>

        <h1 class="mt-5 mb-1 font-bold text-center">{{ category.name }}</h1>

        <!-- subcategorías -->
        <div class="border-t border-primary py-3">
            <ul class="space-y-1">
                <li class="text-sm cursor-pointer hover:text-primary"
                    v-for="subcategory in category.subcategories?.filter(sb => sb.level === 1)" :key="subcategory"
                    @click="$inertia.get(route('public.show-subcategory', subcategory.id))">
                    {{ subcategory.name }}
                </li>
            </ul>
        </div>
    </main>
</template>

<script>
export default {
data() {
    return {

    }
},
props:{
    category: Object
}
}
</script>