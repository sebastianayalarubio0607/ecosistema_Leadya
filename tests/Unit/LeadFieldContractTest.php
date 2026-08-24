<?php

use App\Models\Lead;

test('lead exposes site url and custom text fields for mass assignment and mappings', function () {
    $fields = (new Lead())->getFillable();
    $mappableFields = Lead::integrationMappableFields();

    expect($fields)->toContain('site_url');
    expect($mappableFields)->toContain('site_url');

    foreach (range(1, 15) as $index) {
        $field = "campo_text_{$index}";

        expect($fields)->toContain($field);
        expect($mappableFields)->toContain($field);
    }

    foreach ([
        'whasapp_user_id',
        'ctwa_clid',
        'whatsapp_business_account_id',
        'number_whatsApp_companies',
        'WhatsApp_username',
    ] as $field) {
        expect($fields)->toContain($field);
        expect($mappableFields)->toContain($field);
    }
});
