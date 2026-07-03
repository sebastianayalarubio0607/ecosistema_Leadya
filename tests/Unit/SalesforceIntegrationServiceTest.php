<?php

use App\Http\Services\Integration\SalesforceIntegrationService;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

function callSalesforcePrivateMethod(SalesforceIntegrationService $service, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod($method);
    $method->setAccessible(true);

    return $method->invoke($service, ...$arguments);
}

it('keeps the existing lead created at format unchanged', function () {
    $service = new SalesforceIntegrationService();
    $lead = new Lead();
    $lead->created_at = Carbon::parse('2026-07-01 01:34:21');

    expect(callSalesforcePrivateMethod($service, 'formatLeadCreatedAt', $lead))
        ->toBe('7/01/2026 01:34:21 AM');
});

it('prepares fechaInscripcion for the Salesforce transport without duplicate zero padding', function () {
    $service = new SalesforceIntegrationService();
    $lead = new Lead();
    $lead->created_at = Carbon::parse('2026-07-01 01:34:21');

    $prepared = callSalesforcePrivateMethod($service, 'preparePayloadForSalesforce', [
        'ServiceInput' => [
            'fechaInscripcion' => '7/01/2026 01:34:21 AM',
        ],
    ], $lead);

    expect($prepared['ServiceInput']['fechaInscripcion'])
        ->toBe('7/1/2026 1:34:21 AM');
});

it('normalizes over padded date and time fragments before sending to Salesforce', function () {
    $service = new SalesforceIntegrationService();
    $lead = new Lead();
    $lead->created_at = Carbon::parse('2026-07-03 09:05:07');

    $prepared = callSalesforcePrivateMethod($service, 'preparePayloadForSalesforce', [
        'ServiceInput' => [
            'date' => '2026-005-11',
            'time' => '009:57:53',
            'combined' => 'Fecha 2026-07-001 hora 001:34:21',
        ],
    ], $lead);

    expect($prepared['ServiceInput']['date'])->toBe('2026-05-11')
        ->and($prepared['ServiceInput']['time'])->toBe('09:57:53')
        ->and($prepared['ServiceInput']['combined'])->toBe('Fecha 2026-07-01 hora 01:34:21');
});
