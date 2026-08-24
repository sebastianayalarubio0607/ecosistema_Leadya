<?php

namespace App\Http\Services\AiConnectors;

class AiConnectorRateLimitException extends \RuntimeException
{
    public function __construct(public readonly int $retryAfter, string $message)
    {
        parent::__construct($message);
    }
}
