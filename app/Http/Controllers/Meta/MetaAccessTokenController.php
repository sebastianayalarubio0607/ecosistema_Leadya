<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meta\MetaAccessTokenRequest;
use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Jobs\RefreshMetaLongLivedTokenJob;
use App\Jobs\SyncMetaPagesJob;
use App\Models\Customer;
use App\Models\MetaAccessToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetaAccessTokenController extends Controller
{
    public function index(Request $request)
    {
        $items = MetaAccessToken::query()
            ->with('customer')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('token_type', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhere('meta_app_id', 'like', "%{$search}%")
                        ->orWhere('meta_business_id', 'like', "%{$search}%")
                        ->orWhere('meta_system_user_id', 'like', "%{$search}%")
                        ->orWhere('last_error', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('token_type'), fn ($query) => $query->where('token_type', $request->string('token_type')->toString()))
            ->when($request->filled('purpose'), fn ($query) => $query->where('purpose', $request->string('purpose')->toString()))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('meta.access_tokens.index', [
            'items' => $items,
            'tokenTypes' => MetaAccessToken::availableTypes(),
            'purposes' => MetaAccessToken::availablePurposes(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('meta.access_tokens.create', [
            'accessToken' => new MetaAccessToken([
                'is_active' => true,
                'is_default' => false,
                'purpose' => MetaAccessToken::PURPOSE_GENERAL,
                'token_type' => MetaAccessToken::TYPE_USER_ACCESS_TOKEN,
            ]),
            'tokenTypes' => MetaAccessToken::availableTypes(),
            'purposes' => MetaAccessToken::availablePurposes(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(MetaAccessTokenRequest $request, MetaLeadAdsSyncService $service): RedirectResponse
    {
        $validated = $this->normalizeTokenData($request->validated());

        try {
            $accessToken = new MetaAccessToken(collect($validated)->except('short_lived_token')->all());
            $service->fillLongLivedToken($accessToken, (string) $validated['short_lived_token']);

            DB::transaction(function () use ($accessToken) {
                $accessToken->save();
                $this->ensureSingleWhatsappDefault($accessToken);
            });

            return redirect()
                ->route('meta.access-tokens.show', $accessToken)
                ->with('success', 'Token Meta creado correctamente.');
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['meta_token' => $exception->getMessage()]);
        }
    }

    public function show(MetaAccessToken $access_token)
    {
        return view('meta.access_tokens.show', [
            'accessToken' => $access_token->load('customer'),
        ]);
    }

    public function edit(MetaAccessToken $access_token)
    {
        return view('meta.access_tokens.edit', [
            'accessToken' => $access_token,
            'tokenTypes' => MetaAccessToken::availableTypes(),
            'purposes' => MetaAccessToken::availablePurposes(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(MetaAccessTokenRequest $request, MetaAccessToken $access_token, MetaLeadAdsSyncService $service): RedirectResponse
    {
        $validated = $this->normalizeTokenData($request->validated());

        try {
            $access_token->fill(collect($validated)->except('short_lived_token')->all());

            if (! empty($validated['short_lived_token'])) {
                $service->fillLongLivedToken($access_token, (string) $validated['short_lived_token']);
            }

            DB::transaction(function () use ($access_token) {
                $access_token->save();
                $this->ensureSingleWhatsappDefault($access_token);
            });

            return redirect()
                ->route('meta.access-tokens.show', $access_token)
                ->with('success', 'Token Meta actualizado correctamente.');
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['meta_token' => $exception->getMessage()]);
        }
    }

    public function destroy(MetaAccessToken $access_token): RedirectResponse
    {
        $access_token->delete();

        return redirect()
            ->route('meta.access-tokens.index')
            ->with('success', 'Token Meta eliminado correctamente.');
    }

    public function refresh(MetaAccessToken $access_token): RedirectResponse
    {
        if ($access_token->purpose === MetaAccessToken::PURPOSE_WHATSAPP) {
            return back()->withErrors(['meta_token' => 'Los tokens WhatsApp de usuario del sistema no se refrescan desde este flujo global.']);
        }

        RefreshMetaLongLivedTokenJob::dispatch($access_token->id);

        return back()->with('success', 'Refresco del token enviado a la cola.');
    }

    public function syncPages(MetaAccessToken $access_token): RedirectResponse
    {
        if ($access_token->purpose === MetaAccessToken::PURPOSE_WHATSAPP) {
            return back()->withErrors(['meta_token' => 'Los tokens WhatsApp no se usan para sincronizar paginas de Lead Ads.']);
        }

        SyncMetaPagesJob::dispatch($access_token->id);

        return back()->with('success', 'Sincronización de páginas enviada a la cola.');
    }

    private function normalizeTokenData(array $validated): array
    {
        $validated['purpose'] = $validated['purpose'] ?? MetaAccessToken::PURPOSE_GENERAL;
        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['customer_id'] = $validated['customer_id'] ?? null;

        if ($validated['purpose'] !== MetaAccessToken::PURPOSE_WHATSAPP) {
            $validated['is_default'] = false;
        }

        return $validated;
    }

    private function ensureSingleWhatsappDefault(MetaAccessToken $accessToken): void
    {
        if (! $accessToken->isWhatsappSystemUser() || ! $accessToken->is_default) {
            return;
        }

        MetaAccessToken::query()
            ->whereKeyNot($accessToken->id)
            ->where('purpose', MetaAccessToken::PURPOSE_WHATSAPP)
            ->where('token_type', MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
