<?php

namespace App\Http\Services\AiConnectors;

use App\Models\AiConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiConnectorMcpServerService
{
    private const SUPPORTED_PROTOCOL_VERSIONS = [
        '2026-07-28',
        '2025-11-25',
        '2025-06-18',
        '2025-03-26',
    ];

    public function __construct(
        private readonly AiConnectorGeneralLeadsToolService $tools,
        private readonly AiConnectorQueryCacheService $cache,
        private readonly AiConnectorRateLimitService $rateLimit,
        private readonly AiConnectorQueryAuditService $audit,
    ) {}

    public function handle(Request $request, AiConnector $connector): Response
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || ! isset($payload['jsonrpc']) || $payload['jsonrpc'] !== '2.0') {
            return $this->error(null, -32600, 'Solicitud JSON-RPC invalida.', 400);
        }

        $transportError = $this->validateTransportHeaders($request, $payload);
        if ($transportError) {
            return $transportError;
        }

        $id = $payload['id'] ?? null;
        $method = (string) ($payload['method'] ?? '');
        $protocolVersion = $this->requestedProtocolVersion($request, $payload) ?: '2025-06-18';

        if (! array_key_exists('id', $payload)) {
            return response('', 202);
        }

        return match ($method) {
            'initialize' => $this->result($id, [
                'protocolVersion' => $protocolVersion,
                'capabilities' => [
                    'tools' => ['listChanged' => false],
                ],
                'serverInfo' => [
                    'name' => 'Leadsya Conectores IA',
                    'version' => '1.0.0',
                ],
                'instructions' => 'Este servidor MCP es de solo lectura y devuelve exclusivamente datos agregados de dashboard/general-leads.',
            ]),
            'ping' => $this->result($id, []),
            'tools/list' => $this->result($id, [
                'tools' => $this->tools->tools($connector),
            ]),
            'tools/call' => $this->callTool($request, $connector, $id, (array) ($payload['params'] ?? [])),
            default => $this->error($id, -32601, 'Metodo MCP no soportado.', 404),
        };
    }

    private function callTool(Request $request, AiConnector $connector, mixed $id, array $params): JsonResponse
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = (array) ($params['arguments'] ?? []);
        $startedAt = microtime(true);
        $queryHash = $this->cache->queryHash($name, $arguments);

        try {
            if ($name === '') {
                throw new \InvalidArgumentException('El nombre de la herramienta es requerido.');
            }

            if (! $connector->allowsTool($name)) {
                throw new \InvalidArgumentException('Esta herramienta no esta habilitada para el conector.');
            }

            $normalized = $this->tools->normalizeArguments(
                $connector,
                $arguments,
                $this->extraAllowedArguments($name)
            );

            $result = $this->cache->remember($connector, $name, $normalized, function () use ($connector, $name, $arguments) {
                $this->rateLimit->assertAllowed($connector);

                return $this->tools->call($connector, $name, $arguments);
            });

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->audit->log($connector, $name, $result['query_hash'], 'ok', $durationMs, $result['cache_hit'], $request);

            return $this->result($id, [
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($result['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
                'structuredContent' => $result['payload'],
                'isError' => false,
            ]);
        } catch (AiConnectorRateLimitException $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->audit->log($connector, $name ?: 'unknown', $queryHash, 'rate_limited', $durationMs, false, $request, $exception->getMessage());

            return $this->error($id, -32029, $exception->getMessage(), 429, [
                'retry_after' => $exception->retryAfter,
            ], ['Retry-After' => (string) $exception->retryAfter]);
        } catch (\Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->audit->log($connector, $name ?: 'unknown', $queryHash, 'error', $durationMs, false, $request, $exception->getMessage());

            return $this->result($id, [
                'content' => [[
                    'type' => 'text',
                    'text' => 'No fue posible ejecutar la consulta agregada: '.$exception->getMessage(),
                ]],
                'structuredContent' => [
                    'error' => $exception->getMessage(),
                ],
                'isError' => true,
            ]);
        }
    }

    private function validateTransportHeaders(Request $request, array $payload): ?JsonResponse
    {
        $protocol = $request->header('MCP-Protocol-Version');
        $bodyProtocol = $this->bodyProtocolVersion($payload);

        if ($protocol && $bodyProtocol && $protocol !== $bodyProtocol) {
            return $this->error($payload['id'] ?? null, -32600, 'La version MCP del header no coincide con la version del cuerpo.', 400);
        }

        $requestedProtocol = $protocol ?: $bodyProtocol;
        if ($requestedProtocol && ! in_array($requestedProtocol, self::SUPPORTED_PROTOCOL_VERSIONS, true)) {
            return $this->error($payload['id'] ?? null, -32001, 'Version de protocolo MCP no soportada.', 400, [
                'supported' => self::SUPPORTED_PROTOCOL_VERSIONS,
            ]);
        }

        $methodHeader = $request->header('Mcp-Method');
        if ($methodHeader && $methodHeader !== ($payload['method'] ?? null)) {
            return $this->error($payload['id'] ?? null, -32600, 'El header Mcp-Method no coincide con el metodo JSON-RPC.', 400);
        }

        if (($payload['method'] ?? null) === 'tools/call') {
            $nameHeader = $request->header('Mcp-Name');
            $name = data_get($payload, 'params.name');

            if ($nameHeader && $this->decodeHeaderValue($nameHeader) !== $name) {
                return $this->error($payload['id'] ?? null, -32600, 'El header Mcp-Name no coincide con la herramienta solicitada.', 400);
            }
        }

        return null;
    }

    private function requestedProtocolVersion(Request $request, array $payload): ?string
    {
        return $request->header('MCP-Protocol-Version') ?: $this->bodyProtocolVersion($payload);
    }

    private function bodyProtocolVersion(array $payload): ?string
    {
        $meta = data_get($payload, 'params._meta');
        $metaProtocol = is_array($meta)
            ? ($meta['io.modelcontextprotocol/protocolVersion'] ?? null)
            : null;

        return $metaProtocol ?: data_get($payload, 'params.protocolVersion');
    }

    private function extraAllowedArguments(string $tool): array
    {
        return match ($tool) {
            AiConnector::TOOL_SNAPSHOT => ['include_costs'],
            AiConnector::TOOL_AD_METRICS => ['section', 'sort', 'dir'],
            default => [],
        };
    }

    private function decodeHeaderValue(string $value): string
    {
        if (str_starts_with($value, '=?base64?') && str_ends_with($value, '?=')) {
            $decoded = base64_decode(substr($value, 10, -2), true);

            return $decoded === false ? $value : $decoded;
        }

        return $value;
    }

    private function result(mixed $id, array $result): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    private function error(mixed $id, int $code, string $message, int $status, array $data = [], array $headers = []): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== []) {
            $error['data'] = $data;
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ], $status, $headers);
    }
}
