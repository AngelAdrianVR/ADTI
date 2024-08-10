<template>
    <AppLayout title="Detalles usuario">
        <header class="lg:px-9 px-1 mt-3">
            <h1 class="font-bold text-base mt-10">Detalles del usuario</h1>
            <section class="md:flex items-center justify-between mt-2">
                <!-- buscador -->
                <el-select @change="$inertia.get(route('users.show', selectedItem))" v-model="selectedItem"
                    class="w-full lg:!w-1/4 mt-2" placeholder="Buscar usuario" filterable
                    no-data-text="No hay más usuarios registrados" no-match-text="No se encontraron coincidencias">
                    <el-option v-for="item in users" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
                <div class="flex items-center space-x-2 mt-3 md:mt-0">
                    <PrimaryButton v-if="$page.props.auth.user.permissions.includes('Editar usuarios')"
                        @click="$inertia.get(route('users.edit', user.id))" :disabled="loading">Editar</PrimaryButton>
                    <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Resetear contraseñas')"
                        confirm-button-text="Si" cancel-button-text="No" icon-color="#6D6E72"
                        :title="'¿Desea continuar?'" @confirm="resetPassword()">
                        <template #reference>
                            <SecondaryButton class="!rounded-full !p-2" :disabled="loading">
                                Resetear contraseña
                            </SecondaryButton>
                        </template>
                    </el-popconfirm>
                    <SecondaryButton @click="$inertia.get(route('users.create'))"
                        class="!rounded-full !p-2" :disabled="loading"><i
                            class="fa-solid fa-plus text-xs size-4"></i></SecondaryButton>
                </div>
            </section>
        </header>
        <main class="relative mt-10">
            <div class="bg-[#0B3B51] h-44 pt-px">
                <button @click="$inertia.get(route('users.index'))"
                    class="flex justify-center items-center rounded-full size-5 focus:outline-none bg-grayED text-primary ml-3 mt-3">
                    <i class="fa-solid fa-angle-left text-xs"></i>
                </button>
            </div>
            <svg class="absolute top-1 left-12" width="533" height="169" viewBox="0 0 533 169" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path d="M41.0025 168.5C-115 -99.5 212.506 253.5 532.003 1" stroke="#D9D9D9" />
            </svg>
            <svg class="absolute top-[75px] right-12" width="426" height="102" viewBox="0 0 426 102" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path d="M335 101.001C587.5 -66.501 258 7.5 0.5 101.001" stroke="#D9D9D9" />
            </svg>
            <figure
                class="size-32 lg:size-40 rounded-[5px] bg-gray-500 absolute top-20 left-[calc(50%-5rem)] shadow-lg">
                <img :src="user.profile_photo_url" :alt="user.name"
                    class="size-32 lg:size-40 object-cover rounded-[5px]">
            </figure>
            <section class="mt-10 lg:mt-20">
                <h1 class="font-bold text-center">{{ user.name }}</h1>
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
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Back from "@/Components/MyComponents/Back.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import axios from "axios";

export default {
    data() {
        return {
            loading: false,
            selectedItem: this.user.id,
        }
    },
    components: {
        AppLayout,
        PrimaryButton,
        Back,
        SecondaryButton,
    },
    props: {
        user: Object,
        users: Array,
    },
    methods: {
        async resetPassword() {
            try {
                this.loading = true;
                const response = await axios.put(route('users.reset-password', this.user.id));

                if (response.status === 200) {
                    this.$notify({
                        title: "Correcto",
                        message: "Se ha reseteado la contraseña a 123456",
                        type: "success",
                    });
                }
            } catch (error) {
                console.log(error)
            } finally {
                this.loading = false;
            }
        },
    }
}
</script>