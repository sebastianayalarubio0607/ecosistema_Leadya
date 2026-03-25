<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meta\MetaAccessTokenRequest;
use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Jobs\RefreshMetaLongLivedTokenJob;
use App\Jobs\SyncMetaPagesJob;
use App\Models\MetaAccessToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MetaAccessTokenController extends Controller
{
    public function index(Request $request)
    {
        $items = MetaAccessToken::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('token_type', 'like', "%{$search}%")
                        ->orWhere('meta_app_id', 'like', "%{$search}%")
                        ->orWhere('last_error', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('token_type'), fn ($query) => $query->where('token_type', $request->string('token_type')->toString()))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('meta.access_tokens.index', [
            'items' => $items,
            'tokenTypes' => MetaAccessToken::availableTypes(),
        ]);
    }

    public function create()
    {
        return view('meta.access_tokens.create', [
            'accessToken' => new MetaAccessToken([
                'is_active' => true,
                'token_type' => MetaAccessToken::TYPE_USER_ACCESS_TOKEN,
            ]),
            'tokenTypes' => MetaAccessToken::availableTypes(),
        ]);
    }

    public function store(MetaAccessTokenRequest $request, MetaLeadAdsSyncService $service): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $accessToken = new MetaAccessToken(collect($validated)->except('short_lived_token')->all());
            $service->fillLongLivedToken($accessToken, (string) $validated['short_lived_token']);
            $accessToken->save();

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
            'accessToken' => $access_token,
        ]);
    }

    public function edit(MetaAccessToken $access_token)
    {
        return view('meta.access_tokens.edit', [
            'accessToken' => $access_token,
            'tokenTypes' => MetaAccessToken::availableTypes(),
        ]);
    }

    public function update(MetaAccessTokenRequest $request, MetaAccessToken $access_token, MetaLeadAdsSyncService $service): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $access_token->fill(collect($validated)->except('short_lived_token')->all());

            if (! empty($validated['short_lived_token'])) {
                $service->fillLongLivedToken($access_token, (string) $validated['short_lived_token']);
            }

            $access_token->save();

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
        RefreshMetaLongLivedTokenJob::dispatch($access_token->id);

        return back()->with('success', 'Refresco del token enviado a la cola.');
    }

    public function syncPages(MetaAccessToken $access_token): RedirectResponse
    {
        SyncMetaPagesJob::dispatch($access_token->id);

        return back()->with('success', 'Sincronización de páginas enviada a la cola.');
    }
}
