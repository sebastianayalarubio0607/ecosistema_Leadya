<?php

namespace App\Observers;

use App\Jobs\MetaWhatsappSubscriptionCheckJob;
use App\Jobs\MetaWhatsappUnsubscribeJob;
use App\Models\MetaWhatsapp;

class MetaWhatsappObserver
{
    public function created(MetaWhatsapp $metaWhatsapp): void
    {
        MetaWhatsappSubscriptionCheckJob::dispatch($metaWhatsapp->id)->afterCommit();
    }

    public function updated(MetaWhatsapp $metaWhatsapp): void
    {
        if ($metaWhatsapp->wasChanged(['meta_access_token_id', 'waba_id', 'phone_number_id', 'wa_id', 'status'])) {
            MetaWhatsappSubscriptionCheckJob::dispatch($metaWhatsapp->id)->afterCommit();
        }
    }

    public function deleting(MetaWhatsapp $metaWhatsapp): void
    {
        if (filled($metaWhatsapp->waba_id)) {
            MetaWhatsappUnsubscribeJob::dispatch(
                $metaWhatsapp->id,
                $metaWhatsapp->waba_id,
                $metaWhatsapp->subscription_meta_access_token_id ?: $metaWhatsapp->meta_access_token_id,
            )->afterCommit();
        }
    }
}
