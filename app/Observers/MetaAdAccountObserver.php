<?php

namespace App\Observers;

use App\Jobs\MetaAdAccountSubscriptionCheckJob;
use App\Jobs\MetaAdAccountUnsubscribeJob;
use App\Models\MetaAdAccount;

class MetaAdAccountObserver
{
    public function created(MetaAdAccount $metaAdAccount): void
    {
        MetaAdAccountSubscriptionCheckJob::dispatch($metaAdAccount->id)->afterCommit();
    }

    public function updated(MetaAdAccount $metaAdAccount): void
    {
        if ($metaAdAccount->wasChanged(['customer_id', 'meta_account_id', 'name', 'status'])) {
            MetaAdAccountSubscriptionCheckJob::dispatch($metaAdAccount->id)->afterCommit();
        }
    }

    public function deleting(MetaAdAccount $metaAdAccount): void
    {
        if (filled($metaAdAccount->meta_account_id)) {
            MetaAdAccountUnsubscribeJob::dispatch($metaAdAccount->id, $metaAdAccount->meta_account_id)->afterCommit();
        }
    }
}
