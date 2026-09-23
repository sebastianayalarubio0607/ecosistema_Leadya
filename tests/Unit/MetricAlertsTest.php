<?php

namespace Tests\Unit;

use App\Http\Services\GoogleAds\GoogleAdsApiClient;
use App\Http\Services\GoogleAds\GoogleAdsAuthService;
use App\Http\Services\Meta\MetaGraphService;
use App\Jobs\Alerts\EvaluateMetricAlertsJob;
use App\Livewire\Alerts\MetricMonitors;
use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertMetricMonitor;
use App\Models\Alerts\AlertMetricSubscription;
use App\Models\Alerts\AlertNotification;
use App\Models\Alerts\AlertRecipient;
use App\Models\Alerts\AlertType;
use App\Models\GoogleAdsCredential;
use App\Models\MetaAccessToken;
use App\Models\User;
use App\Services\Alerts\AlertInteractionService;
use App\Services\Alerts\AlertNotificationService;
use App\Services\Alerts\Metrics\DirectImpressionReader;
use App\Services\Alerts\Metrics\EvaluateMetricSubscription;
use App\Services\Alerts\Metrics\MetricIncidentService;
use App\Services\Alerts\Metrics\MetricNotificationService;
use App\Services\Alerts\Metrics\MetricSchedule;
use App\Services\Alerts\Metrics\SaveMetricMonitor;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class MetricAlertsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Explicit isolated connection before any schema operation. Never touch the user's MySQL.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'alerts.enabled' => true, 'alert_metrics.enabled' => true, 'alerts.manager_ids' => [],
            'cache.default' => 'array', 'session.driver' => 'array', 'app.timezone' => 'America/Bogota']);
        DB::purge('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:30:00', 'America/Bogota'));
        Http::preventStrayRequests();
        Queue::fake();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('status')->default(true);
            $t->string('id_Gads')->nullable();
            $t->timestamps();
        });
        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->dateTime('meta_created_time')->nullable();
            $t->timestamps();
            $t->index(['customer_id', 'created_at']);
        });
        Schema::create('meta_ad_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('meta_account_id');
            $t->string('status');
        });
        Schema::create('customer_meta_ad_account', function (Blueprint $t) {
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('meta_ad_account_id');
        });
        Schema::create('meta_access_tokens', function (Blueprint $t) {
            $t->id();
            foreach (array_diff(MetaAccessToken::SYNC_COLUMNS, ['id']) as $column) {
                $t->string($column)->nullable();
            }
        });
        (require database_path('migrations/2026_09_14_120000_create_alert_module_tables.php'))->up();
        (require database_path('migrations/2026_09_15_120000_create_alert_metric_monitors.php'))->up();
        DB::table('customers')->insert([['id' => 1, 'name' => 'Cliente A', 'status' => true, 'id_Gads' => '123-456-7890'], ['id' => 2, 'name' => 'Cliente B', 'status' => true, 'id_Gads' => null]]);
        DB::table('meta_ad_accounts')->insert(['id' => 1, 'customer_id' => 1, 'meta_account_id' => 'act_123', 'status' => 'active']);
        User::create(['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'test']);
        User::create(['name' => 'Beto', 'email' => 'beto@example.test', 'password' => 'test']);
        $this->actingAs(User::first());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function form(string $kind = 'leads', array $settings = []): array
    {
        return ['name' => 'Volumen bajo', 'kind' => $kind, 'customer_ids' => [1], 'user_ids' => [1, 2], 'enabled' => true,
            'settings' => array_replace(SaveMetricMonitor::defaults(), ['warmup' => false], $settings)];
    }

    private function subscription(string $kind = 'leads', array $settings = []): AlertMetricSubscription
    {
        return app(SaveMetricMonitor::class)->save($this->form($kind, $settings))->subscriptions()->first()->load('monitor', 'customer');
    }

    private function row(string $platform = 'meta', int $count = 0, string $entity = '11'): array
    {
        return ['platform' => $platform, 'account' => '123', 'entity' => $entity, 'name' => 'Campaña '.$entity, 'count' => $count,
            'window_start' => '2026-09-14T06:00:00-05:00', 'window_end' => '2026-09-15T06:00:00-05:00'];
    }

    private function apply(AlertMetricSubscription $s, array $rows): void
    {
        DB::transaction(fn () => app(MetricIncidentService::class)->apply($s, $rows, now()));
        $s->update(['last_status' => 'complete', 'last_checked_at' => now()]);
    }

    public function test_catalog_has_categories_subcategories_and_keeps_original_type(): void
    {
        $this->assertSame('Publicidad', AlertType::where('code', 'metric_impressions')->first()->subcategory->category->name);
        $this->assertSame('Creación de leads', AlertType::where('code', 'metric_leads')->first()->subcategory->name);
        $this->assertDatabaseHas('alert_types', ['code' => 'cuenta_publicitaria_inactiva']);
    }

    public function test_many_customers_many_monitors_and_customer_specific_settings(): void
    {
        $form = $this->form();
        $form['customer_ids'] = [1, 2];
        $monitor = app(SaveMetricMonitor::class)->save($form);
        $this->subscription();
        $this->assertSame(2, AlertMetricMonitor::count());
        $this->assertSame(3, AlertMetricSubscription::count());
        $s = $monitor->subscriptions()->where('customer_id', 1)->first();
        $form['customer_ids'] = [1];
        $form['settings']['window_hours'] = 72;
        app(SaveMetricMonitor::class)->save($form, $monitor->id, $s->id, 1);
        $this->assertSame(72, $s->fresh()->settings['window_hours']);
        $this->assertSame(24, $monitor->subscriptions()->where('customer_id', 2)->first()->settings['window_hours']);
        $this->expectException(ValidationException::class);
        app(SaveMetricMonitor::class)->save($form, $monitor->id, $s->id, 1);
    }

    public function test_leads_use_created_at_customer_and_exclusive_end(): void
    {
        $s = $this->subscription('leads', ['minimum' => 3]);
        foreach ([[1, '2026-09-14 09:00:00'], [1, '2026-09-15 08:59:59'], [1, '2026-09-15 09:00:00'], [2, '2026-09-15 08:00:00'], [1, '2026-09-14 08:59:59']] as [$customer, $date]) {
            DB::table('leads')->insert(['customer_id' => $customer, 'created_at' => $date, 'meta_created_time' => '2020-01-01 00:00:00']);
        }
        app(EvaluateMetricSubscription::class)->run($s->id);
        $this->assertSame(2, Alert::first()->metadata['rows'][0]['count']);
        $this->assertSame(5, DB::table('leads')->count());
    }

    public function test_zero_deduplicates_equality_recovers_and_recurrence_opens_new_episode(): void
    {
        $s = $this->subscription('impressions', ['minimum' => 5]);
        $this->apply($s, [$this->row()]);
        $first = Alert::first();
        $this->apply($s, [$this->row(count: 4)]);
        $this->assertSame(1, Alert::count());
        $this->apply($s, [$this->row(count: 5)]);
        $this->assertSame('resolved', $first->fresh()->status);
        $this->apply($s, [$this->row()]);
        $this->assertSame(2, Alert::count());
        $this->assertSame($first->id, Alert::latest('id')->first()->previous_alert_id);
    }

    public function test_two_rules_same_entity_do_not_resolve_each_other(): void
    {
        $a = $this->subscription('impressions');
        $b = $this->subscription('impressions');
        $this->apply($a, [$this->row()]);
        $this->apply($b, [$this->row()]);
        $this->apply($a, [$this->row(count: 1)]);
        $this->assertSame(1, Alert::whereNotNull('active_key')->count());
        $this->assertSame($b->id, Alert::whereNotNull('active_key')->first()->metadata['metric_subscription_id']);
    }

    public function test_separate_and_combined_results_never_mask_platform_failure(): void
    {
        $s = $this->subscription('impressions', ['grouping' => 'together']);
        $this->apply($s, [$this->row(), $this->row('google', 1000)]);
        $this->assertCount(1, Alert::first()->metadata['rows']);
        $this->assertSame('meta', Alert::first()->metadata['rows'][0]['platform']);
        $this->apply($s, [$this->row(), $this->row('google', 0)]);
        $this->assertSame('superseded', Alert::first()->status);
        $this->assertSame('meta_google', Alert::latest('id')->first()->platform);
        $this->assertCount(2, Alert::latest('id')->first()->metadata['rows']);
    }

    public function test_query_and_notify_schedules_are_independent_with_overnight_and_weekends(): void
    {
        $settings = array_replace(SaveMetricMonitor::defaults(), ['query_days' => [1], 'query_start' => '22:00', 'query_end' => '06:00', 'notify_days' => [2], 'notify_start' => '09:00', 'notify_end' => '18:00']);
        $schedule = app(MetricSchedule::class);
        $this->assertTrue($schedule->allows($settings, 'query', Carbon::parse('2026-09-15 05:00', 'America/Bogota')));
        $this->assertFalse($schedule->allows($settings, 'notify', Carbon::parse('2026-09-15 05:00', 'America/Bogota')));
        $this->assertFalse($schedule->allows($settings, 'query', now()));
        $this->assertTrue($schedule->allows($settings, 'notify', now()));
        $this->assertFalse($schedule->allows($settings, 'notify', Carbon::parse('2026-09-20 10:00', 'America/Bogota')));
    }

    public function test_notifications_wait_until_window_and_respect_acknowledgement_limits_and_freshness(): void
    {
        $s = $this->subscription('impressions', ['notify_start' => '10:00', 'notify_end' => '18:00', 'requires_response' => true, 'max_notifications' => 2]);
        $this->apply($s, [$this->row()]);
        $service = app(MetricNotificationService::class);
        $service->deliver($s->id);
        $this->assertSame(0, AlertNotification::count());
        Carbon::setTestNow(now()->setTime(10, 0));
        $service->deliver($s->id);
        $service->deliver($s->id);
        $this->assertSame(2, AlertNotification::count());
        $recipient = AlertRecipient::where('user_id', 1)->first();
        app(AlertInteractionService::class)->acknowledge($recipient->id, 1, 'Revisado');
        Carbon::setTestNow(now()->addHour());
        $service->deliver($s->id);
        $this->assertSame(3, AlertNotification::count());
        $s->update(['last_checked_at' => now()->subDays(2)]);
        Carbon::setTestNow(now()->addHour());
        $service->deliver($s->id);
        $this->assertSame(3, AlertNotification::count());
    }

    public function test_disabled_customers_subscriptions_and_ignored_incidents_do_not_notify(): void
    {
        $s = $this->subscription('impressions');
        $this->apply($s, [$this->row()]);
        app(AlertInteractionService::class)->manage(Alert::first()->id, 'ignored', 'No aplica');
        app(MetricNotificationService::class)->deliver($s->id);
        $this->assertSame(0, AlertNotification::count());
        app(AlertInteractionService::class)->manage(Alert::first()->id, 'open', 'Reactivar');
        DB::table('customers')->where('id', 1)->update(['status' => false]);
        app(MetricNotificationService::class)->deliver($s->id);
        $this->assertSame(0, AlertNotification::count());
        DB::table('customers')->where('id', 1)->update(['status' => true]);
        $s->update(['enabled' => false]);
        app(MetricNotificationService::class)->deliver($s->id);
        $this->assertSame(0, AlertNotification::count());
    }

    public function test_api_error_preserves_incident_without_sending_or_storing_secrets(): void
    {
        $s = $this->subscription('impressions');
        $this->apply($s, [$this->row()]);
        $mock = Mockery::mock(DirectImpressionReader::class);
        $mock->shouldReceive('read')->once()->andThrow(new \RuntimeException('access_token=SECRET_TEST'));
        $this->app->instance(DirectImpressionReader::class, $mock);
        app(EvaluateMetricSubscription::class)->run($s->id);
        $this->assertSame('unavailable', $s->fresh()->last_status);
        $this->assertStringNotContainsString('SECRET_TEST', $s->fresh()->last_error);
        $this->assertSame('open', Alert::first()->status);
        app(MetricNotificationService::class)->deliver($s->id);
        $this->assertSame(0, AlertNotification::count());
    }

    public function test_warmup_and_missing_entities_do_not_generate_zero_alerts(): void
    {
        $s = $this->subscription('impressions', ['warmup' => true]);
        $this->apply($s, [$this->row()]);
        $this->assertSame(0, Alert::count());
        $s->states()->update(['first_seen_at' => now()->subDays(3)]);
        $this->apply($s, [$this->row()]);
        $this->assertSame(1, Alert::count());
        $this->apply($s, []);
        $this->assertSame('suspended', Alert::first()->status);
        $this->apply($s, [$this->row()]);
        $this->assertSame(1, Alert::count());
    }

    public function test_old_notifier_does_not_deliver_metric_incidents(): void
    {
        $s = $this->subscription('impressions');
        $this->apply($s, [$this->row()]);
        app(AlertNotificationService::class)->evaluate();
        app(AlertNotificationService::class)->deliver(Alert::first()->id, 1);
        $this->assertSame(0, AlertNotification::count());
        app(MetricNotificationService::class)->deliver($s->id);
        $this->assertSame(2, AlertNotification::count());
        $this->assertNull(AlertNotification::first()->rule_id);
    }

    public function test_scheduler_dispatches_only_due_active_subscriptions(): void
    {
        $a = $this->subscription();
        $b = $this->subscription();
        $b->update(['enabled' => false]);
        $this->artisan('alerts:metrics')->assertSuccessful();
        Queue::assertPushed(EvaluateMetricAlertsJob::class, fn ($job) => $job->subscriptionId === $a->id && $job->connection === 'alert_metrics');
        Queue::assertPushed(EvaluateMetricAlertsJob::class, 1);
    }

    public function test_livewire_creation_edit_and_manager_permissions(): void
    {
        Livewire::test(MetricMonitors::class)->call('create')->set('form', $this->form())->call('save')->assertHasNoErrors()->assertSee('Volumen bajo');
        $s = AlertMetricSubscription::first();
        Livewire::test(MetricMonitors::class)->call('edit', $s->id)->assertSee('Leads Quality')->assertSee('Creación de leads')
            ->set('form.settings.window_hours', 72)->call('save')->assertHasNoErrors();
        $this->assertSame(72, $s->fresh()->settings['window_hours']);
        config(['alerts.manager_ids' => [2]]);
        $this->get(route('alerts.metrics'))->assertForbidden();
        Livewire::test(MetricMonitors::class)->assertForbidden();
    }

    private function metaAccount(): void
    {
        DB::table('meta_access_tokens')->insert(['token_type' => MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN, 'is_active' => true, 'long_lived_token' => 'test-token']);
    }

    public function test_meta_direct_hourly_query_includes_zero_inventory_and_completes_pagination(): void
    {
        $this->metaAccount();
        $s = $this->subscription('impressions');
        $meta = Mockery::mock(MetaGraphService::class);
        $meta->shouldReceive('get')->with('act_123', Mockery::any())->once()->andReturn(['timezone_name' => 'America/Bogota', 'account_status' => 1]);
        $meta->shouldReceive('get')->with('act_123/campaigns', Mockery::any())->once()->andReturn(['data' => [['id' => '11', 'name' => 'Activa sin datos', 'effective_status' => 'ACTIVE'], ['id' => '22', 'name' => 'Con datos', 'effective_status' => 'ACTIVE']]]);
        $meta->shouldReceive('get')->with('act_123/insights', Mockery::on(fn ($q) => ! isset($q['after']) && $q['time_increment'] === 1 && isset($q['breakdowns'])))->once()->andReturn(['data' => [], 'paging' => ['next' => 'ignored-url', 'cursors' => ['after' => 'page2']]]);
        $meta->shouldReceive('get')->with('act_123/insights', Mockery::on(fn ($q) => ($q['after'] ?? null) === 'page2'))->once()->andReturn(['data' => [
            ['campaign_id' => '22', 'date_start' => '2026-09-14', 'impressions' => '8', 'hourly_stats_aggregated_by_advertiser_time_zone' => '06:00:00 - 06:59:59'],
            ['campaign_id' => '22', 'date_start' => '2026-09-15', 'impressions' => '50', 'hourly_stats_aggregated_by_advertiser_time_zone' => '06:00:00 - 06:59:59'],
        ]]);
        $this->app->instance(MetaGraphService::class, $meta);
        $rows = app(DirectImpressionReader::class)->read($s, now());
        $this->assertSame([0, 8], array_column($rows, 'count'));
        $this->assertSame('2026-09-15T06:00:00-05:00', $rows[0]['window_end']);
    }

    public function test_google_direct_query_uses_hourly_metrics_and_active_inventory(): void
    {
        $s = $this->subscription('impressions', ['platforms' => ['google']]);
        $credential = new GoogleAdsCredential;
        $credential->id = 1;
        $auth = Mockery::mock(GoogleAdsAuthService::class);
        $auth->shouldReceive('ensureValidAccessToken')->andReturn($credential);
        $client = Mockery::mock(GoogleAdsApiClient::class);
        $client->shouldReceive('normalizeCustomerId')->andReturn('1234567890');
        $client->shouldReceive('searchStream')->with($credential, '1234567890', Mockery::on(fn ($q) => str_contains($q, 'customer.time_zone')))->once()->andReturn($this->googleResponse([['customer' => ['timeZone' => 'America/Bogota']]]));
        $client->shouldReceive('searchStream')->with($credential, '1234567890', Mockery::on(fn ($q) => str_contains($q, 'campaign.name') && ! str_contains($q, 'metrics')))->once()->andReturn($this->googleResponse([['campaign' => ['id' => '11', 'name' => 'Campaña']]]));
        $client->shouldReceive('searchStream')->with($credential, '1234567890', Mockery::on(fn ($q) => str_contains($q, 'segments.hour') && str_contains($q, "campaign.status = 'ENABLED'")))->once()->andReturn($this->googleResponse([
            ['campaign' => ['id' => '11'], 'segments' => ['date' => '2026-09-14', 'hour' => 6], 'metrics' => ['impressions' => '10']],
            ['campaign' => ['id' => '11'], 'segments' => ['date' => '2026-09-14', 'hour' => 5], 'metrics' => ['impressions' => '90']],
        ]));
        $this->app->instance(GoogleAdsAuthService::class, $auth);
        $this->app->instance(GoogleAdsApiClient::class, $client);
        $rows = app(DirectImpressionReader::class)->read($s, now());
        $this->assertSame(10, $rows[0]['count']);
    }

    private function googleResponse(array $results): array
    {
        return ['results' => $results, 'raw' => [['fieldMask' => 'test', 'results' => $results]]];
    }

    public function test_google_ad_daily_query_avoids_unsupported_hour_segment(): void
    {
        $s = $this->subscription('impressions', ['platforms' => ['google'], 'level' => 'ad', 'window_mode' => 'days']);
        $credential = new GoogleAdsCredential;
        $credential->id = 1;
        $auth = Mockery::mock(GoogleAdsAuthService::class);
        $auth->shouldReceive('ensureValidAccessToken')->andReturn($credential);
        $client = Mockery::mock(GoogleAdsApiClient::class);
        $client->shouldReceive('normalizeCustomerId')->andReturn('1234567890');
        $client->shouldReceive('searchStream')->with($credential, '1234567890', Mockery::on(fn ($q) => str_contains($q, 'customer.time_zone')))->once()->andReturn($this->googleResponse([['customer' => ['timeZone' => 'America/Bogota']]]));
        $client->shouldReceive('searchStream')->with($credential, '1234567890', Mockery::on(fn ($q) => str_contains($q, 'ad_group_ad.ad.name') && ! str_contains($q, 'metrics')))->once()->andReturn($this->googleResponse([['adGroupAd' => ['ad' => ['id' => '11', 'name' => 'Anuncio']]]]));
        $client->shouldReceive('searchStream')->with($credential, '1234567890', Mockery::on(fn ($q) => str_contains($q, 'metrics.impressions') && ! str_contains($q, 'segments.hour') && str_contains($q, "'2026-09-14' AND '2026-09-14'")))->once()->andReturn($this->googleResponse([
            ['adGroupAd' => ['ad' => ['id' => '11']], 'segments' => ['date' => '2026-09-14'], 'metrics' => ['impressions' => '7']],
        ]));
        $this->app->instance(GoogleAdsAuthService::class, $auth);
        $this->app->instance(GoogleAdsApiClient::class, $client);
        $rows = app(DirectImpressionReader::class)->read($s, now());
        $this->assertSame(7, $rows[0]['count']);
        $this->assertSame('2026-09-15T00:00:00-05:00', $rows[0]['window_end']);
    }

    public function test_google_ad_configuration_cannot_claim_hourly_support(): void
    {
        $this->expectException(ValidationException::class);
        $this->subscription('impressions', ['platforms' => ['google'], 'level' => 'ad']);
    }

    public function test_daily_windows_require_whole_days_and_honor_reporting_margin(): void
    {
        $settings = array_replace(SaveMetricMonitor::defaults(), ['window_mode' => 'days', 'window_hours' => 72]);
        [$start, $end] = app(MetricSchedule::class)->window($settings, 'America/Bogota', Carbon::parse('2026-09-15 02:00', 'America/Bogota'));
        $this->assertSame('2026-09-14 00:00', $end->format('Y-m-d H:i'));
        $this->assertSame('2026-09-11 00:00', $start->format('Y-m-d H:i'));
        $this->expectException(ValidationException::class);
        $this->subscription('impressions', ['window_mode' => 'days', 'window_hours' => 25]);
    }

    public function test_meta_daily_report_does_not_require_hour_breakdown(): void
    {
        $this->metaAccount();
        $s = $this->subscription('impressions', ['window_mode' => 'days']);
        $meta = Mockery::mock(MetaGraphService::class);
        $meta->shouldReceive('get')->with('act_123', Mockery::any())->once()->andReturn(['timezone_name' => 'America/Bogota', 'account_status' => 1]);
        $meta->shouldReceive('get')->with('act_123/campaigns', Mockery::any())->once()->andReturn(['data' => [['id' => '11', 'effective_status' => 'ACTIVE']]]);
        $meta->shouldReceive('get')->with('act_123/insights', Mockery::on(fn ($q) => ! isset($q['breakdowns'])))->once()->andReturn(['data' => [['campaign_id' => '11', 'date_start' => '2026-09-14', 'impressions' => '12']]]);
        $this->app->instance(MetaGraphService::class, $meta);
        $this->assertSame(12, app(DirectImpressionReader::class)->read($s, now())[0]['count']);
    }

    public function test_livewire_explains_google_ad_calendar_days(): void
    {
        Livewire::test(MetricMonitors::class)->call('create')->set('form.settings.platforms', ['google'])
            ->set('form.settings.level', 'ad')->assertSet('form.settings.window_mode', 'days')
            ->assertSee('Google Ads requiere este modo para anuncios');
    }

    public function test_detaching_meta_account_stops_pending_notifications(): void
    {
        $s = $this->subscription('impressions');
        $this->apply($s, [$this->row()]);
        DB::table('customer_meta_ad_account')->insert(['customer_id' => 2, 'meta_ad_account_id' => 1]);
        // Pivot takes precedence over the legacy customer_id.
        app(MetricNotificationService::class)->deliver($s->id);
        $this->assertSame(0, AlertNotification::count());
    }

    public function test_missing_group_members_are_suspended_without_claiming_recovery(): void
    {
        $s = $this->subscription('impressions', ['grouping' => 'together']);
        $this->apply($s, [$this->row()]);
        $this->apply($s, []);
        $this->assertSame('suspended', Alert::first()->status);
        $this->assertNull(Alert::first()->resolved_at);
    }

    public function test_edit_during_api_request_discards_old_measurement(): void
    {
        $s = $this->subscription('impressions');
        $mock = Mockery::mock(DirectImpressionReader::class);
        $mock->shouldReceive('read')->once()->andReturnUsing(function () use ($s) {
            $form = $this->form('impressions', ['minimum' => 20]);
            app(SaveMetricMonitor::class)->save($form, $s->monitor_id, $s->id, $s->version);

            return [$this->row()];
        });
        $this->app->instance(DirectImpressionReader::class, $mock);
        app(EvaluateMetricSubscription::class)->run($s->id);
        $this->assertSame('pending', $s->fresh()->last_status);
        $this->assertSame(0, Alert::count());
    }

    public function test_incomplete_meta_pagination_does_not_become_zero(): void
    {
        $this->metaAccount();
        $s = $this->subscription('impressions');
        $meta = Mockery::mock(MetaGraphService::class);
        $meta->shouldReceive('get')->with('act_123', Mockery::any())->andReturn(['timezone_name' => 'America/Bogota', 'account_status' => 1]);
        $meta->shouldReceive('get')->with('act_123/campaigns', Mockery::any())->andReturn(['data' => [['id' => '11', 'effective_status' => 'ACTIVE']]]);
        $meta->shouldReceive('get')->with('act_123/insights', Mockery::any())->andReturn(['data' => [], 'paging' => ['next' => 'next-page-without-cursor']]);
        $this->app->instance(MetaGraphService::class, $meta);
        app(EvaluateMetricSubscription::class)->run($s->id);
        $this->assertSame('unavailable', $s->fresh()->last_status);
        $this->assertSame(0, Alert::count());
    }

    public function test_jobs_recheck_schedule_and_disabled_feature_before_network_calls(): void
    {
        $s = $this->subscription('impressions', ['query_start' => '12:00', 'query_end' => '13:00']);
        $mock = Mockery::mock(DirectImpressionReader::class);
        $mock->shouldNotReceive('read');
        $this->app->instance(DirectImpressionReader::class, $mock);
        app(EvaluateMetricSubscription::class)->run($s->id);
        $this->assertSame('pending', $s->fresh()->last_status);
        config(['alert_metrics.enabled' => false]);
        Carbon::setTestNow(now()->setTime(12, 30));
        app(EvaluateMetricSubscription::class)->run($s->id);
        $this->assertSame('pending', $s->fresh()->last_status);
    }

    public function test_metric_foreign_keys_preserve_existing_customer_and_user_deletions(): void
    {
        $s = $this->subscription();
        DB::table('customers')->where('id', 1)->delete();
        DB::table('users')->where('id', 1)->delete();
        $this->assertNull($s->fresh()->customer_id);
        $this->assertSame(1, $s->users()->count());
    }
}
