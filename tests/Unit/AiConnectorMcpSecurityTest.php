<?php

namespace Tests\Unit;

use App\Http\Services\AiConnectors\AiConnectorGeneralLeadsToolService;
use App\Http\Services\AiConnectors\AiConnectorMcpServerService;
use App\Http\Services\AiConnectors\AiConnectorPayloadSanitizer;
use App\Http\Services\AiConnectors\AiConnectorQueryAuditService;
use App\Http\Services\AiConnectors\AiConnectorQueryCacheService;
use App\Http\Services\AiConnectors\AiConnectorRateLimitService;
use App\Http\Services\GeneralLeads\GeneralLeadsDashboardService;
use App\Http\Services\GeneralLeads\GeneralLeadsLeadQuery;
use App\Http\Middleware\AiConnectors\AiConnectorMcpOriginGuard;
use App\Models\AiConnector;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AiConnectorMcpSecurityTest extends TestCase
{
    public function test_mcp_tools_are_read_only_and_hide_ad_tools_when_disabled(): void
    {
        $connector = new AiConnector([
            'allowed_tools' => array_keys(AiConnector::AVAILABLE_TOOLS),
            'allow_ad_metrics' => false,
        ]);

        $tools = $this->toolService()->tools($connector);
        $names = array_column($tools, 'name');

        $this->assertNotContains(AiConnector::TOOL_COSTS, $names);
        $this->assertNotContains(AiConnector::TOOL_AD_METRICS, $names);

        foreach ($names as $name) {
            $this->assertStringNotContainsString('create', $name);
            $this->assertStringNotContainsString('update', $name);
            $this->assertStringNotContainsString('delete', $name);
            $this->assertStringNotContainsString('list_leads', $name);
            $this->assertStringNotContainsString('export', $name);
        }
    }

    public function test_sanitizer_removes_sensitive_lead_fields_and_urls_recursively(): void
    {
        $payload = [
            'summary' => ['total' => 10, 'urls' => ['total' => '/dashboard/general-leads/list']],
            'rows' => [
                [
                    'name' => 'Source Seguro',
                    'total' => 10,
                    'email' => 'persona@example.com',
                    'phone' => '555',
                    'remote_ip' => '127.0.0.1',
                    'meta_payload' => ['raw' => true],
                    'gclid' => 'secret-click-id',
                    'url' => '/dashboard/general-leads/list',
                ],
            ],
        ];

        $clean = (new AiConnectorPayloadSanitizer())->sanitize($payload);

        $this->assertSame(10, $clean['summary']['total']);
        $this->assertArrayNotHasKey('urls', $clean['summary']);
        $this->assertSame('Source Seguro', $clean['rows'][0]['name']);
        $this->assertArrayNotHasKey('email', $clean['rows'][0]);
        $this->assertArrayNotHasKey('phone', $clean['rows'][0]);
        $this->assertArrayNotHasKey('remote_ip', $clean['rows'][0]);
        $this->assertArrayNotHasKey('meta_payload', $clean['rows'][0]);
        $this->assertArrayNotHasKey('gclid', $clean['rows'][0]);
        $this->assertArrayNotHasKey('url', $clean['rows'][0]);
    }

    public function test_locked_customer_overrides_customer_sent_by_ai(): void
    {
        $connector = new AiConnector([
            'customer_id' => 7,
            'max_date_range_days' => 31,
        ]);

        $arguments = $this->toolService()->normalizeArguments($connector, [
            'customer_id' => 99,
            'from' => '2026-08-01',
            'to' => '2026-08-02',
        ]);

        $this->assertSame(7, $arguments['customer_id']);
    }

    public function test_unknown_parameters_are_rejected_before_querying(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parametros no permitidos');

        $connector = new AiConnector([
            'max_date_range_days' => 31,
        ]);

        $this->toolService()->normalizeArguments($connector, [
            'from' => '2026-08-01',
            'to' => '2026-08-02',
            'sql' => 'select * from leads',
        ]);
    }

    public function test_claude_origin_is_allowed_when_connector_has_no_custom_origin_list(): void
    {
        $request = Request::create('/api/ai-connectors/mcp', 'POST', [], [], [], [
            'HTTP_ORIGIN' => 'https://claude.ai',
        ]);
        $request->attributes->set('ai_connector', new AiConnector([
            'allowed_origins' => [],
        ]));

        $response = (new AiConnectorMcpOriginGuard())->handle(
            $request,
            fn (): Response => response('', 204)
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_unknown_origin_is_rejected_by_default(): void
    {
        $request = Request::create('/api/ai-connectors/mcp', 'POST', [], [], [], [
            'HTTP_ORIGIN' => 'https://example.invalid',
        ]);
        $request->attributes->set('ai_connector', new AiConnector([
            'allowed_origins' => [],
        ]));

        $response = (new AiConnectorMcpOriginGuard())->handle(
            $request,
            fn (): Response => response('', 204)
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_initialize_returns_the_protocol_requested_by_claude(): void
    {
        $server = new AiConnectorMcpServerService(
            \Mockery::mock(AiConnectorGeneralLeadsToolService::class),
            \Mockery::mock(AiConnectorQueryCacheService::class),
            \Mockery::mock(AiConnectorRateLimitService::class),
            \Mockery::mock(AiConnectorQueryAuditService::class),
        );

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'claude.ai', 'version' => 'test'],
            ],
        ];

        $request = Request::create('/api/ai-connectors/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2025-06-18',
        ], json_encode($payload));

        $response = $server->handle($request, new AiConnector());
        $body = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2025-06-18', data_get($body, 'result.protocolVersion'));
    }

    private function toolService(): AiConnectorGeneralLeadsToolService
    {
        return new AiConnectorGeneralLeadsToolService(
            new GeneralLeadsDashboardService(new GeneralLeadsLeadQuery()),
            new AiConnectorPayloadSanitizer(),
        );
    }
}
