<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SyncMetaLeadsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function receive(Request $request): JsonResponse
    {
        SyncMetaLeadsJob::dispatch();

        return response()->json(['received' => true], Response::HTTP_OK);
    }
}
