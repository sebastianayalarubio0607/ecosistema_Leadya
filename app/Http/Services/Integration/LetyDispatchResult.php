<?php

namespace App\Http\Services\Integration;

class LetyDispatchResult
{
    public function __construct(private array $results)
    {
    }

    public function successful(): bool
    {
        foreach ($this->results as $result) {
            if (!($result['successful'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    public function body(): string
    {
        return json_encode([
            'sent' => count(array_filter($this->results, fn ($result) => ($result['sent'] ?? false) === true)),
            'results' => $this->results,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function status(): int
    {
        if ($this->results === []) {
            return 204;
        }

        if (count($this->results) === 1 && ($this->results[0]['sent'] ?? true) === false) {
            return (int) ($this->results[0]['status'] ?? 204);
        }

        foreach ($this->results as $result) {
            if (!($result['successful'] ?? false)) {
                return (int) ($result['status'] ?? 500);
            }
        }

        return 200;
    }
}
