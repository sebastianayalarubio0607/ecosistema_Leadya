<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeReceivedAtDefault();

        Schema::table('meta_webhook_events', function (Blueprint $table) {
            if (! Schema::hasColumn('meta_webhook_events', 'referral')) {
                $table->json('referral')->nullable()->after('value');
            }
        });

        $this->backfillExistingReferrals();
    }

    public function down(): void
    {
        Schema::table('meta_webhook_events', function (Blueprint $table) {
            if (Schema::hasColumn('meta_webhook_events', 'referral')) {
                $table->dropColumn('referral');
            }
        });
    }

    private function normalizeReceivedAtDefault(): void
    {
        if (! Schema::hasColumn('meta_webhook_events', 'received_at')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `meta_webhook_events` MODIFY `received_at` TIMESTAMP NULL DEFAULT NULL');
    }

    private function backfillExistingReferrals(): void
    {
        DB::table('meta_webhook_events')
            ->whereNull('referral')
            ->whereNotNull('value')
            ->orderBy('id')
            ->chunkById(200, function ($events): void {
                foreach ($events as $event) {
                    $value = is_string($event->value)
                        ? json_decode($event->value, true)
                        : $event->value;
                    $referral = $this->referralFromValue($value);

                    if ($referral === null) {
                        continue;
                    }

                    DB::table('meta_webhook_events')
                        ->where('id', $event->id)
                        ->update(['referral' => json_encode($referral, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
            });
    }

    private function referralFromValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        if (isset($value['referral']) && is_array($value['referral'])) {
            return $value['referral'];
        }

        $messages = $this->listFromValue($value['messages'] ?? null);
        $referrals = [];

        foreach ($messages as $message) {
            if (is_array($message) && isset($message['referral']) && is_array($message['referral'])) {
                $referrals[] = $message['referral'];
            }
        }

        if (count($referrals) === 1) {
            return $referrals[0];
        }

        return $referrals === [] ? null : $referrals;
    }

    private function listFromValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }
};
