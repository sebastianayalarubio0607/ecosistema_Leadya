<?php

namespace App\Http\Controllers\Funnel;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\MetaEvent;
use App\Models\Qualification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FunnelWebController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->get('q');

        $funnels = Funnel::query()
            ->withCount('qualifications')
            ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->inDisplayOrder()
            ->paginate(15)
            ->withQueryString();

        return view('funnels.index', compact('funnels', 'q'));
    }

    public function create(): View
    {
        $funnel = new Funnel(['orden' => min(2147483647, ((int) Funnel::query()->max('orden')) + 5)]);

        $qualifications = Qualification::query()
            ->orderBy('name')
            ->get(['id', 'name', 'funnel_id']);

       

        $selectedQualificationIds = old('qualification_ids', []);

        return view('funnels.create', compact(
            'funnel',
            'qualifications',
            'selectedQualificationIds',
            
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $qualificationTable = (new Qualification())->getTable();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'orden' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],



            // Qualifications
            'qualification_ids' => ['sometimes', 'array'],
            'qualification_ids.*' => ['integer', Rule::exists($qualificationTable, 'id')],
        ]);

        $qualificationIds = $validated['qualification_ids'] ?? [];
        unset($validated['qualification_ids']);

        DB::transaction(function () use ($validated, $qualificationIds) {
            $funnel = Funnel::create($validated);

            if (!empty($qualificationIds)) {
                Qualification::whereIn('id', $qualificationIds)->update(['funnel_id' => $funnel->id]);
            }
        });

        return redirect()
            ->route('funnels.index')
            ->with('success', 'Funnel creado correctamente.');
    }

    public function show(Funnel $funnel): View
    {
        $funnel->load([
           
            'qualifications' => fn ($q) => $q->orderBy('name'),
        ]);

        return view('funnels.show', compact('funnel'));
    }

    public function edit(Funnel $funnel): View
    {
        $funnel->load([
            
            'qualifications:id,funnel_id,name',
        ]);

        $qualifications = Qualification::query()
            ->orderBy('name')
            ->get(['id', 'name', 'funnel_id']);

        

        $selectedQualificationIds = old('qualification_ids', $funnel->qualifications->pluck('id')->all());

        return view('funnels.edit', compact(
            'funnel',
            'qualifications',
            'selectedQualificationIds',
            
        ));
    }

    public function update(Request $request, Funnel $funnel): RedirectResponse
    {
        $qualificationTable = (new Qualification())->getTable();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'orden' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],

            // MetaEvent
            'meta_event_id' => ['nullable', 'integer', 'exists:meta_events,id'],

            // Qualifications
            'qualification_ids' => ['sometimes', 'array'],
            'qualification_ids.*' => ['integer', Rule::exists($qualificationTable, 'id')],
        ]);

        $qualificationIds = $validated['qualification_ids'] ?? [];
        unset($validated['qualification_ids']);

        DB::transaction(function () use ($validated, $qualificationIds, $funnel) {
            $funnel->update($validated);

            // Quitar del funnel las no seleccionadas
            Qualification::where('funnel_id', $funnel->id)
                ->when(!empty($qualificationIds), fn ($q) => $q->whereNotIn('id', $qualificationIds))
                ->update(['funnel_id' => null]);

            // Asignar seleccionadas
            if (!empty($qualificationIds)) {
                Qualification::whereIn('id', $qualificationIds)->update(['funnel_id' => $funnel->id]);
            }
        });

        return redirect()
            ->route('funnels.index')
            ->with('success', 'Funnel actualizado correctamente.');
    }

    public function reorder(): RedirectResponse
    {
        DB::transaction(function (): void {
            // Read the whole catalog, independent of search filters and pagination.
            $funnels = Funnel::query()->inDisplayOrder()->lockForUpdate()->get(['id']);

            foreach ($funnels as $index => $funnel) {
                // Only the display order changes; relationships and timestamps stay intact.
                DB::table('funnels')->where('id', $funnel->id)->update(['orden' => ($index + 1) * 5]);
            }
        });

        return redirect()->route('funnels.index')
            ->with('success', 'Valores actualizados de 5 en 5, manteniendo el orden de todos los funnels.');
    }

    public function destroy(Funnel $funnel): RedirectResponse
    {
        $funnel->delete();

        return redirect()
            ->route('funnels.index')
            ->with('success', 'Funnel eliminado.');
    }
}
