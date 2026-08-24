<?php

namespace App\Http\Controllers\AiConnectors;

use App\Http\Controllers\Controller;
use App\Http\Services\AiConnectors\AiConnectorMcpServerService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiConnectorMcpController extends Controller
{
    public function __construct(private readonly AiConnectorMcpServerService $server) {}

    public function __invoke(Request $request): Response
    {
        return $this->server->handle($request, $request->attributes->get('ai_connector'));
    }
}
