<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomerActionHistory extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_name',
        'action',
        'actor_type',
        'actor_id',
        'actor_name',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public static function recordForCustomer(
        Customer $customer,
        string $action,
        array $oldValues = [],
        array $newValues = [],
        ?array $actor = null
    ): ?self {
        if (! Schema::hasTable('customer_action_histories')) {
            return null;
        }

        $actor ??= self::resolveActor();
        $request = request();

        return self::create([
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'action' => $action,
            'actor_type' => $actor['type'] ?? 'system',
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? null,
            'old_values' => self::maskSensitiveValues($oldValues),
            'new_values' => self::maskSensitiveValues($newValues),
            'ip_address' => app()->runningInConsole() ? null : $request->ip(),
            'user_agent' => app()->runningInConsole() ? null : Str::limit((string) $request->userAgent(), 512, ''),
        ]);
    }

    public static function resolveActor(): array
    {
        if (! app()->runningInConsole()) {
            $request = request();

            if ($request->routeIs('ai-connectors.*') || $request->is('conectores-ia/*') || $request->is('token')) {
                return [
                    'type' => 'ai_connector',
                    'id' => auth()->id(),
                    'name' => auth()->user()?->name ?? 'Conector IA',
                ];
            }

            if (auth()->check()) {
                return [
                    'type' => 'user',
                    'id' => auth()->id(),
                    'name' => auth()->user()?->name,
                ];
            }
        }

        if (app()->runningInConsole()) {
            return [
                'type' => 'job',
                'id' => null,
                'name' => self::consoleActorName(),
            ];
        }

        return [
            'type' => 'system',
            'id' => null,
            'name' => null,
        ];
    }

    public static function maskSensitiveValues(array $values): array
    {
        $secretFields = [
            'token',
            'fb_access_token',
            'Meta_dataset_token',
            'Meta_whatsapp_dataset_token',
        ];

        foreach ($secretFields as $field) {
            if (array_key_exists($field, $values) && filled($values[$field])) {
                $values[$field] = '***';
            }
        }

        return $values;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    private static function consoleActorName(): ?string
    {
        $argv = $_SERVER['argv'] ?? [];

        return $argv ? Str::limit(implode(' ', $argv), 255, '') : null;
    }
}
