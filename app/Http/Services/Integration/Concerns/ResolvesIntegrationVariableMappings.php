<?php

namespace App\Http\Services\Integration\Concerns;

use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait ResolvesIntegrationVariableMappings
{
    protected const INTEGRATION_VARIABLE_TOKEN_PREFIX = '__integration_variable__:';

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

    protected function integrationVariables(Integration $integration)
    {
        if (!Schema::hasTable('integration_variables')) {
            return collect();
        }

        if ($integration->relationLoaded('variables')) {
            return $integration->variables
                ->where('active', true)
                ->sortBy(fn ($variable) => sprintf('%010d-%010d', $variable->order ?? 0, $variable->id ?? 0))
                ->values();
        }

        return $integration->variables()
            ->where('active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    protected function integrationVariableConditions(Integration $integration)
    {
        if (!Schema::hasTable('integration_variable_conditions')) {
            return collect();
        }

        if ($integration->relationLoaded('variableConditions')) {
            return $integration->variableConditions
                ->where('active', true)
                ->sortBy(fn ($condition) => sprintf('%010d-%010d', $condition->order ?? 0, $condition->id ?? 0))
                ->values();
        }

        return $integration->variableConditions()
            ->where('active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    protected function normalizeIntegrationVariableExpression(string $expression): ?string
    {
        $expression = trim($expression);

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression)) {
            return $expression;
        }

        if (preg_match('/^\$?variables?\s*(?:->|\.)\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/', $expression, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function integrationVariableToken(string $name): string
    {
        return self::INTEGRATION_VARIABLE_TOKEN_PREFIX . $name;
    }

    protected function resolveIntegrationVariableTokenValue($value, Lead $lead, Integration $integration, string $logLabel = 'INTEGRATION')
    {
        if (!$this->isIntegrationVariableToken($value) || !preg_match('/^' . preg_quote(self::INTEGRATION_VARIABLE_TOKEN_PREFIX, '/') . '(.+)$/', $value, $matches)) {
            return null;
        }

        return $this->resolveIntegrationVariableValue($integration, $lead, $matches[1], $logLabel);
    }

    protected function isIntegrationVariableToken($value): bool
    {
        return is_string($value)
            && preg_match('/^' . preg_quote(self::INTEGRATION_VARIABLE_TOKEN_PREFIX, '/') . '(.+)$/', $value) === 1;
    }

    protected function resolveIntegrationVariableValue(Integration $integration, Lead $lead, string $name, string $logLabel = 'INTEGRATION', array $seen = [])
    {
        $name = trim($name);

        if ($name === '' || in_array($name, $seen, true)) {
            return '';
        }

        $conditionalValue = $this->resolveConditionalIntegrationVariableValue($integration, $lead, $name, $logLabel, $seen);

        if ($conditionalValue['matched']) {
            return $conditionalValue['value'];
        }

        $variable = $this->integrationVariables($integration)
            ->first(fn ($item) => (string) $item->name === $name);

        if (!$variable) {
            $context = app(\App\Http\Services\Integration\LeadIntegrationContextService::class);

            return $context->hasVariable($name) ? $context->value($lead, $name) : '';
        }

        Log::info($logLabel . ' CUSTOM VARIABLE RESOLVED', [
            'integration_id' => $integration->id,
            'variable' => $name,
            'type' => $variable->type,
        ]);

        $rendered = $this->renderIntegrationVariableTemplate((string) $variable->value, $lead, $integration, [...$seen, $name], $logLabel);

        return $this->castIntegrationVariableValue($rendered, (string) $variable->type);
    }

    protected function renderIntegrationVariablePlaceholder(string $expression, Lead $lead, Integration $integration, array $seen = [], string $logLabel = 'INTEGRATION')
    {
        $leadPath = $this->normalizeIntegrationLeadPlaceholderPath($expression);

        if ($leadPath !== null) {
            $value = data_get($lead, $leadPath);

            if ($value === null) {
                $value = data_get($lead, $this->leadFieldAliasForIntegrationVariable($leadPath), '');
            }

            return $value;
        }

        $variableName = $this->normalizeIntegrationVariableExpression($expression);

        if ($variableName !== null) {
            return $this->resolveIntegrationVariableValue($integration, $lead, $variableName, $logLabel, $seen);
        }

        return null;
    }

    protected function replaceIntegrationVariablesInString(string $value, Lead $lead, Integration $integration, array $seen = [], string $logLabel = 'INTEGRATION'): string
    {
        return (string) preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/', function ($matches) use ($lead, $integration, $seen, $logLabel) {
            $resolved = $this->renderIntegrationVariablePlaceholder($matches[1], $lead, $integration, $seen, $logLabel);

            if ($resolved === null) {
                return $matches[0];
            }

            if (is_array($resolved) || is_object($resolved)) {
                return json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            return (string) $resolved;
        }, $value);
    }

    protected function normalizeIntegrationLeadPlaceholderPath(string $expression): ?string
    {
        $expression = trim($expression);

        if (!preg_match('/^\$?lead(?:(?:->|\.)[A-Za-z_][A-Za-z0-9_]*)+$/', $expression)) {
            return null;
        }

        $path = preg_replace('/^\$?lead(?:->|\.)/', '', $expression);
        $path = str_replace('->', '.', (string) $path);
        $path = trim((string) $path, '.');

        if (str_starts_with($path, 'campaign_origin.')) {
            $path = 'campaignOrigin.' . substr($path, strlen('campaign_origin.'));
        }

        return $this->leadFieldAliasForIntegrationVariable($path);
    }

    private function renderIntegrationVariableTemplate(string $template, Lead $lead, Integration $integration, array $seen, string $logLabel): string
    {
        return $this->replaceIntegrationVariablesInString($template, $lead, $integration, $seen, $logLabel);
    }

    private function castIntegrationVariableValue(string $value, string $type)
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'number', 'numero' => is_numeric($value) ? $value + 0 : $value,
            'boolean', 'bool', 'binary', 'binario' => in_array(strtolower(trim($value)), ['1', 'true', 'si', 'sí', 'yes', 'on'], true),
            'json', 'any', 'cualquier' => $this->decodeIntegrationVariableJsonOrString($value),
            default => $value,
        };
    }

    private function resolveConditionalIntegrationVariableValue(Integration $integration, Lead $lead, string $name, string $logLabel, array $seen): array
    {
        $conditions = $this->integrationVariableConditions($integration)
            ->filter(fn ($condition) => (string) $condition->target_variable === $name)
            ->values();

        foreach ($conditions as $condition) {
            $actual = $this->resolveConditionSourceValue($condition, $lead, $integration, $logLabel, [...$seen, $name]);

            if (!$this->evaluateIntegrationVariableCondition($actual, (string) $condition->operator, $condition->comparison_value)) {
                continue;
            }

            Log::info($logLabel . ' CONDITIONAL VARIABLE MATCHED', [
                'integration_id' => $integration->id,
                'target_variable' => $name,
                'operator' => $condition->operator,
                'source_type' => $condition->source_type,
                'source_key' => $condition->source_key,
            ]);

            $rendered = $this->renderIntegrationVariableTemplate((string) $condition->result_value, $lead, $integration, [...$seen, $name], $logLabel);

            return [
                'matched' => true,
                'value' => $this->castIntegrationVariableValue($rendered, (string) $condition->result_type),
            ];
        }

        return [
            'matched' => false,
            'value' => null,
        ];
    }

    private function resolveConditionSourceValue($condition, Lead $lead, Integration $integration, string $logLabel, array $seen)
    {
        $sourceType = strtolower(trim((string) $condition->source_type));
        $sourceKey = trim((string) $condition->source_key);

        if ($sourceType === 'variable') {
            return $this->resolveIntegrationVariableValue($integration, $lead, $sourceKey, $logLabel, $seen);
        }

        $value = data_get($lead, $this->leadFieldAliasForIntegrationVariable($sourceKey));

        return $value ?? data_get($lead, $this->leadFieldAliasForIntegrationVariable(strtolower($sourceKey)));
    }

    private function evaluateIntegrationVariableCondition($actual, string $operator, $expected): bool
    {
        $operator = $this->normalizeConditionOperator($operator);

        if ($operator === 'exists') {
            return $actual !== null;
        }

        if ($operator === 'not_exists') {
            return $actual === null;
        }

        if ($operator === 'empty') {
            return $this->isBlankConditionValue($actual);
        }

        if ($operator === 'not_empty') {
            return !$this->isBlankConditionValue($actual);
        }

        if ($operator === 'has_elements') {
            return is_countable($actual) ? count($actual) > 0 : !$this->isBlankConditionValue($actual);
        }

        if ($operator === 'no_elements') {
            return is_countable($actual) ? count($actual) === 0 : $this->isBlankConditionValue($actual);
        }

        if (in_array($operator, ['key_exists', 'key_not_exists'], true)) {
            $exists = is_array($actual) && array_key_exists((string) $expected, $actual);

            return $operator === 'key_exists' ? $exists : !$exists;
        }

        $actualString = $this->conditionValueToString($actual);
        $expectedString = $this->conditionValueToString($expected);
        $expectsNull = in_array(mb_strtolower($expectedString), ['null', 'nulo'], true);

        if (in_array($operator, ['equals', 'is'], true) && $expectsNull) {
            return $actual === null;
        }

        if (in_array($operator, ['not_equals', 'not_is'], true) && $expectsNull) {
            return $actual !== null;
        }

        return match ($operator) {
            'not_equals', 'not_is' => $actualString !== $expectedString,
            'greater_than' => is_numeric($actualString) && is_numeric($expectedString) && $actualString > $expectedString,
            'less_than' => is_numeric($actualString) && is_numeric($expectedString) && $actualString < $expectedString,
            'greater_or_equal' => is_numeric($actualString) && is_numeric($expectedString) && $actualString >= $expectedString,
            'less_or_equal' => is_numeric($actualString) && is_numeric($expectedString) && $actualString <= $expectedString,
            'contains' => str_contains(mb_strtolower($actualString), mb_strtolower($expectedString)),
            'not_contains' => !str_contains(mb_strtolower($actualString), mb_strtolower($expectedString)),
            'starts_with' => str_starts_with(mb_strtolower($actualString), mb_strtolower($expectedString)),
            'not_starts_with' => !str_starts_with(mb_strtolower($actualString), mb_strtolower($expectedString)),
            'ends_with' => str_ends_with(mb_strtolower($actualString), mb_strtolower($expectedString)),
            'not_ends_with' => !str_ends_with(mb_strtolower($actualString), mb_strtolower($expectedString)),
            'in_list' => in_array($actualString, $this->conditionListValues($expectedString), true),
            'not_in_list' => !in_array($actualString, $this->conditionListValues($expectedString), true),
            'record_exists' => !$this->isBlankConditionValue($actual),
            'record_not_exists' => $this->isBlankConditionValue($actual),
            'matches_pattern' => $this->matchesConditionPattern($actualString, $expectedString),
            'not_matches_pattern' => !$this->matchesConditionPattern($actualString, $expectedString),
            'and_logic', 'or_logic', 'all_conditions', 'any_condition' => !$this->isBlankConditionValue($actual),
            'negation', 'no_conditions' => $this->isBlankConditionValue($actual),
            default => $actualString === $expectedString,
        };
    }

    private function normalizeConditionOperator(string $operator): string
    {
        $operator = str_replace('-', '_', strtolower(trim($operator)));

        return match ($operator) {
            'igual_a', 'equal', '==' => 'equals',
            'distinto_de', 'not_equal', '!=' => 'not_equals',
            'mayor_que', '>' => 'greater_than',
            'menor_que', '<' => 'less_than',
            'mayor_o_igual', '>=' => 'greater_or_equal',
            'menor_o_igual', '<=' => 'less_or_equal',
            'existe' => 'exists',
            'no_existe' => 'not_exists',
            'esta_vacio' => 'empty',
            'no_esta_vacio' => 'not_empty',
            'es' => 'is',
            'no_es' => 'not_is',
            'contiene' => 'contains',
            'no_contiene' => 'not_contains',
            'empieza_con' => 'starts_with',
            'no_empieza_con' => 'not_starts_with',
            'termina_con' => 'ends_with',
            'no_termina_con' => 'not_ends_with',
            'esta_dentro_de_una_lista' => 'in_list',
            'no_esta_dentro_de_una_lista' => 'not_in_list',
            'existe_una_llave' => 'key_exists',
            'no_existe_una_llave' => 'key_not_exists',
            'existe_un_registro' => 'record_exists',
            'no_existe_un_registro' => 'record_not_exists',
            'hay_elementos' => 'has_elements',
            'no_hay_elementos' => 'no_elements',
            'coincide_con_un_patron' => 'matches_pattern',
            'no_coincide_con_un_patron' => 'not_matches_pattern',
            'negacion' => 'negation',
            'y_logico', 'todas_las_condiciones_se_cumplen' => 'and_logic',
            'o_logico', 'alguna_condicion_se_cumple' => 'or_logic',
            'ninguna_condicion_se_cumple' => 'no_conditions',
            default => $operator,
        };
    }

    private function isBlankConditionValue($value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === false;
    }

    private function conditionValueToString($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return trim((string) $value);
    }

    private function conditionListValues(string $value): array
    {
        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return array_map(fn ($item) => $this->conditionValueToString($item), $decoded);
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($item) => $item !== ''));
    }

    private function matchesConditionPattern(string $actual, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        $regex = @preg_match($pattern, '') !== false ? $pattern : '/' . str_replace('/', '\/', $pattern) . '/';

        return @preg_match($regex, $actual) === 1;
    }

    private function decodeIntegrationVariableJsonOrString(string $value)
    {
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function leadFieldAliasForIntegrationVariable(string $field): string
    {
        return match ($field) {
            'referencia' => 'reference',
            'servicio' => 'service',
            default => $field,
        };
    }
}
