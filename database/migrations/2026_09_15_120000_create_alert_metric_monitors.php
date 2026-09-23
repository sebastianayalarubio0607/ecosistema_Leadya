<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_metric_monitors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('kind', 30);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('alert_metric_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('monitor_id')->constrained('alert_metric_monitors');
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->boolean('enabled')->default(true);
            $t->json('settings');
            $t->unsignedInteger('version')->default(1);
            $t->dateTime('activated_at');
            $t->dateTime('next_query_at')->nullable()->index();
            $t->dateTime('last_checked_at')->nullable();
            $t->string('last_status', 30)->default('pending');
            $t->string('last_error')->nullable();
            $t->timestamps();
            $t->unique(['monitor_id', 'customer_id'], 'metric_monitor_customer_unique');
        });
        Schema::create('alert_metric_subscription_user', function (Blueprint $t) {
            $t->foreignId('subscription_id')->constrained('alert_metric_subscriptions');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->primary(['subscription_id', 'user_id'], 'metric_subscription_user_pk');
        });
        Schema::create('alert_metric_states', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained('alert_metric_subscriptions');
            $t->char('scope_key', 64);
            $t->foreignId('alert_id')->nullable()->constrained('alerts');
            $t->dateTime('first_seen_at');
            $t->dateTime('checked_at');
            $t->json('data');
            $t->unique(['subscription_id', 'scope_key'], 'metric_state_scope_unique');
        });
        Schema::create('alert_metric_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained('alert_metric_subscriptions');
            $t->unsignedInteger('version');
            $t->string('status', 30);
            $t->json('summary')->nullable();
            $t->dateTime('checked_at')->index();
        });
        // Metric deliveries carry their own immutable policy in rule_snapshot.
        // Existing rule IDs, constraints and deliveries remain intact.
        Schema::table('alert_notifications', fn (Blueprint $t) => $t->unsignedBigInteger('rule_id')->nullable()->change());
        foreach ([
            ['publicidad', 'Publicidad', 'impresiones', 'Impresiones', 'metric_impressions', 'Impresiones insuficientes'],
            ['leads_quality', 'Leads Quality', 'creacion_leads', 'Creación de leads', 'metric_leads', 'Leads insuficientes'],
        ] as [$categoryCode, $categoryName, $subCode, $subName, $typeCode, $typeName]) {
            $category = DB::table('alert_categories')->insertGetId(['code' => $categoryCode, 'name' => $categoryName]);
            $subcategory = DB::table('alert_subcategories')->insertGetId(['category_id' => $category, 'code' => $subCode, 'name' => $subName]);
            DB::table('alert_types')->insert(['subcategory_id' => $subcategory, 'code' => $typeCode, 'name' => $typeName]);
        }
    }

    public function down(): void
    {
        // Preserve audit records. Rollback is allowed only before this module has data.
        if (DB::table('alert_metric_subscriptions')->exists()) {
            throw new RuntimeException('Hay configuraciones de métricas. Deshabilítelas; no elimine su historial.');
        }
        foreach (['alert_metric_runs', 'alert_metric_states', 'alert_metric_subscription_user', 'alert_metric_subscriptions', 'alert_metric_monitors'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::table('alert_types')->whereIn('code', ['metric_impressions', 'metric_leads'])->delete();
        $categories = DB::table('alert_categories')->whereIn('code', ['publicidad', 'leads_quality'])->pluck('id');
        DB::table('alert_subcategories')->whereIn('category_id', $categories)->delete();
        DB::table('alert_categories')->whereIn('id', $categories)->delete();
    }
};
