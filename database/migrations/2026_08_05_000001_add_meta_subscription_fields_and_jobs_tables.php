<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addMetaAdAccountColumns();
        $this->addMetaPageColumns();

        $this->createQueueTable('meta_ad_account_subscription_jobs', 'maasj');
        $this->createQueueTable('meta_page_subscription_jobs', 'mpsj');
        $this->createFailedJobsTable('meta_ad_account_subscription_failed_jobs', 'maasfj');
        $this->createFailedJobsTable('meta_page_subscription_failed_jobs', 'mpsfj');
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_page_subscription_failed_jobs');
        Schema::dropIfExists('meta_ad_account_subscription_failed_jobs');
        Schema::dropIfExists('meta_page_subscription_jobs');
        Schema::dropIfExists('meta_ad_account_subscription_jobs');

        $this->dropColumnsIfTheyExist('meta_pages', [
            'leadgen',
            'is_leadgen_subscribed',
            'subscription_checked_at',
            'subscription_updated_at',
            'subscription_last_error',
        ]);

        $this->dropColumnsIfTheyExist('meta_ad_accounts', [
            'subscribed_apps',
            'is_subscribed_to_meta_app',
            'token_can_view_account',
            'subscription_checked_at',
            'subscription_updated_at',
            'subscription_last_error',
        ]);
    }

    private function addMetaAdAccountColumns(): void
    {
        if (! Schema::hasColumn('meta_ad_accounts', 'subscribed_apps')) {
            Schema::table('meta_ad_accounts', function (Blueprint $table) {
                $table->json('subscribed_apps')->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('meta_ad_accounts', 'is_subscribed_to_meta_app')) {
            Schema::table('meta_ad_accounts', function (Blueprint $table) {
                $table->boolean('is_subscribed_to_meta_app')->default(false)->after('subscribed_apps')->index('maa_subscribed_idx');
            });
        }

        if (! Schema::hasColumn('meta_ad_accounts', 'token_can_view_account')) {
            Schema::table('meta_ad_accounts', function (Blueprint $table) {
                $table->boolean('token_can_view_account')->nullable()->after('is_subscribed_to_meta_app')->index('maa_token_view_idx');
            });
        }

        if (! Schema::hasColumn('meta_ad_accounts', 'subscription_checked_at')) {
            Schema::table('meta_ad_accounts', function (Blueprint $table) {
                $table->timestamp('subscription_checked_at')->nullable()->after('token_can_view_account');
            });
        }

        if (! Schema::hasColumn('meta_ad_accounts', 'subscription_updated_at')) {
            Schema::table('meta_ad_accounts', function (Blueprint $table) {
                $table->timestamp('subscription_updated_at')->nullable()->after('subscription_checked_at');
            });
        }

        if (! Schema::hasColumn('meta_ad_accounts', 'subscription_last_error')) {
            Schema::table('meta_ad_accounts', function (Blueprint $table) {
                $table->text('subscription_last_error')->nullable()->after('subscription_updated_at');
            });
        }
    }

    private function addMetaPageColumns(): void
    {
        if (! Schema::hasColumn('meta_pages', 'leadgen')) {
            Schema::table('meta_pages', function (Blueprint $table) {
                $table->json('leadgen')->nullable()->after('last_error');
            });
        }

        if (! Schema::hasColumn('meta_pages', 'is_leadgen_subscribed')) {
            Schema::table('meta_pages', function (Blueprint $table) {
                $table->boolean('is_leadgen_subscribed')->default(false)->after('leadgen')->index('mp_leadgen_idx');
            });
        }

        if (! Schema::hasColumn('meta_pages', 'subscription_checked_at')) {
            Schema::table('meta_pages', function (Blueprint $table) {
                $table->timestamp('subscription_checked_at')->nullable()->after('is_leadgen_subscribed');
            });
        }

        if (! Schema::hasColumn('meta_pages', 'subscription_updated_at')) {
            Schema::table('meta_pages', function (Blueprint $table) {
                $table->timestamp('subscription_updated_at')->nullable()->after('subscription_checked_at');
            });
        }

        if (! Schema::hasColumn('meta_pages', 'subscription_last_error')) {
            Schema::table('meta_pages', function (Blueprint $table) {
                $table->text('subscription_last_error')->nullable()->after('subscription_updated_at');
            });
        }
    }

    private function createQueueTable(string $tableName, string $indexPrefix): void
    {
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($indexPrefix) {
            $table->id();
            $table->string('queue')->index($indexPrefix.'_queue_idx');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    private function createFailedJobsTable(string $tableName, string $indexPrefix): void
    {
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($indexPrefix) {
            $table->id();
            $table->string('uuid', 64)->nullable()->index($indexPrefix.'_uuid_idx');
            $table->string('connection')->nullable();
            $table->string('queue')->nullable();
            $table->string('job_class');
            $table->string('action', 40)->index($indexPrefix.'_action_idx');
            $table->unsignedBigInteger('resource_id')->nullable()->index($indexPrefix.'_rid_idx');
            $table->string('resource_identifier', 191)->nullable()->index($indexPrefix.'_rident_idx');
            $table->json('payload')->nullable();
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent()->index($indexPrefix.'_failed_idx');
            $table->timestamp('retried_at')->nullable();
            $table->timestamps();
        });
    }

    private function dropColumnsIfTheyExist(string $tableName, array $columns): void
    {
        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($tableName, $column)
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existingColumns) {
            $table->dropColumn($existingColumns);
        });
    }
};
