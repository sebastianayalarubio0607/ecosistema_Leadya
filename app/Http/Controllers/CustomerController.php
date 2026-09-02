<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureGoogleAdsConversionTemplatesJob;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\MetaAdAccount;
use App\Models\MetaPage;
use App\Models\MetaWhatsapp;
use App\Support\MetaAdAccountId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                'metaAdAccounts' => fn ($query) => $query->select([
                    'meta_ad_accounts.id',
                    'meta_ad_accounts.customer_id',
                    'meta_ad_accounts.meta_account_id',
                    'meta_ad_accounts.name',
                    'meta_ad_accounts.is_subscribed_to_meta_app',
                ]),
                'metaAdAccounts.customers' => fn ($query) => $query->select(['customers.id', 'customers.name']),
                'metaPages:id,customer_id,name,meta_page_id,is_leadgen_subscribed',
                'metaWhatsapps' => fn ($query) => $query->select([
                    'meta_whatsapps.id',
                    'meta_whatsapps.waba_id',
                    'meta_whatsapps.phone_number_id',
                    'meta_whatsapps.wa_id',
                    'meta_whatsapps.is_subscribed_to_meta_app',
                ]),
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($innerQuery) use ($q) {
                    $innerQuery->where('name', 'like', "%{$q}%")
                        ->orWhere('fb_pixel_id', 'like', "%{$q}%")
                        ->orWhere('Meta_dataset_id', 'like', "%{$q}%")
                        ->orWhere('Meta_whatsapp_dataset_id', 'like', "%{$q}%")
                        ->orWhere('id_Gads', 'like', "%{$q}%")
                        ->orWhereHas('metaAdAccounts', function ($accountQuery) use ($q) {
                            $accountQuery->where('meta_account_id', 'like', "%{$q}%")
                                ->orWhere('name', 'like', "%{$q}%");
                        })
                        ->orWhereHas('metaWhatsapps', function ($whatsappQuery) use ($q) {
                            $whatsappQuery->where('waba_id', 'like', "%{$q}%")
                                ->orWhere('phone_number_id', 'like', "%{$q}%")
                                ->orWhere('wa_id', 'like', "%{$q}%");
                        });
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function create()
    {
        return view('customers.create', [
            'currencies' => $this->currencyOptions(),
            'defaultCurrencyId' => $this->defaultCurrencyId(),
            'metaPages' => MetaPage::query()->orderBy('name')->get(['id', 'name', 'meta_page_id', 'customer_id', 'is_leadgen_subscribed']),
            'selectedMetaPageIds' => old('meta_page_ids', []),
            'metaAdAccounts' => $this->metaAdAccountOptions(),
            'selectedMetaAdAccountIds' => old('meta_ad_account_ids', []),
            'defaultMetaAdAccountIds' => old('default_meta_ad_account_ids', []),
            'metaWhatsapps' => MetaWhatsapp::query()->orderBy('waba_id')->get(['id', 'waba_id', 'phone_number_id', 'wa_id', 'is_subscribed_to_meta_app']),
            'selectedMetaWhatsappIds' => old('meta_whatsapp_ids', []),
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
            'fb_test_event_code' => ['nullable', 'string', 'max:255'],
            'Meta_dataset' => ['nullable', 'boolean'],
            'Meta_dataset_id' => ['nullable', 'string', 'max:255'],
            'Meta_dataset_token' => ['nullable', 'string', 'max:500'],
            'Meta_whatsapp_dataset' => ['nullable', 'boolean'],
            'Meta_whatsapp_dataset_id' => ['nullable', 'string', 'max:255'],
            'Meta_whatsapp_dataset_token' => ['nullable', 'string', 'max:500'],
            'id_Gads' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'default_lead_value' => ['nullable', 'numeric', 'min:0'],
            'meta_page_ids' => ['sometimes', 'array'],
            'meta_page_ids.*' => ['integer', 'exists:meta_pages,id'],
            'meta_ad_account_ids' => ['sometimes', 'array'],
            'meta_ad_account_ids.*' => ['integer', 'exists:meta_ad_accounts,id'],
            'default_meta_ad_account_ids' => ['sometimes', 'array'],
            'default_meta_ad_account_ids.*' => ['integer', 'exists:meta_ad_accounts,id'],
            'meta_whatsapp_ids' => ['sometimes', 'array'],
            'meta_whatsapp_ids.*' => ['integer', 'exists:meta_whatsapps,id'],
            'new_meta_ad_account' => ['sometimes', 'array'],
            'new_meta_ad_account.meta_account_id' => ['nullable', 'required_with:new_meta_ad_account.name', 'string', 'max:64'],
            'new_meta_ad_account.name' => ['nullable', 'string', 'max:255'],
            'new_meta_ad_account.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'new_meta_ad_account.default_for_whatsapp_leads' => ['nullable', 'boolean'],
            'new_meta_whatsapp' => ['sometimes', 'array'],
            'new_meta_whatsapp.waba_id' => ['nullable', 'required_with:new_meta_whatsapp.phone_number_id,new_meta_whatsapp.wa_id', 'string', 'max:64', 'unique:meta_whatsapps,waba_id'],
            'new_meta_whatsapp.phone_number_id' => ['nullable', 'string', 'max:64'],
            'new_meta_whatsapp.wa_id' => ['nullable', 'string', 'max:64'],
            'new_meta_whatsapp.status' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $metaPageIds = $data['meta_page_ids'] ?? [];
        $metaAdAccountIds = $data['meta_ad_account_ids'] ?? [];
        $defaultMetaAdAccountIds = $data['default_meta_ad_account_ids'] ?? [];
        $metaWhatsappIds = $data['meta_whatsapp_ids'] ?? [];
        $newMetaAdAccount = $this->extractNewMetaAdAccountData($data['new_meta_ad_account'] ?? []);
        $newMetaWhatsapp = $this->extractNewMetaWhatsappData($data['new_meta_whatsapp'] ?? []);
        $this->validateCustomerMetaAdAccountDefaults(null, $metaAdAccountIds, $defaultMetaAdAccountIds);
        unset($data['meta_page_ids']);
        unset($data['meta_ad_account_ids']);
        unset($data['default_meta_ad_account_ids']);
        unset($data['meta_whatsapp_ids']);
        unset($data['new_meta_ad_account']);
        unset($data['new_meta_whatsapp']);
        $data['Meta_dataset'] = $request->boolean('Meta_dataset');
        $data['Meta_whatsapp_dataset'] = $request->boolean('Meta_whatsapp_dataset');
        $data = $this->applyDefaultCustomerSettings($data);
        $data['status'] = (int) $data['status'];

        // ✅ 1) Token plano (solo para mostrar una vez)
        $plainToken = Str::random(64);

        // ✅ 2) Token hasheado (es el que queda en BD)
        $data['token'] = hash('sha256', $plainToken);

        // ✅ 3) Crear customer
        $customer = DB::transaction(function () use ($data, $metaPageIds, $metaAdAccountIds, $defaultMetaAdAccountIds, $metaWhatsappIds, $newMetaAdAccount, $newMetaWhatsapp) {
            $customer = Customer::create($data);

            if (! empty($metaPageIds)) {
                MetaPage::query()
                    ->whereIn('id', $metaPageIds)
                    ->update(['customer_id' => $customer->id]);
            }

            $this->syncCustomerMetaAdAccounts($customer, $metaAdAccountIds, $defaultMetaAdAccountIds);
            $this->createCustomerMetaAdAccount($customer, $newMetaAdAccount);
            $this->syncCustomerMetaWhatsapps($customer, $metaWhatsappIds);
            $this->createCustomerMetaWhatsapp($customer, $newMetaWhatsapp);

            return $customer;
        });

        $this->dispatchGoogleAdsTemplateSync($customer);

        // ✅ 4) Redirigir al show y enviar el token PLANO por sesión (flash)
        return redirect()
            ->route('customers.show', $customer)
            ->with('created_token', $plainToken)
            ->with('success', 'Customer creado correctamente. Copia el token ahora.'.$this->googleAdsQueueMessage($customer));
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'defaultCurrency:id,name,code',
            'metaPages' => fn ($query) => $query->orderBy('name'),
            'metaAdAccounts' => fn ($query) => $query->orderBy('name')->orderBy('meta_account_id'),
            'metaAdAccounts.customers' => fn ($query) => $query->select(['customers.id', 'customers.name']),
            'metaWhatsapps' => fn ($query) => $query->orderBy('waba_id'),
            'actionHistories' => fn ($query) => $query->latest('id')->limit(20),
            'googleAdsConversionTemplateHistories' => fn ($query) => $query->latest('id')->limit(20),
        ]);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $customer->load([
            'metaPages:id,customer_id,name,meta_page_id,is_leadgen_subscribed',
            'metaAdAccounts' => fn ($query) => $query
                ->select([
                    'meta_ad_accounts.id',
                    'meta_ad_accounts.customer_id',
                    'meta_ad_accounts.meta_account_id',
                    'meta_ad_accounts.name',
                    'meta_ad_accounts.status',
                    'meta_ad_accounts.is_subscribed_to_meta_app',
                    'meta_ad_accounts.token_can_view_account',
                ])
                ->orderBy('meta_ad_accounts.name')
                ->orderBy('meta_ad_accounts.meta_account_id'),
            'metaAdAccounts.customers' => fn ($query) => $query->select(['customers.id', 'customers.name']),
            'metaWhatsapps' => fn ($query) => $query
                ->select('meta_whatsapps.id', 'waba_id', 'phone_number_id', 'wa_id', 'status', 'is_subscribed_to_meta_app', 'token_can_view_account')
                ->orderBy('waba_id'),
        ]);

        return view('customers.edit', [
            'customer' => $customer,
            'currencies' => $this->currencyOptions(),
            'defaultCurrencyId' => $this->defaultCurrencyId(),
            'metaPages' => MetaPage::query()->orderBy('name')->get(['id', 'name', 'meta_page_id', 'customer_id', 'is_leadgen_subscribed']),
            'selectedMetaPageIds' => old('meta_page_ids', $customer->metaPages->pluck('id')->all()),
            'metaAdAccounts' => $this->metaAdAccountOptions(),
            'selectedMetaAdAccountIds' => old('meta_ad_account_ids', $customer->metaAdAccounts->pluck('id')->all()),
            'defaultMetaAdAccountIds' => old('default_meta_ad_account_ids', $customer->metaAdAccounts
                ->filter(fn ($account) => (bool) $account->pivot->is_default_for_whatsapp_leads)
                ->pluck('id')
                ->all()),
            'metaWhatsapps' => MetaWhatsapp::query()->orderBy('waba_id')->get(['id', 'waba_id', 'phone_number_id', 'wa_id', 'is_subscribed_to_meta_app']),
            'selectedMetaWhatsappIds' => old('meta_whatsapp_ids', $customer->metaWhatsapps->pluck('id')->all()),
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
            'fb_test_event_code' => ['nullable', 'string', 'max:255'],
            'Meta_dataset' => ['nullable', 'boolean'],
            'Meta_dataset_id' => ['nullable', 'string', 'max:255'],
            'Meta_dataset_token' => ['nullable', 'string', 'max:500'],
            'Meta_whatsapp_dataset' => ['nullable', 'boolean'],
            'Meta_whatsapp_dataset_id' => ['nullable', 'string', 'max:255'],
            'Meta_whatsapp_dataset_token' => ['nullable', 'string', 'max:500'],
            'id_Gads' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'default_lead_value' => ['nullable', 'numeric', 'min:0'],
            'regenerate_token' => ['nullable', 'boolean'],
            'meta_page_ids' => ['sometimes', 'array'],
            'meta_page_ids.*' => ['integer', 'exists:meta_pages,id'],
            'meta_ad_account_ids' => ['sometimes', 'array'],
            'meta_ad_account_ids.*' => ['integer', 'exists:meta_ad_accounts,id'],
            'default_meta_ad_account_ids' => ['sometimes', 'array'],
            'default_meta_ad_account_ids.*' => ['integer', 'exists:meta_ad_accounts,id'],
            'meta_whatsapp_ids' => ['sometimes', 'array'],
            'meta_whatsapp_ids.*' => ['integer', 'exists:meta_whatsapps,id'],
            'new_meta_ad_account' => ['sometimes', 'array'],
            'new_meta_ad_account.meta_account_id' => ['nullable', 'required_with:new_meta_ad_account.name', 'string', 'max:64'],
            'new_meta_ad_account.name' => ['nullable', 'string', 'max:255'],
            'new_meta_ad_account.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'new_meta_ad_account.default_for_whatsapp_leads' => ['nullable', 'boolean'],
            'new_meta_whatsapp' => ['sometimes', 'array'],
            'new_meta_whatsapp.waba_id' => ['nullable', 'required_with:new_meta_whatsapp.phone_number_id,new_meta_whatsapp.wa_id', 'string', 'max:64', 'unique:meta_whatsapps,waba_id'],
            'new_meta_whatsapp.phone_number_id' => ['nullable', 'string', 'max:64'],
            'new_meta_whatsapp.wa_id' => ['nullable', 'string', 'max:64'],
            'new_meta_whatsapp.status' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $metaPageIds = $data['meta_page_ids'] ?? [];
        $metaAdAccountIds = $data['meta_ad_account_ids'] ?? [];
        $defaultMetaAdAccountIds = $data['default_meta_ad_account_ids'] ?? [];
        $metaWhatsappIds = $data['meta_whatsapp_ids'] ?? [];
        $newMetaAdAccount = $this->extractNewMetaAdAccountData($data['new_meta_ad_account'] ?? []);
        $newMetaWhatsapp = $this->extractNewMetaWhatsappData($data['new_meta_whatsapp'] ?? []);
        $this->validateCustomerMetaAdAccountDefaults($customer->id, $metaAdAccountIds, $defaultMetaAdAccountIds);
        unset($data['meta_page_ids']);
        unset($data['meta_ad_account_ids']);
        unset($data['default_meta_ad_account_ids']);
        unset($data['meta_whatsapp_ids']);
        unset($data['new_meta_ad_account']);
        unset($data['new_meta_whatsapp']);
        $data['Meta_dataset'] = $request->boolean('Meta_dataset');
        $data['Meta_whatsapp_dataset'] = $request->boolean('Meta_whatsapp_dataset');
        $data = $this->applyDefaultCustomerSettings($data);
        $data['status'] = (int) $data['status'];
        $regenerateToken = $request->boolean('regenerate_token');
        unset($data['regenerate_token']);

        if ($regenerateToken) {
            $plainToken = Str::random(64);
            $data['token'] = hash('sha256', $plainToken);


            // Redirige a show y muestra la modal con el nuevo token
            DB::transaction(function () use ($customer, $data, $metaPageIds, $metaAdAccountIds, $defaultMetaAdAccountIds, $metaWhatsappIds, $newMetaAdAccount, $newMetaWhatsapp) {
                $customer->update($data);
                $this->syncCustomerMetaPages($customer, $metaPageIds);
                $this->syncCustomerMetaAdAccounts($customer, $metaAdAccountIds, $defaultMetaAdAccountIds);
                $this->syncCustomerMetaWhatsapps($customer, $metaWhatsappIds);
                $this->createCustomerMetaAdAccount($customer, $newMetaAdAccount);
                $this->createCustomerMetaWhatsapp($customer, $newMetaWhatsapp);
            });

            $this->dispatchGoogleAdsTemplateSync($customer);

            return redirect()
                ->route('customers.show', $customer)
                ->with('created_token', $plainToken)
                ->with('success', 'Token regenerado. Copia el token ahora.'.$this->googleAdsQueueMessage($customer));
        }

        DB::transaction(function () use ($customer, $data, $metaPageIds, $metaAdAccountIds, $defaultMetaAdAccountIds, $metaWhatsappIds, $newMetaAdAccount, $newMetaWhatsapp) {
            $customer->update($data);
            $this->syncCustomerMetaPages($customer, $metaPageIds);
            $this->syncCustomerMetaAdAccounts($customer, $metaAdAccountIds, $defaultMetaAdAccountIds);
            $this->syncCustomerMetaWhatsapps($customer, $metaWhatsappIds);
            $this->createCustomerMetaAdAccount($customer, $newMetaAdAccount);
            $this->createCustomerMetaWhatsapp($customer, $newMetaWhatsapp);
        });

        $this->dispatchGoogleAdsTemplateSync($customer);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer actualizado correctamente.'.$this->googleAdsQueueMessage($customer));

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

        $metaAccountId = MetaAdAccountId::normalize($metaAccountId);

        if ($metaAccountId === '') {
            throw ValidationException::withMessages([
                'new_meta_ad_account.meta_account_id' => 'Ingresa un Meta Account ID valido.',
            ]);
        }

        return [
            'meta_account_id' => $metaAccountId,
            'name' => filled($payload['name'] ?? null) ? trim((string) $payload['name']) : null,
            'status' => $payload['status'] ?? 'active',
            'default_for_whatsapp_leads' => (bool) ($payload['default_for_whatsapp_leads'] ?? false),
        ];
    }

    private function extractNewMetaWhatsappData(array $payload): ?array
    {
        $wabaId = trim((string) ($payload['waba_id'] ?? ''));

        if ($wabaId === '') {
            return null;
        }

        return [
            'waba_id' => $wabaId,
            'phone_number_id' => filled($payload['phone_number_id'] ?? null) ? trim((string) $payload['phone_number_id']) : null,
            'wa_id' => filled($payload['wa_id'] ?? null) ? trim((string) $payload['wa_id']) : null,
            'status' => (bool) ($payload['status'] ?? true),
        ];
    }

    private function createCustomerMetaAdAccount(Customer $customer, ?array $data): void
    {
        if ($data === null) {
            return;
        }

        $defaultForWhatsappLeads = (bool) ($data['default_for_whatsapp_leads'] ?? false);
        unset($data['default_for_whatsapp_leads']);

        $metaAccountId = (string) $data['meta_account_id'];
        $account = $this->findMetaAdAccountByExternalId($metaAccountId);

        if (! $account) {
            $data['customer_id'] = $customer->id;
            $account = MetaAdAccount::create($data);
        } else {
            $this->validateExistingMetaAdAccountAttachment($customer, $account, $defaultForWhatsappLeads);

            $updates = collect($data)
                ->except(['meta_account_id'])
                ->filter(fn ($value) => filled($value))
                ->all();

            if ($updates !== []) {
                $account->fill($updates)->save();
            }
        }

        $customer->metaAdAccounts()->syncWithoutDetaching([
            $account->id => ['is_default_for_whatsapp_leads' => $defaultForWhatsappLeads],
        ]);

        $defaultCustomerId = $account->ensureWhatsappDefaultCustomer($defaultForWhatsappLeads ? $customer->id : null);
        $account->forceFill(['customer_id' => $defaultCustomerId])->saveQuietly();
    }

    private function syncCustomerMetaAdAccounts(Customer $customer, array $metaAdAccountIds, array $defaultMetaAdAccountIds): void
    {
        $metaAdAccountIds = collect($metaAdAccountIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $defaultMetaAdAccountIds = collect($defaultMetaAdAccountIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->intersect($metaAdAccountIds)
            ->unique()
            ->values();

        $currentIds = $customer->metaAdAccounts()
            ->pluck('meta_ad_accounts.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $detachedIds = array_values(array_diff($currentIds, $metaAdAccountIds->all()));
        $customer->metaAdAccounts()->detach($detachedIds);

        foreach ($metaAdAccountIds as $accountId) {
            $customer->metaAdAccounts()->syncWithoutDetaching([
                $accountId => [
                    'is_default_for_whatsapp_leads' => $defaultMetaAdAccountIds->contains($accountId),
                ],
            ]);
        }

        $touchedIds = array_values(array_unique(array_merge($detachedIds, $metaAdAccountIds->all())));

        if ($touchedIds === []) {
            return;
        }

        MetaAdAccount::query()
            ->whereIn('id', $touchedIds)
            ->get()
            ->each(function (MetaAdAccount $account) use ($customer, $defaultMetaAdAccountIds): void {
                $preferredCustomerId = $defaultMetaAdAccountIds->contains((int) $account->id) ? $customer->id : null;
                $defaultCustomerId = $account->ensureWhatsappDefaultCustomer($preferredCustomerId);

                $account->forceFill(['customer_id' => $defaultCustomerId])->saveQuietly();
            });
    }

    private function validateCustomerMetaAdAccountDefaults(?int $customerId, array $metaAdAccountIds, array $defaultMetaAdAccountIds): void
    {
        $metaAdAccountIds = collect($metaAdAccountIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($metaAdAccountIds->isEmpty()) {
            return;
        }

        $defaultMetaAdAccountIds = collect($defaultMetaAdAccountIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->intersect($metaAdAccountIds)
            ->unique()
            ->values();

        $accounts = MetaAdAccount::query()
            ->with(['customers' => fn ($query) => $query->select(['customers.id', 'customers.name'])])
            ->whereIn('id', $metaAdAccountIds->all())
            ->get(['id', 'meta_account_id', 'name']);

        foreach ($accounts as $account) {
            $otherCustomers = $account->customers
                ->reject(fn ($relatedCustomer) => $customerId !== null && (int) $relatedCustomer->id === (int) $customerId);

            if ($otherCustomers->count() < 1) {
                continue;
            }

            if ($defaultMetaAdAccountIds->contains((int) $account->id)) {
                continue;
            }

            $hasDefaultOutsideThisCustomer = $otherCustomers
                ->contains(fn ($relatedCustomer) => (bool) $relatedCustomer->pivot->is_default_for_whatsapp_leads);

            if ($hasDefaultOutsideThisCustomer) {
                continue;
            }

            throw ValidationException::withMessages([
                'default_meta_ad_account_ids' => 'La cuenta Meta '.($account->name ?: $account->meta_account_id).' quedaria compartida sin customer default de WhatsApp. Marca este customer como default o define el default desde la cuenta publicitaria.',
            ]);
        }
    }

    private function validateExistingMetaAdAccountAttachment(
        Customer $customer,
        MetaAdAccount $account,
        bool $defaultForWhatsappLeads,
    ): void {
        if ($defaultForWhatsappLeads) {
            return;
        }

        $otherCustomers = $account->customers()
            ->whereKeyNot($customer->id)
            ->get(['customers.id', 'customers.name']);

        if ($otherCustomers->isEmpty()) {
            return;
        }

        $hasDefaultOutsideThisCustomer = $otherCustomers
            ->contains(fn ($relatedCustomer) => (bool) $relatedCustomer->pivot->is_default_for_whatsapp_leads);

        if ($hasDefaultOutsideThisCustomer) {
            return;
        }

        throw ValidationException::withMessages([
            'new_meta_ad_account.default_for_whatsapp_leads' => 'La cuenta Meta '.($account->name ?: $account->meta_account_id).' ya existe y quedaria compartida sin customer default de WhatsApp. Marca este customer como default o define el default desde la cuenta publicitaria.',
        ]);
    }

    private function syncCustomerMetaWhatsapps(Customer $customer, array $metaWhatsappIds): void
    {
        $customer->metaWhatsapps()->sync($metaWhatsappIds);
    }

    private function createCustomerMetaWhatsapp(Customer $customer, ?array $data): void
    {
        if ($data === null) {
            return;
        }

        $whatsapp = MetaWhatsapp::create($data);
        $customer->metaWhatsapps()->syncWithoutDetaching([$whatsapp->id]);
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

    private function dispatchGoogleAdsTemplateSync(Customer $customer): void
    {
        if (trim((string) $customer->id_Gads) === '') {
            return;
        }

        $user = auth()->user();

        EnsureGoogleAdsConversionTemplatesJob::dispatch(
            $customer->id,
            'user',
            $user?->id,
            $user?->name
        );
    }

    private function googleAdsQueueMessage(Customer $customer): string
    {
        return trim((string) $customer->id_Gads) !== ''
            ? ' La revision de plantillas Google Ads quedo en cola tracking.'
            : '';
    }

    private function metaAdAccountOptions()
    {
        return MetaAdAccount::query()
            ->with(['customers' => fn ($query) => $query->select(['customers.id', 'customers.name'])])
            ->orderBy('name')
            ->orderBy('meta_account_id')
            ->get(['id', 'meta_account_id', 'name', 'status', 'is_subscribed_to_meta_app', 'token_can_view_account']);
    }

    private function findMetaAdAccountByExternalId(string $metaAccountId): ?MetaAdAccount
    {
        return MetaAdAccount::query()
            ->whereIn('meta_account_id', MetaAdAccountId::candidates($metaAccountId))
            ->orderBy('id')
            ->first();
    }
}
