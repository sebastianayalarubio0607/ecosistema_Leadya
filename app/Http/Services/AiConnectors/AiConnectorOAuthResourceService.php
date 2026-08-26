<?php

namespace App\Http\Services\AiConnectors;

use Illuminate\Http\Request;

class AiConnectorOAuthResourceService
{
    public function issuer(?Request $request = null): string
    {
        if ($request) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public function mcpResource(): string
    {
        return $this->canonicalize(route('api.ai-connectors.mcp', absolute: true));
    }

    public function protectedResourceMetadataUrl(): string
    {
        return route('ai-connectors.oauth.protected-resource', absolute: true);
    }

    public function isMcpResource(string $resource): bool
    {
        return hash_equals($this->mcpResource(), $this->canonicalize($resource));
    }

    public function canonicalize(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $path = (string) ($parts['path'] ?? '/');

        if ($path === '') {
            $path = '/';
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $authority = $host;
        if ($port && ! (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
            $authority .= ':'.$port;
        }

        $query = isset($parts['query']) && $parts['query'] !== ''
            ? '?'.$parts['query']
            : '';

        return $scheme.'://'.$authority.$path.$query;
    }
}
