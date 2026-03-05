<nav class="flex items-center gap-1">
    @auth
        <a href="{{ url('/dashboard') }}"
            class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-orange-600 transition-colors">
            Dashboard
        </a>
    @else
        <a href="{{ route('login') }}"
            class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-orange-600 transition-colors">
            Iniciar Sesión
        </a>
    @endauth

    <a href="{{ route('register') }}"
        class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-orange-600 transition-colors">
        Registrarse
    </a>
    
    <a href="{{ route('generate-url') }}"
        class="ml-2 px-5 py-2.5 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition-all shadow-md">
        Generador URL
    </a>
</nav>
