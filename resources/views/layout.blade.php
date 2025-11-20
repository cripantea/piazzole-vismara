<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Piazzole Gianmario')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen flex flex-col">

<!-- Header con Navigation -->
<header class="bg-white shadow-sm border-b border-gray-200">
    <nav class="container mx-auto px-6 py-4">
        <ul class="flex space-x-8">
            <li>
                <a href="{{ route('piazzole.index') }}"
                   class="text-gray-700 hover:text-blue-600 transition-colors font-medium
                              {{ request()->routeIs('piazzole.*') ? 'text-blue-600 border-b-2 border-blue-600 pb-1' : '' }}">
                    Piazzuole
                </a>
            </li>
            <li>
                <a href="{{ route('clienti.index') }}"
                   class="text-gray-700 hover:text-blue-600 transition-colors font-medium
                              {{ request()->routeIs('clienti.*') ? 'text-blue-600 border-b-2 border-blue-600 pb-1' : '' }}">
                    Clienti
                </a>
            </li>
            <li>
                <a href="{{ route('contratti.index') }}"
                   class="text-gray-700 hover:text-blue-600 transition-colors font-medium
                              {{ request()->routeIs('contratti.*') ? 'text-blue-600 border-b-2 border-blue-600 pb-1' : '' }}">
                    Contratti
                </a>
            </li>
            <li>
                <a href="{{ route('scadenze.index') }}"
                   class="text-gray-700 hover:text-blue-600 transition-colors font-medium
                              {{ request()->routeIs('scadenze.*') ? 'text-blue-600 border-b-2 border-blue-600 pb-1' : '' }}">
                    Scadenze
                </a>
            </li>
        </ul>
    </nav>
</header>

<!-- Main Content -->
<main class="flex-1 container mx-auto px-6 py-8">
    @yield('content')
</main>

<!-- Footer (opzionale) -->
<footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="container mx-auto px-6 py-4 text-center text-gray-600 text-sm">
        © {{ date('Y') }} Piazzole Gianmario
    </div>
</footer>

</body>
</html>