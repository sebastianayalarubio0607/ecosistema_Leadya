<?php

namespace Tests\Unit;

use App\Http\Services\Meta\MetaAssetStatusSyncService;
use App\Http\Services\Meta\MetaGraphService;
use App\Livewire\Alerts\AlertHistory;
use App\Livewire\Alerts\AlertInbox;
use App\Livewire\Alerts\AlertRules;
use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertNotification;
use App\Models\Alerts\AlertObservation;
use App\Models\Alerts\AlertRecipient;
use App\Models\Alerts\AlertRule;
use App\Models\MetaAdAccount;
use App\Models\MetaAdAccountStatusHistory;
use App\Models\User;
use App\Services\Alerts\AlertInteractionService;
use App\Services\Alerts\AlertNotificationService;
use App\Services\Alerts\AlertRuleSchedule;
use App\Services\Alerts\MetaAlertObservationService;
use App\Services\Alerts\ProcessAlertObservations;
use App\Services\Alerts\ReconcileMetaAlerts;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AlertsModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No RefreshDatabase: never migrate, truncate or reconnect to the user's MySQL.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'alerts.enabled' => true, 'alerts.manager_ids' => [], 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-09-14 09:00:00', 'America/Bogota'));
        Queue::fake();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('meta_ad_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('meta_account_id');
            $t->string('name');
            $t->string('status');
            $t->string('estado_meta')->nullable();
            $t->string('estado_meta_nombre')->nullable();
            $t->timestamp('estado_meta_checked_at')->nullable();
            $t->json('estado_meta_payload')->nullable();
            $t->text('estado_meta_last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('customer_meta_ad_account', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('meta_ad_account_id');
            $t->boolean('is_default_for_whatsapp_leads')->default(false);
            $t->timestamps();
        });
        Schema::create('meta_ad_account_status_histories', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('meta_ad_account_id')->nullable();
            $t->unsignedBigInteger('meta_webhook_event_id')->nullable();
            $t->string('meta_account_id')->nullable();
            $t->string('estado_meta_anterior')->nullable();
            $t->string('estado_meta_anterior_nombre')->nullable();
            $t->string('estado_meta_nuevo')->nullable();
            $t->string('estado_meta_nuevo_nombre')->nullable();
            $t->boolean('changed');
            $t->string('query_type')->nullable();
            $t->timestamp('consulted_at');
            $t->json('payload')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_09_14_120000_create_alert_module_tables.php'))->up();
        DB::table('customers')->insert([['id' => 1, 'name' => 'Cliente A'], ['id' => 2, 'name' => 'Cliente B']]);
        User::create(['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'test-password']);
        User::create(['name' => 'Beto', 'email' => 'beto@example.test', 'password' => 'test-password']);
        $this->actingAs(User::first());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $external = '123', array $customers = [1], ?string $state = '1'): MetaAdAccount
    {
        $account = MetaAdAccount::withoutEvents(fn () => MetaAdAccount::create(['meta_account_id' => $external, 'name' => 'Cuenta '.$external, 'status' => 'active', 'estado_meta' => $state, 'estado_meta_checked_at' => now()]));
        foreach ($customers as $id) {
            DB::table('customer_meta_ad_account')->insert(['customer_id' => $id, 'meta_ad_account_id' => $account->id]);
        }

        return $account;
    }

    private function observation(MetaAdAccount $account, ?string $before = '1', string $after = '2', string $key = 'test:1'): void
    {
        $account->forceFill(['estado_meta' => $after, 'estado_meta_checked_at' => now()])->saveQuietly();
        app(MetaAlertObservationService::class)->capture($account, $key, $before, $after, now());
        app(ProcessAlertObservations::class)->run();
    }

    private function rule(array $overrides = [], array $users = [1, 2]): AlertRule
    {
        $rule = AlertRule::create(array_merge([
            'customer_id' => 1, 'category_id' => 1, 'subcategory_id' => 1, 'type_id' => 1, 'scope_key' => 'type:1', 'name' => 'Regla',
            'enabled' => true, 'timezone' => 'America/Bogota', 'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'starts_at' => now()->subDay(), 'ends_at' => null, 'max_notifications' => 2, 'min_alerts' => 1, 'interval_minutes' => 60,
            'channel' => 'internal', 'severity' => 'warning', 'requires_response' => true,
        ], $overrides));
        $rule->users()->sync($users);

        return $rule;
    }

    public function test_deduplication_recovery_and_recurrence_are_separate_episodes(): void
    {
        $account = $this->account();
        $this->observation($account);
        $this->observation($account, '1', '2', 'test:duplicate-source');
        $this->observation($account, '2', '3', 'test:payment');
        $this->assertSame(1, Alert::count());
        $this->assertSame('3', Alert::first()->current_state);
        $this->observation($account, '3', '1', 'test:recovery');
        $this->assertSame('resolved', Alert::first()->status);
        $this->assertNull(Alert::first()->active_key);
        $this->observation($account, '1', '2', 'test:recurrence');
        $this->assertSame(2, Alert::count());
        $this->assertSame(1, Alert::latest('id')->first()->previous_alert_id);
    }

    public function test_shared_account_creates_one_episode_per_customer_without_changing_pivot(): void
    {
        $account = $this->account('act_123', [1, 2]);
        $before = DB::table('customer_meta_ad_account')->get()->toJson();
        $this->observation($account);
        $this->assertSame([1, 2], Alert::orderBy('customer_id')->pluck('customer_id')->all());
        $this->assertSame('123', Alert::first()->entity_id);
        $this->assertSame($before, DB::table('customer_meta_ad_account')->get()->toJson());
        $this->rule(['customer_id' => 2], [2]);
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame([2], AlertRecipient::pluck('user_id')->all());
        $this->assertSame(2, AlertRecipient::first()->alert->customer_id);
    }

    public function test_baseline_errors_missing_status_and_internal_inactivity_do_not_open_alerts(): void
    {
        $account = $this->account();
        $this->observation($account, null, '2');
        $this->assertSame(0, Alert::count());
        app(MetaAlertObservationService::class)->capture($account, 'empty', '1', null, now());
        $account->status = 'inactive';
        $account->saveQuietly();
        $this->observation($account, '2', '1', 'active');
        $this->observation($account, '1', '2', 'inactive');
        $this->assertSame(0, Alert::count());
        $this->assertFalse(AlertObservation::where('source_key', 'empty')->exists());
    }

    public function test_deliveries_are_idempotent_and_limits_are_per_user(): void
    {
        $account = $this->account();
        $this->rule();
        $this->observation($account);
        $service = app(AlertNotificationService::class);
        $service->evaluate();
        $service->evaluate();
        $this->assertSame(2, AlertNotification::count());
        $ana = AlertRecipient::where('user_id', 1)->first();
        app(AlertInteractionService::class)->acknowledge($ana->id, 1, 'Estoy enterada.');
        Carbon::setTestNow(now()->addHour());
        $service->evaluate();
        $this->assertSame(3, AlertNotification::count());
        Carbon::setTestNow(now()->addHours(2));
        $service->evaluate();
        $this->assertSame(3, AlertNotification::count());
        $this->assertSame(1, $ana->fresh()->notification_count);
    }

    public function test_read_and_recovery_do_not_clear_required_response(): void
    {
        $account = $this->account();
        $this->rule();
        $this->observation($account);
        app(AlertNotificationService::class)->evaluate();
        $recipient = AlertRecipient::first();
        $service = app(AlertInteractionService::class);
        $service->read($recipient->id, 1);
        $this->observation($account, '2', '1', 'recovered');
        $this->assertSame(2, AlertRecipient::pending()->count());
        try {
            $service->dismiss($recipient->id, 1);
            $this->fail('Required response bypassed');
        } catch (ValidationException) {
        }
        try {
            $service->acknowledge($recipient->id, 1, '   ');
            $this->fail('Blank response accepted');
        } catch (ValidationException) {
        }
        $service->acknowledge($recipient->id, 1, 'Confirmado');
        $service->acknowledge($recipient->id, 1, 'Reintento');
        $this->assertSame(1, DB::table('alert_comments')->count());
        $this->assertSame(1, AlertRecipient::pending()->count());
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(2, AlertNotification::count());
    }

    public function test_users_cannot_read_or_acknowledge_other_users_deliveries(): void
    {
        $account = $this->account();
        $this->rule([], [2]);
        $this->observation($account);
        app(AlertNotificationService::class)->evaluate();
        Livewire::test(AlertInbox::class)->assertDontSee('Cuenta 123');
        $this->expectException(ModelNotFoundException::class);
        app(AlertInteractionService::class)->acknowledge(AlertRecipient::first()->id, 1, 'No autorizado');
    }

    public function test_schedule_handles_overnight_windows_timezone_and_indefinite_end(): void
    {
        $rule = $this->rule(['weekdays' => [1], 'starts_time' => '22:00:00', 'ends_time' => '06:00:00']);
        $schedule = app(AlertRuleSchedule::class);
        $this->assertTrue($schedule->allows($rule, Carbon::parse('2026-09-15 02:00:00', 'America/Bogota')));
        $this->assertFalse($schedule->allows($rule, Carbon::parse('2026-09-15 06:00:00', 'America/Bogota')));
        $this->assertFalse($schedule->allows($rule, Carbon::parse('2026-09-15 23:00:00', 'America/Bogota')));
        $this->assertTrue($schedule->allows($rule, Carbon::parse('2026-09-15 03:00:00', 'UTC')));
        $rule->ends_at = Carbon::parse('2026-09-14 23:00:00', 'America/Bogota');
        $this->assertFalse($schedule->allows($rule, Carbon::parse('2026-09-15 02:00:00', 'America/Bogota')));
    }

    public function test_threshold_counts_distinct_incidents_and_specific_exclusion_wins(): void
    {
        $rule = $this->rule(['min_alerts' => 2]);
        $first = $this->account();
        $this->observation($first);
        $this->observation($first, '2', '2', 'repeat');
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(0, AlertNotification::count());
        $this->observation($this->account('456'), '1', '2', 'another');
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(4, AlertNotification::count());
        $rule->update(['enabled' => false]);
        $this->rule(['type_id' => null, 'subcategory_id' => null, 'scope_key' => 'category:1']);
        Carbon::setTestNow(now()->addHour());
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(4, AlertNotification::count());
    }

    public function test_ignored_incident_does_not_reappear_until_recovery(): void
    {
        $account = $this->account();
        $this->observation($account);
        $this->rule();
        app(AlertInteractionService::class)->manage(Alert::first()->id, 'ignored', 'Situación conocida');
        $this->observation($account, '1', '2', 'duplicate');
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(1, Alert::count());
        $this->assertSame(0, AlertNotification::count());
        $this->assertSame('ignored', Alert::first()->status);
    }

    public function test_existing_meta_sync_records_history_and_new_observer_does_not_change_its_result(): void
    {
        $account = $this->account();
        Http::fake(['*' => Http::response(['name' => $account->name, 'account_status' => 2, 'disable_reason' => 0])]);
        $service = new MetaAssetStatusSyncService(new MetaGraphService);
        (new \ReflectionMethod($service, 'syncAdAccount'))->invoke($service, $account, 'manual', null, 'test-token');
        $this->assertSame('2', $account->fresh()->estado_meta);
        $this->assertSame(1, MetaAdAccountStatusHistory::count());
        $this->assertSame(1, AlertObservation::count());
        app(ProcessAlertObservations::class)->run();
        $this->assertSame(1, Alert::count());
        Queue::assertNothingPushed();
    }

    public function test_import_observation_and_history_reconciliation_are_idempotent(): void
    {
        $account = $this->account();
        $account->update(['estado_meta' => '2', 'estado_meta_checked_at' => now()]);
        $this->assertSame(1, AlertObservation::count());
        app(ProcessAlertObservations::class)->run();
        app(ReconcileMetaAlerts::class)->run();
        app(ProcessAlertObservations::class)->run();
        $this->assertSame(1, Alert::count());
    }

    public function test_new_foreign_keys_do_not_block_existing_customer_or_user_deletions(): void
    {
        $account = $this->account();
        $this->rule();
        $this->observation($account);
        app(AlertNotificationService::class)->evaluate();
        app(AlertInteractionService::class)->acknowledge(AlertRecipient::first()->id, 1, 'Recibido');
        DB::table('customers')->where('id', 1)->delete();
        DB::table('users')->where('id', 1)->delete();
        $this->assertNull(Alert::first()->customer_id);
        $this->assertSame('Cliente A', Alert::first()->customer_name);
        $this->assertSame(1, DB::table('alert_comments')->count());
    }

    public function test_livewire_rule_creation_edit_and_inbox_confirmation(): void
    {
        Livewire::test(AlertRules::class)->call('create')->set('form.customer_ids', [1, 2])->set('form.user_ids', [1])
            ->set('form.requires_response', true)->call('save')->assertHasNoErrors();
        $this->assertSame(2, AlertRule::count());
        Livewire::test(AlertRules::class)->call('edit', 1)->set('form.max_notifications', 3)->call('save')->assertHasNoErrors();
        $this->observation($this->account());
        app(AlertNotificationService::class)->evaluate();
        Livewire::test(AlertInbox::class)->call('open', AlertRecipient::first()->id)->assertSee('Comentario obligatorio')
            ->set('comment', 'Confirmación desde Livewire')->call('acknowledge')->assertHasNoErrors()->assertSee('Confirmaste');
        Livewire::test(AlertHistory::class)->call('open', Alert::first()->id)->assertSee('Confirmación desde Livewire')->call('close')->assertSet('selectedId', null);
        $this->get('/alerts')->assertOk()->assertSee('Centro de alertas');
    }

    public function test_management_allowlist_is_enforced_on_routes_and_livewire(): void
    {
        config(['alerts.manager_ids' => ['2']]);
        $this->get('/alerts/rules')->assertForbidden();
        Livewire::test(AlertRules::class)->assertForbidden();
        $this->get('/alerts')->assertOk();
    }

    public function test_module_can_be_disabled_without_breaking_existing_account_saves(): void
    {
        config(['alerts.enabled' => false]);
        $account = $this->account();
        $account->update(['estado_meta' => '2']);
        $this->assertSame(0, AlertObservation::count());
        $this->get('/alerts')->assertOk()->assertSee('todavía no está disponible');
    }

    public function test_failed_alert_persistence_does_not_break_the_existing_meta_sync(): void
    {
        $account = $this->account();
        Schema::drop('alert_observations');
        Http::fake(['*' => Http::response(['account_status' => 2])]);
        $service = new MetaAssetStatusSyncService(new MetaGraphService);
        (new \ReflectionMethod($service, 'syncAdAccount'))->invoke($service, $account, 'scheduled', null, 'test-token');
        $this->assertSame('2', $account->fresh()->estado_meta);
        $this->assertNull($account->fresh()->estado_meta_last_error);
        $this->assertSame(1, MetaAdAccountStatusHistory::count());
    }

    public function test_reconciliation_recovers_history_missed_by_observer(): void
    {
        $account = $this->account();
        $account->forceFill(['estado_meta' => '2'])->saveQuietly();
        MetaAdAccountStatusHistory::withoutEvents(fn () => MetaAdAccountStatusHistory::create([
            'meta_ad_account_id' => $account->id, 'estado_meta_anterior' => '1', 'estado_meta_nuevo' => '2',
            'changed' => true, 'consulted_at' => now(),
        ]));
        app(ReconcileMetaAlerts::class)->run();
        app(ProcessAlertObservations::class)->run();
        $this->assertSame(1, Alert::count());
        app(ReconcileMetaAlerts::class)->run();
        app(ProcessAlertObservations::class)->run();
        $this->assertSame(1, Alert::count());
    }

    public function test_stale_recovery_does_not_close_a_newer_incident(): void
    {
        $account = $this->account();
        $this->observation($account);
        app(MetaAlertObservationService::class)->capture($account, 'old:recovery', '2', '1', now()->subHour());
        app(ProcessAlertObservations::class)->run();
        $this->assertSame('open', Alert::first()->status);
        $this->assertSame('2', Alert::first()->current_state);
    }

    public function test_off_hours_are_re_evaluated_without_a_new_meta_transition(): void
    {
        $account = $this->account();
        $this->rule(['starts_time' => '10:00:00', 'ends_time' => '17:00:00']);
        $this->observation($account);
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(0, AlertNotification::count());
        Carbon::setTestNow(now()->addHour());
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(2, AlertNotification::count());
    }

    public function test_removing_recipient_stops_reminders_and_preserves_history(): void
    {
        $account = $this->account();
        $rule = $this->rule();
        $this->observation($account);
        app(AlertNotificationService::class)->evaluate();
        $rule->users()->sync([2]);
        Carbon::setTestNow(now()->addHour());
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(1, AlertRecipient::where('user_id', 1)->first()->notification_count);
        $this->assertSame(2, AlertRecipient::where('user_id', 2)->first()->notification_count);
    }

    public function test_editing_a_rule_does_not_reset_delivery_limits_and_rejects_stale_edits(): void
    {
        $account = $this->account();
        $this->rule(['max_notifications' => 1]);
        $this->observation($account);
        app(AlertNotificationService::class)->evaluate();
        $editor = Livewire::test(AlertRules::class)->call('edit', 1);
        Livewire::test(AlertRules::class)->call('edit', 1)->set('form.name', 'Actualizada')->call('save')->assertHasNoErrors();
        $editor->call('save')->assertHasErrors('name');
        Carbon::setTestNow(now()->addHour());
        app(AlertNotificationService::class)->evaluate();
        $this->assertSame(2, AlertNotification::count());
    }
}
