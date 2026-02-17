<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();

        $customers = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('fb_pixel_id', 'like', "%{$q}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:0,1'],
            'fb_pixel_id' => ['nullable', 'string', 'max:255'],
            'fb_access_token' => ['nullable', 'string', 'max:255'],
        ]);

        $data['status'] = (int) $data['status'];

        // ✅ 1) Token plano (solo para mostrar una vez)
        $plainToken = Str::random(64);

        // ✅ 2) Token hasheado (es el que queda en BD)
        $data['token'] = hash('sha256', $plainToken);

        // ✅ 3) Crear customer
        $customer = Customer::create($data);

        // ✅ 4) Redirigir al show y enviar el token PLANO por sesión (flash)
        return redirect()
            ->route('customers.show', $customer)
            ->with('created_token', $plainToken)
            ->with('success', 'Customer creado correctamente. Copia el token ahora.');
    }

    public function show(Customer $customer)
    {
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:0,1'],
            'fb_pixel_id' => ['nullable', 'string', 'max:255'],
            'fb_access_token' => ['nullable', 'string', 'max:255'],
            'regenerate_token' => ['nullable', 'boolean'],
        ]);

        $data['status'] = (int) $data['status'];

        if ($request->boolean('regenerate_token')) {
            $plainToken = Str::random(64);
            $data['token'] = hash('sha256', $plainToken);


            // Redirige a show y muestra la modal con el nuevo token
            $customer->update($data);

            return redirect()
                ->route('customers.show', $customer)
                ->with('created_token', $plainToken)
                ->with('success', 'Token regenerado. Copia el token ahora.');
        }

        unset($data['regenerate_token']);

        $customer->update($data);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer actualizado correctamente.');

    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer eliminado correctamente.');
    }
}
