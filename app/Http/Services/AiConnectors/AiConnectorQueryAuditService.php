<?php

namespace App\Http\Services\AiConnectors;

use App\Models\AiConnector;
use App\Models\AiConnectorQueryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiConnectorQueryAuditService
{
    public function log(
        AiConnector $connector,
        string $tool,
        string $queryHash,
        string $status,
        int $durationMs,
        bool $cacheHit,
        Request $request,
        ?string $errorMessage = null,
    ): void {
        AiConnectorQueryLog::query()->create([
            'ai_connector_id' => $connector->id,
            'tool_name' => $tool,
            'query_hash' => $queryHash,
            'status' => $status,
            'cache_hit' => $cacheHit,
            'duration_ms' => max(0, $durationMs),
            'ip_address' => (string) $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'error_message' => $errorMessage ? Str::limit($errorMessage, 2000, '') : null,
        ]);
    }
}
