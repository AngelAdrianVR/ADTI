<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ADTI') }} - Sesión Expirada</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Figtree', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-lg w-full mx-auto px-6">
        <div class="bg-white rounded-2xl shadow-lg p-10 text-center">
            {{-- Ícono --}}
            <div class="mx-auto w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>

            <h1 class="text-2xl font-semibold text-gray-800 mb-2">Sesión Expirada</h1>
            <p class="text-gray-500 mb-8 leading-relaxed">
                Tu sesión ha expirado por mantenerse inactiva durante más de <strong class="text-gray-700">2 horas</strong>.
                Por razones de seguridad, debes volver a iniciar sesión para continuar.
            </p>

            <a href="{{ url('/dashboard') }}"
               class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"></path>
                </svg>
                Ir al inicio
            </a>
        </div>

        <p class="text-center text-gray-400 text-sm mt-6">
            &copy; {{ date('Y') }} {{ config('app.name', 'ADTI') }}. Todos los derechos reservados.
        </p>
    </div>
</body>
</html>
