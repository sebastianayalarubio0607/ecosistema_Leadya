<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Http\Services\Meta\MetaAdAccountSyncService;
use App\Http\Services\Meta\MetaAssetStatusSyncService;
use App\Jobs\SyncMetaAdAccountStatusJob;
use App\Jobs\SyncMetaAssetStatusesJob;
use App\Models\Customer;
use App\Models\MetaAdAccount;
use App\Support\MetaAdAccountId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MetaAdAccountController extends Controller
{
    public function index(Request $request)
    {
        $q = MetaAdAccount::with([
            'customers' => fn ($query) => $query->select(['customers.id', 'customers.name']),
        ]);

        if ($request->filled('customer_id')) {
            $q->whereHas('customers', fn ($customers) => $customers->whereKey($request->integer('customer_id')));
        }

        if ($request->filled('assignment_status')) {
            $assignmentStatus = $request->string('assignment_status')->toString();

            if ($assignmentStatus === 'assigned') {
                $q->has('customers');
            }

            if ($assignmentStatus === 'unassigned') {
                $q->doesntHave('customers');
            }
        }

        if ($request->filled('meta_account_id')) {
            $q->where('meta_account_id', 'like', '%'.$request->string('meta_account_id')->toString().'%');
        }

        if ($request->filled('name')) {
            $q->where('name', 'like', '%'.$request->string('name')->toString().'%');
        }

        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }

        if ($request->filled('estado_meta')) {
            $q->where('estado_meta', $request->string('estado_meta')->toString());
        }

        if ($request->filled('subscription')) {
            $q->where('is_subscribed_to_meta_app', $request->boolean('subscription'));
        }

        if ($request->filled('token_can_view_account')) {
            $tokenCanView = $request->string('token_can_view_account')->toString();

            if ($tokenCanView === 'unknown') {
                $q->whereNull('token_can_view_account');
            } else {
                $q->where('token_can_view_account', $tokenCanView === '1');
            }
        }

        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $q->where(function ($qq) use ($s) {
                $qq->where('meta_account_id', 'like', "%{$s}%")
                   ->orWhere('name', 'like', "%{$s}%")
                   ->orWhereHas('customers', fn ($customers) => $customers->where('name', 'like', "%{$s}%"));
            });
        }

        return view('meta.ad_accounts.index', [
            'items' => $q->orderByDesc('id')->paginate(15)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $selectedCustomerIds = $request->filled('customer_id')
            && Customer::query()->whereKey($request->integer('customer_id'))->exists()
                ? [$request->integer('customer_id')]
                : [];

        return view('meta.ad_accounts.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'ad_account' => new MetaAdAccount(['status' => 'active']),
            'selectedCustomerIds' => $selectedCustomerIds,
            'selectedDefaultWhatsappCustomerId' => $selectedCustomerIds[0] ?? null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_ids' => ['sometimes', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'default_whatsapp_lead_customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'meta_account_id' => ['required','string','max:64'],
            'name' => ['nullable','string','max:255'],
            'status' => ['required', Rule::in(['active','inactive'])],
        ]);

        [$customerIds, $defaultCustomerId] = $this->resolveCustomerSelection($request);
        $data['meta_account_id'] = MetaAdAccountId::normalize($data['meta_account_id']);
        $this->validateNormalizedMetaAccountId($data['meta_account_id']);
        $this->validateMetaAccountIdIsUnique($data['meta_account_id']);

        unset($data['customer_ids'], $data['default_whatsapp_lead_customer_id']);
        $data['customer_id'] = $defaultCustomerId ?? ($customerIds[0] ?? null);

        DB::transaction(function () use ($data, $customerIds, $defaultCustomerId): void {
            $account = MetaAdAccount::create($data);
            $account->syncCustomersWithWhatsappDefault($customerIds, $defaultCustomerId);
        });

        return redirect()->route('meta.ad-accounts.index')->with('success', 'Cuenta creada.');
    }

    public function show(MetaAdAccount $ad_account)
    {
        $ad_account->load([
            'customers' => fn ($query) => $query->select(['customers.id', 'customers.name']),
            'statusHistories' => fn ($query) => $query->with('webhookEvent:id,object,field')->orderByDesc('consulted_at')->limit(15),
        ]);

        return view('meta.ad_accounts.show', compact('ad_account'));
    }

    public function edit(MetaAdAccount $ad_account)
    {
        $ad_account->load([
            'customers' => fn ($query) => $query->select(['customers.id', 'customers.name']),
        ]);
        $selectedCustomerIds = $ad_account->customers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedDefaultWhatsappCustomerId = $ad_account->customers
            ->first(fn ($customer) => (bool) $customer->pivot->is_default_for_whatsapp_leads)?->id;

        return view('meta.ad_accounts.edit', [
            'ad_account' => $ad_account,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'selectedCustomerIds' => $selectedCustomerIds,
            'selectedDefaultWhatsappCustomerId' => $selectedDefaultWhatsappCustomerId,
        ]);
    }

    public function update(Request $request, MetaAdAccount $ad_account)
    {
        $data = $request->validate([
            'customer_ids' => ['sometimes', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'default_whatsapp_lead_customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'meta_account_id' => ['required','string','max:64'],
            'name' => ['nullable','string','max:255'],
            'status' => ['required', Rule::in(['active','inactive'])],
        ]);

        [$customerIds, $defaultCustomerId] = $this->resolveCustomerSelection($request);
        $data['meta_account_id'] = MetaAdAccountId::normalize($data['meta_account_id']);
        $this->validateNormalizedMetaAccountId($data['meta_account_id']);
        $this->validateMetaAccountIdIsUnique($data['meta_account_id'], (int) $ad_account->id);

        unset($data['customer_ids'], $data['default_whatsapp_lead_customer_id']);
        $data['customer_id'] = $defaultCustomerId ?? ($customerIds[0] ?? null);

        DB::transaction(function () use ($ad_account, $data, $customerIds, $defaultCustomerId): void {
            $ad_account->update($data);
            $ad_account->syncCustomersWithWhatsappDefault($customerIds, $defaultCustomerId);
        });

        return redirect()->route('meta.ad-accounts.index')->with('success', 'Cuenta actualizada.');
    }

    public function destroy(MetaAdAccount $ad_account)
    {
        if ($ad_account->customers()->exists()) {
            return back()->withErrors([
                'delete' => 'No puedes eliminar una cuenta publicitaria con clientes relacionados. Desvinculala de los clientes primero.',
            ]);
        }

        $ad_account->delete();
        return redirect()->route('meta.ad-accounts.index')->with('success', 'Cuenta eliminada.');
    }

    public function syncStatuses(): RedirectResponse
    {
        SyncMetaAssetStatusesJob::dispatch('manual', MetaAssetStatusSyncService::ASSET_TYPE_AD_ACCOUNTS);

        return back()->with('success', 'Consulta de estados de cuentas publicitarias enviada a la cola.');
    }

    public function syncStatus(MetaAdAccount $ad_account): RedirectResponse
    {
        SyncMetaAdAccountStatusJob::dispatch((int) $ad_account->id, 'manual');

        return back()->with('success', 'Consulta de estado de la cuenta publicitaria enviada a la cola.');
    }

    public function syncFromMeta(MetaAdAccountSyncService $service): RedirectResponse
    {
        try {
            $result = $service->syncAllAvailable();

            $message = 'Sincronizacion Meta Ads OK: '
                .$result['accounts_found'].' cuentas encontradas, '
                .$result['accounts_created'].' creadas, '
                .$result['accounts_updated'].' actualizadas.';

            if (! empty($result['errors'])) {
                $message .= ' Errores parciales: '.count($result['errors']).' (revisa logs).';
            }

            return back()->with('success', $message);
        } catch (\Throwable $exception) {
            return back()->withErrors(['sync' => $exception->getMessage()]);
        }
    }

    private function resolveCustomerSelection(Request $request): array
    {
        $customerIds = collect($request->input('customer_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $defaultCustomerId = $request->filled('default_whatsapp_lead_customer_id')
            ? $request->integer('default_whatsapp_lead_customer_id')
            : null;

        if ($customerIds->count() === 1) {
            $defaultCustomerId = $customerIds->first();
        }

        if ($customerIds->count() > 1 && (! $defaultCustomerId || ! $customerIds->contains($defaultCustomerId))) {
            throw ValidationException::withMessages([
                'default_whatsapp_lead_customer_id' => 'Selecciona el customer default para leads de WhatsApp de esta cuenta compartida.',
            ]);
        }

        return [$customerIds->all(), $defaultCustomerId];
    }

    private function validateMetaAccountIdIsUnique(string $metaAccountId, ?int $ignoreId = null): void
    {
        $exists = MetaAdAccount::query()
            ->whereIn('meta_account_id', MetaAdAccountId::candidates($metaAccountId))
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if (! $exists) {
            return;
        }

        throw ValidationException::withMessages([
            'meta_account_id' => 'Ya existe una cuenta publicitaria Meta con este ID.',
        ]);
    }

    private function validateNormalizedMetaAccountId(string $metaAccountId): void
    {
        if ($metaAccountId !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'meta_account_id' => 'Ingresa un Meta Account ID valido.',
        ]);
    }
}
