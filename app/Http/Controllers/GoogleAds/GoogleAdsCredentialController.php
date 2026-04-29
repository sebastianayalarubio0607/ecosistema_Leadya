<?php

namespace App\Http\Controllers\GoogleAds;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleAds\GoogleAdsCredentialRequest;
use App\Http\Services\GoogleAds\GoogleAdsAuthService;
use App\Models\GoogleAdsCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoogleAdsCredentialController extends Controller
{
    public function index()
    {
        $activeCredential = GoogleAdsCredential::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();

        return view('google_ads.credentials.index', [
            'items' => GoogleAdsCredential::query()
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->paginate(15),
            'activeCredential' => $activeCredential,
        ]);
    }

    public function create()
    {
        $existing = GoogleAdsCredential::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return redirect()
                ->route('google-ads.credentials.edit', $existing)
                ->with('success', 'Ya existe una credencial global. Puedes editarla aquí.');
        }

        return view('google_ads.credentials.create', [
            'credential' => new GoogleAdsCredential([
                'is_active' => true,
            ]),
        ]);
    }

    public function store(GoogleAdsCredentialRequest $request): RedirectResponse
    {
        if ($existing = GoogleAdsCredential::query()->orderByDesc('is_active')->orderByDesc('id')->first()) {
            return redirect()
                ->route('google-ads.credentials.edit', $existing)
                ->withErrors([
                    'google_ads_credential' => 'Solo puede existir una credencial global de Google Ads.',
                ]);
        }

        $credential = DB::transaction(function () use ($request) {
            GoogleAdsCredential::query()->update(['is_active' => false]);

            return GoogleAdsCredential::create($request->validated());
        });

        return redirect()
            ->route('google-ads.credentials.show', $credential)
            ->with('success', 'Credencial de Google Ads creada correctamente.');
    }

    public function show(GoogleAdsCredential $credential)
    {
        return view('google_ads.credentials.show', compact('credential'));
    }

    public function edit(GoogleAdsCredential $credential)
    {
        return view('google_ads.credentials.edit', compact('credential'));
    }

    public function update(GoogleAdsCredentialRequest $request, GoogleAdsCredential $credential): RedirectResponse
    {
        $validated = $request->validated();

        foreach (GoogleAdsCredential::SECRET_FIELDS as $field) {
            if (! array_key_exists($field, $validated) || $validated[$field] === null || $validated[$field] === '') {
                unset($validated[$field]);
            }
        }

        DB::transaction(function () use ($credential, $validated) {
            if (($validated['is_active'] ?? false) === true) {
                GoogleAdsCredential::query()
                    ->whereKeyNot($credential->id)
                    ->update(['is_active' => false]);
            }

            $credential->fill($validated)->save();
        });

        return redirect()
            ->route('google-ads.credentials.show', $credential)
            ->with('success', 'Credencial de Google Ads actualizada correctamente.');
    }

    public function destroy(GoogleAdsCredential $credential): RedirectResponse
    {
        if (GoogleAdsCredential::query()->count() === 1) {
            return back()->withErrors([
                'google_ads_credential' => 'No puedes eliminar la única credencial global desde aquí. Edítala o desactívala si lo necesitas.',
            ]);
        }

        $credential->delete();

        return redirect()
            ->route('google-ads.credentials.index')
            ->with('success', 'Credencial de Google Ads eliminada correctamente.');
    }

    public function revealSecret(Request $request, GoogleAdsCredential $credential): JsonResponse
    {
        $field = (string) $request->input('field');

        abort_unless(in_array($field, GoogleAdsCredential::SECRET_FIELDS, true), 404);

        return response()->json([
            'value' => (string) $credential->{$field},
        ]);
    }

    public function refreshToken(GoogleAdsCredential $credential, GoogleAdsAuthService $authService): RedirectResponse
    {
        $refreshed = $authService->refreshAccessToken($credential);

        if (! $refreshed) {
            return back()->withErrors([
                'google_ads_refresh' => 'No fue posible refrescar el access token.',
            ]);
        }

        return back()->with('success', 'Access token refrescado correctamente.');
    }
}
