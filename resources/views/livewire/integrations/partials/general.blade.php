<section class="space-y-5">
    <div>
        <h3 class="text-base font-semibold text-white">Datos generales</h3>
        <p class="text-sm text-white/50">Identificacion, cliente y comportamiento general de la integracion.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="{{ $labelClass }}">Nombre *</label>
            <input name="name" wire:model.blur="form.name" class="{{ $inputClass }}" required>
            @error('name') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelClass }}">Cliente *</label>
            <select name="customer_id" wire:model.change="form.customer_id" class="{{ $inputClass }}" required>
                <option value="">Seleccione...</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer['id'] }}">{{ $customer['name'] }}</option>
                @endforeach
            </select>
            @error('customer_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="{{ $labelClass }}">Descripcion</label>
            <textarea name="description" wire:model.blur="form.description" rows="3" class="{{ $inputClass }}"></textarea>
            @error('description') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelClass }}">Tipo de integracion *</label>
            <select name="integrationtype_id"
                    wire:model="form.integrationtype_id"
                    wire:change="selectType($event.target.value)"
                    class="{{ $inputClass }}" required>
                <option value="">Seleccione...</option>
                @foreach($types as $type)
                    <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                @endforeach
            </select>
            @error('integrationtype_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelClass }}">URL destino</label>
            <input name="urldestino" type="url" wire:model.blur="form.urldestino" class="{{ $inputClass }}" placeholder="https://...">
            @error('urldestino') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelClass }}">Estado *</label>
            <select name="status" wire:model.change="form.status" class="{{ $inputClass }}" required>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
            @error('status') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $labelClass }}">Prioridad *</label>
            <input name="priority" type="number" min="0" wire:model.blur="form.priority" class="{{ $inputClass }}" required>
            @error('priority') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
        </div>
    </div>

    @if($typeChanged)
        <div class="border border-amber-300/20 bg-amber-500/10 p-4 text-sm text-amber-100">
            <p class="font-semibold">El tipo seleccionado es diferente al tipo guardado.</p>
            <p class="mt-1 text-amber-100/70">Al guardar, la logica actual puede reemplazar credenciales y configuraciones del tipo anterior.</p>
            <label class="mt-3 inline-flex items-center gap-2">
                <input type="checkbox" wire:model.live="confirmTypeChange" required class="rounded border-amber-200/30 bg-slate-900 text-amber-400">
                Confirmo que deseo cambiar el tipo de integracion
            </label>
        </div>
    @endif

    @if($supportsPrefix)
        <div class="border-t border-white/10 pt-5">
            <label class="inline-flex items-center gap-2 text-sm text-white/80">
                <input type="hidden" name="disable_integration_id_crm_prefix" value="0">
                <input type="checkbox" name="disable_integration_id_crm_prefix" value="1"
                       wire:model.live="form.disable_integration_id_crm_prefix"
                       class="rounded border-white/20 bg-slate-900 text-indigo-500">
                Usar un prefijo manual para crm_id
            </label>
            @if($form['disable_integration_id_crm_prefix'])
                <div class="mt-3 max-w-md">
                    <label class="{{ $labelClass }}">Prefijo manual *</label>
                    <input name="crm_id_prefix" wire:model.blur="form.crm_id_prefix" class="{{ $inputClass }}" required>
                    @error('crm_id_prefix') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    @endif
</section>
