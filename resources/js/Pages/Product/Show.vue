<template>
    <AppLayout :title="product.name">
        <main>
            show de producto
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
    PrimaryButton,
},
props:{
    product: Object
},
methods:{

}
}
</script>
