<template>
    <main class="border">
        <div class="flex">
            <p class="text-[#6D6E72] w-48">Fecha de alta:</p>
            <p class="text-black">{{ formatDate(product.created_at) }}</p>
        </div>
    </main>
</template>

<script>
import { format, parseISO } from 'date-fns';
import es from 'date-fns/locale/es';

export default {
components:{

},
props:{
    product: Object
},
methods:{
    formatDate(dateString) {
        return format(parseISO(dateString), 'dd MMMM, yyyy', { locale: es });
    },
}
}
</script>
