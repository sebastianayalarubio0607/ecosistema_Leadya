<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\MetaEvent;
use Illuminate\Http\Request;

class MetaEventController extends Controller
{
    public function index(Request $request)
    {
        $query = MetaEvent::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('id', $search)
                ->orWhere('nombre', 'like', "%{$search}%");
        }

        $items = $query
            ->withCount('crmStates') // ✅ antes funnels
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        return view('meta.meta-events.index', compact('items'));
    }

    public function create()
    {
        return view('meta.meta-events.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'  => ['required', 'string', 'max:255'],
            'estados' => ['required', 'string', 'max:50'],
        ]);

        $item = MetaEvent::create($data);

        return redirect()
            ->route('meta.meta-events.show', $item)
            ->with('success', 'Meta Event creado correctamente.');
    }

    public function show(MetaEvent $meta_event)
    {
        $meta_event->load([
            'crmStates' => fn ($q) => $q->orderBy('name'),
            'crmStates.qualificationModel', // opcional (si lo muestras)
        ]);

        return view('meta.meta-events.show', ['item' => $meta_event]);
    }

    public function edit(MetaEvent $meta_event)
    {
        return view('meta.meta-events.edit', ['item' => $meta_event]);
    }

    public function update(Request $request, MetaEvent $meta_event)
    {
        $data = $request->validate([
            'nombre'  => ['required', 'string', 'max:255'],
            'estados' => ['required', 'string', 'max:50'],
        ]);

        $meta_event->update($data);

        return redirect()
            ->route('meta.meta-events.show', $meta_event)
            ->with('success', 'Meta Event actualizado correctamente.');
    }

    public function destroy(MetaEvent $meta_event)
    {
        $meta_event->delete();

        return redirect()
            ->route('meta.meta-events.index')
            ->with('success', 'Meta Event eliminado correctamente.');
    }
}
