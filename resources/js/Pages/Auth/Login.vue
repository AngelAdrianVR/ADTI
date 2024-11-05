<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
// import Back from "@/Components/MyComponents/Back.vue";

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const showPassword = ref(false);

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar sesión" />


    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>


        <div class="border-b border-gray-300 mb-12 text-center w-[80%] mx-auto">
            <span class="inline-block border-b-2 border-primary px-3 text-gray-600">Iniciar sesión</span>
        </div>

        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <div
                    class="flex items-center space-x-2 py-2 px-5 w-full h-10 border border-grayD9 rounded-full placeholder:text-sm placeholder:text-[#777777]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-[#777777]">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>

                    <input v-model="form.username" id="username"
                        class="text-sm w-full placeholder:text-sm placeholder:text-[#777777] border-0 focus:ring-0 focus:border-grayD9 border-grayD9 border-l h-full"
                        type="text" placeholder="Nombre de usuario" required autofocus autocomplete="username">
                </div>
                <InputError :message="form.errors.username" />
            </div>
            <div>
                <div
                    class="mt-3 flex items-center space-x-2 py-2 px-5 w-full h-10 border border-grayD9 rounded-full placeholder:text-sm placeholder:text-[#777777]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-4 text-[#777777]">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <input v-model="form.password" id="password"
                        class="text-sm w-full placeholder:text-sm placeholder:text-[#777777] border-0 focus:ring-0 focus:border-grayD9 border-grayD9 border-l h-full"
                        :type="showPassword ? 'text' : 'password'" placeholder="Contraseña" required>
                    <button @click="showPassword = !showPassword" type="button" class="z-10">
                        <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-4 text-[#777777]">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-4 text-[#777777]">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <div class="flex justify-between items-center mt-4 ml-6">
                <el-checkbox v-model="form.remember" name="remember" label="Mantener sesión abierta" />
                <!-- <Link v-if="canResetPassword" :href="route('password.request')" class="cursor-pointer text-sm text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2">
                    Olvidé mi contraseña
                </Link> -->
            </div>

            <div class="flex items-center justify-center mt-7">
                <PrimaryButton class="ms-4 md:px-32 px-20" :disabled="form.processing">
                    <i v-if="form.processing" class="fa-sharp fa-solid fa-circle-notch fa-spin mr-2 text-white"></i>
                    Iniciar sesión
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
