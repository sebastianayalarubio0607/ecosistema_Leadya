<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code', 80)->unique();
            $t->string('name');
        });
        Schema::create('alert_subcategories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained('alert_categories');
            $t->string('code', 80);
            $t->string('name');
            $t->unique(['category_id', 'code']);
        });
        Schema::create('alert_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subcategory_id')->constrained('alert_subcategories');
            $t->string('code', 100)->unique();
            $t->string('name');
        });
        Schema::create('alert_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('category_id')->constrained('alert_categories');
            $t->foreignId('subcategory_id')->nullable()->constrained('alert_subcategories');
            $t->foreignId('type_id')->nullable()->constrained('alert_types');
            $t->string('scope_key', 110);
            $t->string('name');
            $t->boolean('enabled')->default(true);
            $t->string('timezone', 80)->default('America/Bogota');
            $t->json('weekdays');
            $t->time('starts_time')->nullable();
            $t->time('ends_time')->nullable();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->unsignedInteger('max_notifications')->default(1);
            $t->unsignedInteger('min_alerts')->default(1);
            $t->unsignedInteger('interval_minutes')->default(60);
            $t->string('channel', 30)->default('internal');
            $t->string('severity', 20)->default('warning');
            $t->boolean('requires_response')->default(false);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['customer_id', 'scope_key']);
        });
        Schema::create('alert_rule_user', function (Blueprint $t) {
            $t->foreignId('alert_rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->primary(['alert_rule_id', 'user_id']);
        });
        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('type_id')->constrained('alert_types');
            $t->string('platform', 40);
            $t->string('entity_type', 80);
            $t->string('entity_id', 100);
            $t->unsignedBigInteger('meta_ad_account_id')->nullable();
            $t->string('entity_name')->nullable();
            $t->string('customer_name');
            $t->char('fingerprint', 64)->index();
            $t->char('active_key', 64)->nullable()->unique();
            $t->foreignId('previous_alert_id')->nullable()->constrained('alerts');
            $t->string('previous_state', 64)->nullable();
            $t->string('current_state', 64);
            $t->text('message');
            $t->string('severity', 20)->default('warning');
            $t->string('status', 20)->default('open');
            $t->json('metadata')->nullable();
            $t->dateTime('detected_at', 6);
            $t->dateTime('last_seen_at', 6);
            $t->dateTime('resolved_at', 6)->nullable();
            $t->timestamps();
            $t->index(['customer_id', 'status', 'detected_at'], 'alerts_customer_status_date');
            $t->index(['platform', 'entity_type', 'entity_id', 'type_id'], 'alerts_source');
        });
        Schema::create('alert_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('alert_id')->constrained('alerts');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('requires_response')->default(false);
            $t->dateTime('read_at')->nullable();
            $t->dateTime('acknowledged_at')->nullable();
            $t->dateTime('dismissed_at')->nullable();
            $t->unsignedInteger('notification_count')->default(0);
            $t->dateTime('last_notified_at')->nullable();
            $t->timestamps();
            $t->unique(['alert_id', 'user_id']);
            $t->index(['user_id', 'acknowledged_at', 'read_at'], 'alert_recipient_pending');
        });
        Schema::create('alert_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recipient_id')->constrained('alert_recipients');
            $t->foreignId('rule_id')->constrained('alert_rules');
            $t->unsignedInteger('sequence');
            $t->string('channel', 30)->default('internal');
            $t->string('severity', 20);
            $t->json('rule_snapshot');
            $t->dateTime('delivered_at');
            $t->unique(['recipient_id', 'channel', 'sequence'], 'alert_delivery_unique');
        });
        Schema::create('alert_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('alert_id')->constrained('alerts');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('kind', 40);
            $t->json('data')->nullable();
            $t->dateTime('occurred_at', 6);
            $t->index(['alert_id', 'id']);
        });
        Schema::create('alert_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recipient_id')->constrained('alert_recipients');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('alert_observations', function (Blueprint $t) {
            $t->id();
            $t->string('source_key', 160)->unique();
            $t->json('data');
            $t->dateTime('observed_at', 6);
            $t->dateTime('processed_at')->nullable();
            $t->index(['processed_at', 'observed_at', 'id'], 'alert_observations_pending');
        });
        Schema::create('alert_source_states', function (Blueprint $t) {
            $t->string('source_key', 160)->primary();
            $t->string('state', 64)->nullable();
            $t->dateTime('observed_at', 6)->nullable();
        });
        Schema::create('alert_checkpoints', function (Blueprint $t) {
            $t->string('name', 80)->primary();
            $t->unsignedBigInteger('last_id')->default(0);
            $t->dateTime('started_at');
        });

        $category = DB::table('alert_categories')->insertGetId(['code' => 'cuentas_publicitarias', 'name' => 'Cuentas publicitarias']);
        $subcategory = DB::table('alert_subcategories')->insertGetId(['category_id' => $category, 'code' => 'estados', 'name' => 'Estados']);
        DB::table('alert_types')->insert(['subcategory_id' => $subcategory, 'code' => 'cuenta_publicitaria_inactiva', 'name' => 'Cuenta publicitaria inactiva']);
        DB::table('alert_checkpoints')->insert([
            'name' => 'meta_history', 'started_at' => now(),
            'last_id' => Schema::hasTable('meta_ad_account_status_histories') ? (DB::table('meta_ad_account_status_histories')->max('id') ?? 0) : 0,
        ]);
        DB::table('alert_checkpoints')->insert(['name' => 'meta_snapshot', 'started_at' => now(), 'last_id' => 0]);
    }

    public function down(): void
    {
        foreach (['alert_checkpoints', 'alert_source_states', 'alert_observations', 'alert_comments', 'alert_events', 'alert_notifications', 'alert_recipients', 'alerts', 'alert_rule_user', 'alert_rules', 'alert_types', 'alert_subcategories', 'alert_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
