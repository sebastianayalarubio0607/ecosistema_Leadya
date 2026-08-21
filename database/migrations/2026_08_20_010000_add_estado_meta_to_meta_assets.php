<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCurrentStatusColumns('meta_ad_accounts', 'subscription_last_error');
        $this->addCurrentStatusColumns('meta_pages', 'subscription_last_error');

        if (! Schema::hasTable('meta_ad_account_status_histories')) {
            Schema::create('meta_ad_account_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('meta_ad_account_id')->nullable()->constrained('meta_ad_accounts')->nullOnDelete();
                $table->foreignId('meta_webhook_event_id')->nullable()->constrained('meta_webhook_events')->nullOnDelete();
                $table->string('meta_account_id', 64)->nullable()->index();
                $table->string('estado_meta_anterior', 64)->nullable()->index();
                $table->string('estado_meta_anterior_nombre')->nullable();
                $table->string('estado_meta_nuevo', 64)->nullable()->index();
                $table->string('estado_meta_nuevo_nombre')->nullable();
                $table->boolean('changed')->default(false)->index();
                $table->string('query_type', 80)->nullable()->index();
                $table->timestamp('consulted_at')->index();
                $table->json('payload')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('meta_page_status_histories')) {
            Schema::create('meta_page_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('meta_page_id')->nullable()->constrained('meta_pages')->nullOnDelete();
                $table->foreignId('meta_webhook_event_id')->nullable()->constrained('meta_webhook_events')->nullOnDelete();
                $table->string('meta_page_external_id')->nullable()->index();
                $table->string('estado_meta_anterior', 64)->nullable()->index();
                $table->string('estado_meta_anterior_nombre')->nullable();
                $table->string('estado_meta_nuevo', 64)->nullable()->index();
                $table->string('estado_meta_nuevo_nombre')->nullable();
                $table->boolean('changed')->default(false)->index();
                $table->string('query_type', 80)->nullable()->index();
                $table->timestamp('consulted_at')->index();
                $table->json('payload')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_page_status_histories');
        Schema::dropIfExists('meta_ad_account_status_histories');

        $this->dropColumnsIfTheyExist('meta_pages', [
            'estado_meta',
            'estado_meta_nombre',
            'estado_meta_checked_at',
            'estado_meta_payload',
            'estado_meta_last_error',
        ]);

        $this->dropColumnsIfTheyExist('meta_ad_accounts', [
            'estado_meta',
            'estado_meta_nombre',
            'estado_meta_checked_at',
            'estado_meta_payload',
            'estado_meta_last_error',
        ]);
    }

    private function addCurrentStatusColumns(string $tableName, string $after): void
    {
        if (! Schema::hasColumn($tableName, 'estado_meta')) {
            Schema::table($tableName, function (Blueprint $table) use ($after) {
                $table->string('estado_meta', 64)->nullable()->after($after)->index();
            });
        }

        if (! Schema::hasColumn($tableName, 'estado_meta_nombre')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('estado_meta_nombre')->nullable()->after('estado_meta');
            });
        }

        if (! Schema::hasColumn($tableName, 'estado_meta_checked_at')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('estado_meta_checked_at')->nullable()->after('estado_meta_nombre');
            });
        }

        if (! Schema::hasColumn($tableName, 'estado_meta_payload')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('estado_meta_payload')->nullable()->after('estado_meta_checked_at');
            });
        }

        if (! Schema::hasColumn($tableName, 'estado_meta_last_error')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->text('estado_meta_last_error')->nullable()->after('estado_meta_payload');
            });
        }
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
