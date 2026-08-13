<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meta_whatsapps')) {
            Schema::create('meta_whatsapps', function (Blueprint $table) {
                $table->id();
                $table->string('waba_id', 64)->unique();
                $table->string('phone_number_id', 64)->nullable()->index();
                $table->string('wa_id', 64)->nullable()->index();
                $table->boolean('status')->default(true)->index();
                $table->json('subscribed_apps')->nullable();
                $table->boolean('is_subscribed_to_meta_app')->default(false)->index('mw_subscribed_idx');
                $table->boolean('token_can_view_account')->nullable()->index('mw_token_view_idx');
                $table->timestamp('subscription_checked_at')->nullable();
                $table->timestamp('subscription_updated_at')->nullable();
                $table->text('subscription_last_error')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_meta_whatsapp')) {
            Schema::create('customer_meta_whatsapp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('meta_whatsapp_id')->constrained('meta_whatsapps')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['customer_id', 'meta_whatsapp_id'], 'cmw_customer_whatsapp_unique');
            });
        }

        $this->createQueueTable('meta_whatsapp_subscription_jobs', 'mwsj');
        $this->createFailedJobsTable('meta_whatsapp_subscription_failed_jobs', 'mwsfj');

        if (! Schema::hasTable('meta_whatsapp_messages')) {
            Schema::create('meta_whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->string('waba_id', 64)->nullable()->index();
                $table->string('phone_number_id', 64)->nullable()->index();
                $table->string('wa_id', 64)->nullable()->index();
                $table->string('message_id', 191)->unique();
                $table->timestamp('message_timestamp')->nullable()->index();
                $table->string('ctwa_clid')->nullable()->index();
                $table->string('source_id')->nullable()->index();
                $table->text('source_url')->nullable();
                $table->string('headline')->nullable();
                $table->text('body')->nullable();
                $table->string('source_type')->nullable()->index();
                $table->boolean('is_first_message')->default(false)->index();
                $table->json('referral')->nullable();
                $table->json('message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left without destructive operations.
        // These tables can contain queued jobs, failed job logs, webhook messages,
        // and customer associations that must not be removed by an accidental rollback.
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
};
