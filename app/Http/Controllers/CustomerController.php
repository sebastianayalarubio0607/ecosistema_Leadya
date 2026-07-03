<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Currency;
use App\Models\MetaPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    private const DEFAULT_CURRENCY_CODE = 'COP';
    private const DEFAULT_CURRENCY_NAME = 'Peso Colombiano';
    private const DEFAULT_LEAD_VALUE = 100000;

    public function index(Request $request)
    {
        $q = $request->string('q')->toString();

        $customers = Customer::query()
            ->with([
                'defaultCurrency:id,name,code',
                'metaAdAccounts:id,customer_id,meta_account_id',
                'metaPages:id,customer_id,name,meta_page_id',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($innerQuery) use ($q) {
                    $innerQuery->where('name', 'like', "%{$q}%")
                        ->orWhere('fb_pixel_id', 'like', "%{$q}%")
                        ->orWhere('id_Gads', 'like', "%{$q}%")
                        ->orWhereHas('metaAdAccounts', function ($accountQuery) use ($q) {
                            $accountQuery->where('meta_account_id', 'like', "%{$q}%")
                                ->orWhere('name', 'like', "%{$q}%");
                        });
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function create()
    {
        return view('customers.create', [
            'currencies' => $this->currencyOptions(),
            'defaultCurrencyId' => $this->defaultCurrencyId(),
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
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'default_lead_value' => ['nullable', 'numeric', 'min:0'],
            'meta_page_ids' => ['sometimes', 'array'],
            'meta_page_ids.*' => ['integer', 'exists:meta_pages,id'],
            'new_meta_ad_account' => ['sometimes', 'array'],
            'new_meta_ad_account.meta_account_id' => ['nullable', 'required_with:new_meta_ad_account.name', 'string', 'max:64', 'unique:meta_ad_accounts,meta_account_id'],
            'new_meta_ad_account.name' => ['nullable', 'string', 'max:255'],
            'new_meta_ad_account.status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $metaPageIds = $data['meta_page_ids'] ?? [];
        $newMetaAdAccount = $this->extractNewMetaAdAccountData($data['new_meta_ad_account'] ?? []);
        unset($data['meta_page_ids']);
        unset($data['new_meta_ad_account']);
        $data = $this->applyDefaultCustomerSettings($data);
        $data['status'] = (int) $data['status'];

        // ✅ 1) Token plano (solo para mostrar una vez)
        $plainToken = Str::random(64);

        // ✅ 2) Token hasheado (es el que queda en BD)
        $data['token'] = hash('sha256', $plainToken);

        // ✅ 3) Crear customer
        $customer = DB::transaction(function () use ($data, $metaPageIds, $newMetaAdAccount) {
            $customer = Customer::create($data);

            if (! empty($metaPageIds)) {
                MetaPage::query()
                    ->whereIn('id', $metaPageIds)
                    ->update(['customer_id' => $customer->id]);
            }

            $this->createCustomerMetaAdAccount($customer, $newMetaAdAccount);

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
        $customer->load([
            'defaultCurrency:id,name,code',
            'metaPages' => fn ($query) => $query->orderBy('name'),
            'metaAdAccounts' => fn ($query) => $query->orderBy('name')->orderBy('meta_account_id'),
        ]);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $customer->load([
            'metaPages:id,customer_id,name,meta_page_id',
            'metaAdAccounts' => fn ($query) => $query
                ->select('id', 'customer_id', 'meta_account_id', 'name', 'status')
                ->orderBy('name')
                ->orderBy('meta_account_id'),
        ]);

        return view('customers.edit', [
            'customer' => $customer,
            'currencies' => $this->currencyOptions(),
            'defaultCurrencyId' => $this->defaultCurrencyId(),
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
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'default_lead_value' => ['nullable', 'numeric', 'min:0'],
            'regenerate_token' => ['nullable', 'boolean'],
            'meta_page_ids' => ['sometimes', 'array'],
            'meta_page_ids.*' => ['integer', 'exists:meta_pages,id'],
            'new_meta_ad_account' => ['sometimes', 'array'],
            'new_meta_ad_account.meta_account_id' => ['nullable', 'required_with:new_meta_ad_account.name', 'string', 'max:64', 'unique:meta_ad_accounts,meta_account_id'],
            'new_meta_ad_account.name' => ['nullable', 'string', 'max:255'],
            'new_meta_ad_account.status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $metaPageIds = $data['meta_page_ids'] ?? [];
        $newMetaAdAccount = $this->extractNewMetaAdAccountData($data['new_meta_ad_account'] ?? []);
        unset($data['meta_page_ids']);
        unset($data['new_meta_ad_account']);
        $data = $this->applyDefaultCustomerSettings($data);
        $data['status'] = (int) $data['status'];

        if ($request->boolean('regenerate_token')) {
            $plainToken = Str::random(64);
            $data['token'] = hash('sha256', $plainToken);


            // Redirige a show y muestra la modal con el nuevo token
            DB::transaction(function () use ($customer, $data, $metaPageIds, $newMetaAdAccount) {
                $customer->update($data);
                $this->syncCustomerMetaPages($customer, $metaPageIds);
                $this->createCustomerMetaAdAccount($customer, $newMetaAdAccount);
            });

            return redirect()
                ->route('customers.show', $customer)
                ->with('created_token', $plainToken)
                ->with('success', 'Token regenerado. Copia el token ahora.');
        }

        unset($data['regenerate_token']);

        DB::transaction(function () use ($customer, $data, $metaPageIds, $newMetaAdAccount) {
            $customer->update($data);
            $this->syncCustomerMetaPages($customer, $metaPageIds);
            $this->createCustomerMetaAdAccount($customer, $newMetaAdAccount);
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

    private function extractNewMetaAdAccountData(array $payload): ?array
    {
        $metaAccountId = trim((string) ($payload['meta_account_id'] ?? ''));

        if ($metaAccountId === '') {
            return null;
        }

        return [
            'meta_account_id' => $metaAccountId,
            'name' => filled($payload['name'] ?? null) ? trim((string) $payload['name']) : null,
            'status' => $payload['status'] ?? 'active',
        ];
    }

    private function createCustomerMetaAdAccount(Customer $customer, ?array $data): void
    {
        if ($data === null) {
            return;
        }

        $customer->metaAdAccounts()->create($data);
    }

    private function currencyOptions()
    {
        $this->defaultCurrencyId();

        return Currency::query()
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'status']);
    }

    private function defaultCurrencyId(): int
    {
        return (int) Currency::query()->firstOrCreate(
            ['code' => self::DEFAULT_CURRENCY_CODE],
            [
                'name' => self::DEFAULT_CURRENCY_NAME,
                'status' => true,
            ]
        )->id;
    }

    private function applyDefaultCustomerSettings(array $data): array
    {
        if (empty($data['default_currency_id'])) {
            $data['default_currency_id'] = $this->defaultCurrencyId();
        }

        if (! array_key_exists('default_lead_value', $data)
            || $data['default_lead_value'] === null
            || $data['default_lead_value'] === ''
            || (float) $data['default_lead_value'] <= 0
        ) {
            $data['default_lead_value'] = self::DEFAULT_LEAD_VALUE;
        }

        return $data;
    }
}
