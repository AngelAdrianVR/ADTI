<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

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

    <div class="min-h-screen flex bg-gray-50">
        
        <!-- Sección Izquierda: Decorativa (Visible solo en desktop) -->
        <div class="hidden lg:flex lg:w-1/2 bg-[#1676A2] relative overflow-hidden flex-col items-center justify-center p-12">
            <!-- Círculos decorativos de fondo -->
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0">
                <div class="absolute w-96 h-96 bg-white/10 rounded-full -top-10 -left-20 mix-blend-overlay blur-3xl"></div>
                <div class="absolute w-96 h-96 bg-white/10 rounded-full bottom-0 right-0 mix-blend-overlay blur-3xl"></div>
            </div>

            <!-- Contenido Branding -->
            <div class="relative z-10 text-center text-white max-w-lg">
                <div class="mb-8 flex justify-center">
                    <!-- Logo Grande en Blanco/Transparente -->
                     <img src="/images/logo_colors.webp" alt="Logo" class="w-40 h-auto bg-white p-2 rounded-xl shadow-lg opacity-90 backdrop-blur-sm">
                </div>
                <h2 class="text-4xl font-bold mb-4 tracking-tight">Bienvenido a ERP ADTI</h2>
                <p class="text-blue-100 text-lg font-light leading-relaxed">
                    Gestiona tus proyectos, nóminas y recursos de manera eficiente en una sola plataforma.
                </p>
            </div>
            
            <div class="absolute bottom-4 text-xs text-blue-200">
                &copy; {{ new Date().getFullYear() }} ERP. Todos los derechos reservados.
            </div>
        </div>

        <!-- Sección Derecha: Formulario -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 md:p-12 relative">
            
            <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-gray-100 p-8 md:p-10 relative z-10">
                
                <!-- Logo móvil -->
                <div class="lg:hidden text-center mb-8">
                    <img src="/images/logo_colors.webp" alt="Logo" class="w-32 h-auto mx-auto">
                </div>

                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-800 text-center lg:text-left">¡Hola de nuevo!</h1>
                    <p class="text-sm text-gray-500 mt-2 text-center lg:text-left">Ingresa tus credenciales para acceder a tu cuenta.</p>
                </div>

                <div v-if="status" class="mb-4 font-medium text-sm text-green-600 bg-green-50 p-3 rounded-lg border border-green-200">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    
                    <!-- Username -->
                    <div>
                        <InputLabel for="username" value="Nombre de Usuario" class="!text-gray-700 font-semibold" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <TextInput
                                id="username"
                                v-model="form.username"
                                type="text"
                                class="w-full pl-10 !rounded-lg !border-gray-300 focus:!border-[#1676A2] focus:!ring-[#1676A2]"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="Ingresa tu usuario"
                            />
                        </div>
                        <InputError class="mt-1" :message="form.errors.username" />
                    </div>

                    <!-- Password -->
                    <div>
                        <!-- <div class="flex justify-between items-center mb-1">
                            <InputLabel for="password" value="Contraseña" class="!text-gray-700 font-semibold" />
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs text-[#1676A2] hover:text-[#0f5270] hover:underline font-medium">
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </div> -->
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <TextInput
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="w-full pl-10 pr-10 !rounded-lg !border-gray-300 focus:!border-[#1676A2] focus:!ring-[#1676A2]"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            />
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-[#1676A2] transition-colors focus:outline-none"
                            >
                                <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <!-- Remember Me -->
                    <div class="block">
                        <label class="flex items-center">
                            <Checkbox v-model:checked="form.remember" name="remember" class="!text-[#1676A2] focus:!ring-[#1676A2]" />
                            <span class="ms-2 text-sm text-gray-600">Recordar dispositivo</span>
                        </label>
                    </div>

                    <!-- Submit -->
                    <div class="pt-2">
                        <PrimaryButton 
                            class="w-full justify-center py-3 text-base !bg-[#1676A2] hover:!bg-[#125d80] !rounded-lg shadow-md hover:shadow-lg transition-all duration-300" 
                            :class="{ 'opacity-25': form.processing }" 
                            :disabled="form.processing"
                        >
                            <span v-if="!form.processing">Iniciar Sesión</span>
                            <span v-else class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Iniciando...
                            </span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>