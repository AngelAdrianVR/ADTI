<template>
    <AppLayout title="Nuevo usuario">
        <div class="px-3 md:px-16 py-8">
            <Back />
            {{ user }}
           
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputFilePreview from "@/Components/MyComponents/InputFilePreview.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { useForm } from "@inertiajs/vue3";

export default {
    data() {
        const form = useForm({
            name: null,
            org_props: {
                position: null
            },
            email: null,
            phone: null,
            image: null,
            roles: [],
        });

        return {
            //formularios
            form,
        }
    },
    components: {
        AppLayout,
        InputFilePreview,
        PrimaryButton,
        InputLabel,
        InputError,
        Back,
    },
    props: {
        user: Object,
    },
    methods: {
        async store() {
            try {
                this.form.post(route("users.store"), {
                    onSuccess: async () => {
                        // toast
                        this.$notify({
                            title: "Correcto",
                            message: "",
                            type: "success",
                            position: "bottom-right",
                        });
                    },
                });
            } catch (error) {
                console.error(error);
            }
        },
        saveImage(image) {
            this.form.image = image;
        },
    }
}
</script>