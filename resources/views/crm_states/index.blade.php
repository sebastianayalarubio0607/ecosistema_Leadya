<<<<<<< HEAD
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                CRM States
            </h2>

            <a href="{{ route('crmstates.create') }}"
               class="px-4 py-2 rounded bg-green-600 text-white">
                Nuevo
            </a>
        </div>
    </x-slot>

    <div class="p-6 max-w-6xl mx-auto">
        @if (session('success'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" class="mb-4 flex gap-2">
            <input name="q" value="{{ $q ?? request('q') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   placeholder="Buscar por ID (ej: 1-123) o nombre..." />
            <button class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                Buscar
            </button>
        </form>

        <div class="bg-white dark:bg-gray-900 rounded shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                <tr>
                    <th class="p-3">ID</th>
                    <th class="p-3">Nombre</th>
                    <th class="p-3">Qualification</th>
                    <th class="p-3 w-72">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($crmstates as $crmstate)
                    <tr class="border-t border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200">
                        <td class="p-3 font-mono text-xs">{{ $crmstate->id }}</td>
                        <td class="p-3">{{ $crmstate->name }}</td>
                        <td class="p-3">
                            {{ $crmstate->qualificationModel->name ?? '—' }}
                        </td>
                        <td class="p-3 flex gap-2">
                            <a class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-800"
                               href="{{ route('crmstates.show', $crmstate) }}">
                                Ver
                            </a>

                            <a class="px-3 py-1 rounded bg-yellow-900 text-white"
                               href="{{ route('crmstates.edit', $crmstate) }}">
                                Editar
                            </a>

                            <form method="POST"
                                  action="{{ route('crmstates.destroy', $crmstate) }}"
                                  onsubmit="return confirm('¿Seguro que deseas eliminar este CRM State?');">
                                @csrf
                                @method('DELETE')
                                <button class="px-3 py-1 rounded bg-red-900 text-white" type="submit">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-3" colspan="4">No hay CRM States.</td></tr>
                @endforelse
=======
@extends('meta.layout')

@section('title', 'CRM States')
@section('subtitle', 'Estados del CRM + asignación a Meta Event (conversión)')

@section('header_actions')
    <a href="{{ route('crmstates.create') }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-10">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="q" value="{{ $q }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por ID o nombre">
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('crmstates.index') }}"
                   class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">
                    Limpiar
                </a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">ID</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Qualification</th>
                        <th class="text-left px-3 py-2">Meta Event</th>
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($crmstates as $it)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">
                                {{ $it->id }}
                                <div class="text-xs text-white/50">
                                    Leads: {{ method_exists($it, 'leads') ? $it->leads()->count() : '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ $it->name }}</td>
                            <td class="px-3 py-2">{{ $it->qualificationModel?->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $it->metaEvent?->nombre ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('crmstates.show', $it) }}">Ver</a>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('crmstates.edit', $it) }}">Editar</a>

                                    <form action="{{ route('crmstates.destroy', $it) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('¿Eliminar CRM State?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-white/60">No hay CRM States.</td>
                        </tr>
                    @endforelse
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
                </tbody>
            </table>
        </div>

<<<<<<< HEAD
        <div class="mt-4">
            {{ $crmstates->links() }}
        </div>
    </div>
</x-app-layout>
=======
        <div>{{ $crmstates->links() }}</div>
    </div>
@endsection
>>>>>>> 3ac2fef11dafeeab5dedfae1f504ba67206b2bba
