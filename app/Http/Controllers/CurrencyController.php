<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));

        $currencies = Currency::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($innerQuery) use ($q) {
                    $innerQuery->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('currencies.index', compact('currencies', 'q'));
    }

    public function create(): View
    {
        return view('currencies.create', [
            'currency' => new Currency(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Currency::create($this->validatedPayload($request));

        return redirect()
            ->route('currencies.index')
            ->with('success', 'Divisa creada correctamente.');
    }

    public function show(Currency $currency): View
    {
        return view('currencies.show', compact('currency'));
    }

    public function edit(Currency $currency): View
    {
        return view('currencies.edit', compact('currency'));
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $currency->update($this->validatedPayload($request, $currency));

        return redirect()
            ->route('currencies.index')
            ->with('success', 'Divisa actualizada correctamente.');
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->customers()->exists()) {
            return back()->withErrors([
                'currency' => 'No se puede eliminar: hay customers usando esta divisa.',
            ]);
        }

        $currency->delete();

        return redirect()
            ->route('currencies.index')
            ->with('success', 'Divisa eliminada.');
    }

    private function validatedPayload(Request $request, ?Currency $currency = null): array
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies', 'code')->ignore($currency?->id),
            ],
            'status' => ['required', 'boolean'],
        ]);

        $payload['code'] = strtoupper(trim($payload['code']));
        $payload['status'] = (bool) $payload['status'];

        return $payload;
    }
}
