<?php

namespace App\Http\Middleware\AiConnectors;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiConnectorMcpOriginGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        if (! $origin) {
            return $next($request);
        }

        $connector = $request->attributes->get('ai_connector');
        $allowed = $this->allowedOrigins((array) ($connector?->allowed_origins ?? []));

        if (! in_array($this->normalizeOrigin($origin), $allowed, true)) {
            return response()->json([
                'error' => 'forbidden_origin',
                'error_description' => 'El origen HTTP no esta autorizado para este conector IA.',
            ], 403);
        }

        return $next($request);
    }

    private function allowedOrigins(array $configured): array
    {
        $origins = collect($configured)
            ->map(fn ($origin) => $this->normalizeOrigin((string) $origin))
            ->filter()
            ->values();

        $appOrigin = $this->normalizeOrigin((string) config('app.url'));
        if ($appOrigin !== '') {
            $origins->push($appOrigin);
        }

        return $origins->unique()->values()->all();
    }

    private function normalizeOrigin(string $origin): string
    {
        $origin = trim($origin);
        if ($origin === '') {
            return '';
        }

        $parts = parse_url($origin);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $normalized = strtolower($parts['scheme']).'://'.strtolower($parts['host']);
        if (! empty($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        return $normalized;
    }
}
