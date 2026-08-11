<?php

namespace App\Observers;

use App\Jobs\MetaPageSubscriptionCheckJob;
use App\Jobs\MetaPageUnsubscribeLeadgenJob;
use App\Models\MetaPage;

class MetaPageObserver
{
    public function created(MetaPage $metaPage): void
    {
        MetaPageSubscriptionCheckJob::dispatch($metaPage->id)->afterCommit();
    }

    public function updated(MetaPage $metaPage): void
    {
        if ($metaPage->wasChanged(['customer_id', 'meta_page_id', 'name', 'page_access_token', 'status'])) {
            MetaPageSubscriptionCheckJob::dispatch($metaPage->id)->afterCommit();
        }
    }

    public function deleting(MetaPage $metaPage): void
    {
        if (filled($metaPage->meta_page_id)) {
            MetaPageUnsubscribeLeadgenJob::dispatch($metaPage->id, $metaPage->meta_page_id)->afterCommit();
        }
    }
}
