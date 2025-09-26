<?php

namespace App\Observers;

use App\Jobs\SendLeadToFacebook;
use App\Models\Lead;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        // Recopila contexto si lo guardaste en el Lead
        $context = [
            'ip'         => $lead->remote_ip ?? null,
            'user_agent' => request()?->userAgent(), // si estás en un flujo HTTP
            // Si almacenaste cookies de meta en el Lead o request:
            'fbp'        => request()?->cookie('_fbp') ?? null,
            'fbc'        => request()?->cookie('_fbc') ?? null,
        ];

        // customer_id ya viene en el Lead
        $customerId = (int) $lead->customer_id;

        SendLeadToFacebook::dispatch($lead->id, $customerId, array_filter($context));
    }
}
