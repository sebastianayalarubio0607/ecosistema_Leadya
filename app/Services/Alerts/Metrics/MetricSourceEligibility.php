<?php

namespace App\Services\Alerts\Metrics;

use App\Models\Customer;
use App\Models\MetaAdAccount;
use App\Support\MetaAdAccountId;

class MetricSourceEligibility
{
    public function allows(Customer $customer, array $rows): bool
    {
        if (! $customer->status) {
            return false;
        }
        $metaIds = null;
        foreach ($rows as $row) {
            if ($row['platform'] === 'meta') {
                $metaIds ??= MetaAdAccount::where(function ($q) use ($customer) {
                    $q->whereHas('customers', fn ($c) => $c->whereKey($customer->id))
                        ->orWhere(fn ($legacy) => $legacy->whereDoesntHave('customers')->where('customer_id', $customer->id));
                })->get()->filter(fn ($a) => $a->isActive())
                    ->map(fn ($a) => MetaAdAccountId::normalize($a->meta_account_id))->all();
                if (! in_array(MetaAdAccountId::normalize($row['account']), $metaIds, true)) {
                    return false;
                }
            } elseif ($row['platform'] === 'google' && preg_replace('/\D+/', '', (string) $customer->id_Gads) !== $row['account']) {
                return false;
            } elseif ($row['platform'] === 'leads' && (string) $customer->id !== $row['account']) {
                return false;
            }
        }

        return true;
    }
}
