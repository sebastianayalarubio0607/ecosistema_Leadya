<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SyncMetaLeadsJob;
use App\Services\Meta\MetaWebhookStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaLeadAdsWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = config('services.meta.verify_token');
        $requestToken = $request->query('hub_verify_token', $request->query('hub.verify_token'));

        if (blank($verifyToken) || ! hash_equals((string) $verifyToken, (string) $requestToken)) {
            return response('', Response::HTTP_FORBIDDEN);
        }

        return response((string) $request->query('hub_challenge', $request->query('hub.challenge')), Response::HTTP_OK);
    }

    public function receive(Request $request, MetaWebhookStorageService $metaWebhookStorageService): JsonResponse
    {
        try {
            $metaWebhookStorageService->storeFromRequest($request);
        } catch (\Throwable $exception) {
            Log::error('Meta webhook payload could not be stored', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        SyncMetaLeadsJob::dispatch();

        return response()->json(['received' => true], Response::HTTP_OK);
    }
}
