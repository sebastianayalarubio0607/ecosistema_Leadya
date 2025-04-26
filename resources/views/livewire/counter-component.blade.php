<div>
    {{-- Because she competes with no one, no one can compete with her. --}}
    <p>hola componente</p>
    <p>el contador es: {{$count}}</p>
    <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded" wire:click="increment">Incrementar</button>
      
    <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded" wire:click="decrement">
        decrementar
    </button>
</div>
