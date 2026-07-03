<?php

namespace App\Http\Services\Integration\Concerns;

use App\Models\Integration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait ResolvesIntegrationVariableMappings
{
    protected function integrationVariableMappings(Integration $integration)
    {
        if (!Schema::hasTable('integration_variable_mappings')) {
            return collect();
        }

        if ($integration->relationLoaded('variableMappings')) {
            return $integration->variableMappings
                ->where('active', true)
                ->sortBy(fn ($mapping) => sprintf('%010d-%010d', $mapping->order ?? 0, $mapping->id ?? 0))
                ->values();
        }

        return $integration->variableMappings()
            ->where('active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    protected function resolveMappedIntegrationValue(
        $mappings,
        ?string $targetVariable,
        string $leadField,
        $leadValue,
        ?string $fallbackValue = null,
        string $logLabel = 'INTEGRATION'
    ) {
        $fallback = func_num_args() >= 5 ? $fallbackValue : $leadValue;

        if ($targetVariable === null || $leadValue === null || $leadValue === '') {
            return $fallback;
        }

        foreach ($mappings as $mapping) {
            if ((string) $mapping->target_variable !== (string) $targetVariable) {
                continue;
            }

            if ((string) $mapping->lead_field !== (string) $leadField) {
                continue;
            }

            if ((string) $mapping->expected_value !== (string) $leadValue) {
                continue;
            }

            if ($mapping->mapped_value === null || $mapping->mapped_value === '') {
                return $fallback;
            }

            Log::info($logLabel . ' VARIABLE MAPPING MATCHED', [
                'target_variable' => $targetVariable,
                'lead_field' => $leadField,
                'expected_value' => $mapping->expected_value,
            ]);

            return $mapping->mapped_value;
        }

        return $fallback;
    }

    protected function resolveMappedIntegrationValueForTargets(
        $mappings,
        array $targetVariables,
        string $leadField,
        $leadValue,
        $fallbackValue = null,
        string $logLabel = 'INTEGRATION'
    ) {
        foreach ($targetVariables as $targetVariable) {
            $targetVariable = trim((string) $targetVariable);

            if ($targetVariable === '') {
                continue;
            }

            foreach ($mappings as $mapping) {
                if ((string) $mapping->target_variable !== $targetVariable) {
                    continue;
                }

                if ((string) $mapping->lead_field !== (string) $leadField) {
                    continue;
                }

                if ((string) $mapping->expected_value !== (string) $leadValue) {
                    continue;
                }

                if ($mapping->mapped_value === null || $mapping->mapped_value === '') {
                    return $fallbackValue;
                }

                Log::info($logLabel . ' VARIABLE MAPPING MATCHED', [
                    'target_variable' => $targetVariable,
                    'lead_field' => $leadField,
                    'expected_value' => $mapping->expected_value,
                ]);

                return $mapping->mapped_value;
            }
        }

        return $fallbackValue;
    }
}
