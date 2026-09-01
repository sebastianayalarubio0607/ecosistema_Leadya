<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Jobs\MetaWhatsappSubscriptionCheckJob;
use App\Models\Customer;
use App\Models\MetaAccessToken;
use App\Models\MetaWhatsapp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetaWhatsappController extends Controller
{
    public function index(Request $request)
    {
        $query = MetaWhatsapp::with(['customers', 'metaAccessToken', 'subscriptionMetaAccessToken']);

        if ($request->filled('customer_id')) {
            $query->whereHas('customers', fn ($customers) => $customers->whereKey($request->integer('customer_id')));
        }

        if ($request->filled('waba_id')) {
            $query->where('waba_id', 'like', '%'.$request->string('waba_id')->toString().'%');
        }

        if ($request->filled('phone_number_id')) {
            $query->where('phone_number_id', 'like', '%'.$request->string('phone_number_id')->toString().'%');
        }

        if ($request->filled('wa_id')) {
            $query->where('wa_id', 'like', '%'.$request->string('wa_id')->toString().'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('subscription')) {
            $query->where('is_subscribed_to_meta_app', $request->boolean('subscription'));
        }

        if ($request->filled('token_can_view_account')) {
            $tokenCanView = $request->string('token_can_view_account')->toString();

            if ($tokenCanView === 'unknown') {
                $query->whereNull('token_can_view_account');
            } else {
                $query->where('token_can_view_account', $tokenCanView === '1');
            }
        }

        if ($request->filled('meta_access_token_id')) {
            $query->where('meta_access_token_id', $request->integer('meta_access_token_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($innerQuery) use ($search) {
                $innerQuery->where('waba_id', 'like', "%{$search}%")
                    ->orWhere('phone_number_id', 'like', "%{$search}%")
                    ->orWhere('wa_id', 'like', "%{$search}%");
            });
        }

        return view('meta.whatsapps.index', [
            'items' => $query->orderByDesc('id')->paginate(15)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'whatsappAccessTokens' => $this->whatsappAccessTokens(),
        ]);
    }

    public function create(Request $request)
    {
        $selectedCustomerIds = [];

        if ($request->filled('customer_id') && Customer::query()->whereKey($request->integer('customer_id'))->exists()) {
            $selectedCustomerIds[] = $request->integer('customer_id');
        }

        return view('meta.whatsapps.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'whatsappAccessTokens' => $this->whatsappAccessTokens(),
            'whatsapp' => new MetaWhatsapp(['status' => true]),
            'selectedCustomerIds' => $selectedCustomerIds,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_ids' => ['sometimes', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'meta_access_token_id' => [
                'nullable',
                'integer',
                Rule::exists('meta_access_tokens', 'id')->where(function ($query) {
                    $query->where('purpose', MetaAccessToken::PURPOSE_WHATSAPP)
                        ->where('token_type', MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN);
                }),
            ],
            'waba_id' => ['required', 'string', 'max:64', 'unique:meta_whatsapps,waba_id'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'wa_id' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(['0', '1'])],
        ]);

        $customerIds = $data['customer_ids'] ?? [];
        unset($data['customer_ids']);
        $data['meta_access_token_id'] = $data['meta_access_token_id'] ?? null;
        $data['status'] = (bool) $data['status'];

        $whatsapp = MetaWhatsapp::create($data);
        $whatsapp->customers()->sync($customerIds);
        MetaWhatsappSubscriptionCheckJob::dispatch($whatsapp->id, $whatsapp->meta_access_token_id);

        return redirect()->route('meta.whatsapps.index')->with('success', 'Cuenta WhatsApp creada.');
    }

    public function show(MetaWhatsapp $whatsapp)
    {
        $whatsapp->load(['customers', 'metaAccessToken', 'subscriptionMetaAccessToken']);

        return view('meta.whatsapps.show', compact('whatsapp'));
    }

    public function edit(MetaWhatsapp $whatsapp)
    {
        $whatsapp->load('customers');

        return view('meta.whatsapps.edit', [
            'whatsapp' => $whatsapp,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'whatsappAccessTokens' => $this->whatsappAccessTokens(),
            'selectedCustomerIds' => old('customer_ids', $whatsapp->customers->pluck('id')->all()),
        ]);
    }

    public function update(Request $request, MetaWhatsapp $whatsapp)
    {
        $data = $request->validate([
            'customer_ids' => ['sometimes', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'meta_access_token_id' => [
                'nullable',
                'integer',
                Rule::exists('meta_access_tokens', 'id')->where(function ($query) {
                    $query->where('purpose', MetaAccessToken::PURPOSE_WHATSAPP)
                        ->where('token_type', MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN);
                }),
            ],
            'waba_id' => ['required', 'string', 'max:64', Rule::unique('meta_whatsapps', 'waba_id')->ignore($whatsapp->id)],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'wa_id' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(['0', '1'])],
        ]);

        $customerIds = $data['customer_ids'] ?? [];
        $originalCustomerIds = $whatsapp->customers()->pluck('customers.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        unset($data['customer_ids']);
        $data['meta_access_token_id'] = $data['meta_access_token_id'] ?? null;
        $data['status'] = (bool) $data['status'];

        $whatsapp->update($data);
        $whatsapp->customers()->sync($customerIds);

        $newCustomerIds = collect($customerIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($whatsapp->wasChanged('meta_access_token_id') || $originalCustomerIds !== $newCustomerIds) {
            MetaWhatsappSubscriptionCheckJob::dispatch($whatsapp->id, $whatsapp->meta_access_token_id);
        }

        return redirect()->route('meta.whatsapps.index')->with('success', 'Cuenta WhatsApp actualizada.');
    }

    public function destroy(MetaWhatsapp $whatsapp)
    {
        $whatsapp->delete();

        return redirect()->route('meta.whatsapps.index')->with('success', 'Cuenta WhatsApp eliminada.');
    }

    private function whatsappAccessTokens()
    {
        return MetaAccessToken::query()
            ->with('customer')
            ->whatsappSystemUsers()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderByDesc('id')
            ->get(MetaAccessToken::SYNC_COLUMNS);
    }
}
