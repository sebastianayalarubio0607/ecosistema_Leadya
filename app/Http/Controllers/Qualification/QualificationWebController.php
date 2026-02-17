<?php

namespace App\Http\Controllers\Qualification;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\Qualification;
use Illuminate\Http\Request;

class QualificationWebController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        $qualifications = Qualification::query()
            ->with('funnel:id,name')
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('qualifications.index', compact('qualifications', 'q'));
    }

    public function create()
    {
        $qualification = new Qualification();
        $funnels = Funnel::query()->orderBy('name')->get(['id', 'name']);

        return view('qualifications.create', compact('qualification', 'funnels'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'funnel_id' => 'nullable|integer|exists:funnels,id',
        ]);

        Qualification::create($validated);

        return redirect()
            ->route('qualifications.index')
            ->with('success', 'Qualification creada correctamente.');
    }

    public function show(Qualification $qualification)
    {
        $qualification->load('funnel:id,name');
        return view('qualifications.show', compact('qualification'));
    }

    public function edit(Qualification $qualification)
    {
        $funnels = Funnel::query()->orderBy('name')->get(['id', 'name']);

        return view('qualifications.edit', compact('qualification', 'funnels'));
    }

    public function update(Request $request, Qualification $qualification)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'funnel_id' => 'nullable|integer|exists:funnels,id',
        ]);

        $qualification->update($validated);

        return redirect()
            ->route('qualifications.index')
            ->with('success', 'Qualification actualizada correctamente.');
    }

    public function destroy(Qualification $qualification)
    {
        $qualification->delete();

        return redirect()
            ->route('qualifications.index')
            ->with('success', 'Qualification eliminada.');
    }
}
