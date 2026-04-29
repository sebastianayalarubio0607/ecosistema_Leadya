<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MetaPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();

        $customers = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('fb_pixel_id', 'like', "%{$q}%")
                    ->orWhere('id_Gads', 'like', "%{$q}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function create()
    {
        return view('customers.create', [
            'metaPages' => MetaPage::query()->orderBy('name')->get(['id', 'name', 'meta_page_id', 'customer_id']),
            'selectedMetaPageIds' => old('meta_page_ids', []),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:0,1'],
            'fb_pixel_id' => ['nullable', 'string', 'max:255'],
            'fb_access_token' => ['nullable', 'string', 'max:255'],
            'id_Gads' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'meta_page_ids' => ['sometimes', 'array'],
            'meta_page_ids.*' => ['integer', 'exists:meta_pages,id'],
        ]);

        $metaPageIds = $data['meta_page_ids'] ?? [];
        unset($data['meta_page_ids']);
        $data['status'] = (int) $data['status'];

        // ✅ 1) Token plano (solo para mostrar una vez)
        $plainToken = Str::random(64);

        // ✅ 2) Token hasheado (es el que queda en BD)
        $data['token'] = hash('sha256', $plainToken);

        // ✅ 3) Crear customer
        $customer = DB::transaction(function () use ($data, $metaPageIds) {
            $customer = Customer::create($data);

            if (! empty($metaPageIds)) {
                MetaPage::query()
                    ->whereIn('id', $metaPageIds)
                    ->update(['customer_id' => $customer->id]);
            }

            return $customer;
        });

        // ✅ 4) Redirigir al show y enviar el token PLANO por sesión (flash)
        return redirect()
            ->route('customers.show', $customer)
            ->with('created_token', $plainToken)
            ->with('success', 'Customer creado correctamente. Copia el token ahora.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['metaPages' => fn ($query) => $query->orderBy('name')]);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $customer->load(['metaPages:id,customer_id,name,meta_page_id']);

        return view('customers.edit', [
            'customer' => $customer,
            'metaPages' => MetaPage::query()->orderBy('name')->get(['id', 'name', 'meta_page_id', 'customer_id']),
            'selectedMetaPageIds' => old('meta_page_ids', $customer->metaPages->pluck('id')->all()),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:0,1'],
            'fb_pixel_id' => ['nullable', 'string', 'max:255'],
            'fb_access_token' => ['nullable', 'string', 'max:255'],
            'id_Gads' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'regenerate_token' => ['nullable', 'boolean'],
            'meta_page_ids' => ['sometimes', 'array'],
            'meta_page_ids.*' => ['integer', 'exists:meta_pages,id'],
        ]);

        $metaPageIds = $data['meta_page_ids'] ?? [];
        unset($data['meta_page_ids']);
        $data['status'] = (int) $data['status'];

        if ($request->boolean('regenerate_token')) {
            $plainToken = Str::random(64);
            $data['token'] = hash('sha256', $plainToken);


            // Redirige a show y muestra la modal con el nuevo token
            DB::transaction(function () use ($customer, $data, $metaPageIds) {
                $customer->update($data);
                $this->syncCustomerMetaPages($customer, $metaPageIds);
            });

            return redirect()
                ->route('customers.show', $customer)
                ->with('created_token', $plainToken)
                ->with('success', 'Token regenerado. Copia el token ahora.');
        }

        unset($data['regenerate_token']);

        DB::transaction(function () use ($customer, $data, $metaPageIds) {
            $customer->update($data);
            $this->syncCustomerMetaPages($customer, $metaPageIds);
        });

        return redirect()->route('customers.show', $customer)->with('success', 'Customer actualizado correctamente.');

    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer eliminado correctamente.');
    }

    private function syncCustomerMetaPages(Customer $customer, array $metaPageIds): void
    {
        MetaPage::query()
            ->where('customer_id', $customer->id)
            ->when(! empty($metaPageIds), fn ($query) => $query->whereNotIn('id', $metaPageIds))
            ->update(['customer_id' => null]);

        if (! empty($metaPageIds)) {
            MetaPage::query()
                ->whereIn('id', $metaPageIds)
                ->update(['customer_id' => $customer->id]);
        }
    }
}
