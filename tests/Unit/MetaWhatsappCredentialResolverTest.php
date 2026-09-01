<?php

use App\Http\Services\Meta\Subscription\Whatsapp\MetaWhatsappCredentialResolver;
use App\Models\Customer;
use App\Models\MetaAccessToken;
use App\Models\MetaWhatsapp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    Queue::fake();

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->boolean('status')->nullable();
        $table->timestamps();
    });

    Schema::create('meta_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->foreignId('customer_id')->nullable();
        $table->string('token_type')->nullable();
        $table->string('purpose', 40)->nullable();
        $table->longText('short_lived_token')->nullable();
        $table->longText('long_lived_token')->nullable();
        $table->string('meta_app_id')->nullable();
        $table->string('meta_app_secret')->nullable();
        $table->string('meta_business_id')->nullable();
        $table->string('meta_system_user_id')->nullable();
        $table->integer('expires_in')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->boolean('is_default')->default(false);
        $table->timestamp('refresh_last_run_at')->nullable();
        $table->text('last_error')->nullable();
        $table->json('permissions_payload')->nullable();
        $table->timestamp('last_validated_at')->nullable();
        $table->timestamps();
    });

    Schema::create('meta_whatsapps', function (Blueprint $table) {
        $table->id();
        $table->foreignId('meta_access_token_id')->nullable();
        $table->string('waba_id', 64)->unique();
        $table->string('phone_number_id', 64)->nullable();
        $table->string('wa_id', 64)->nullable();
        $table->boolean('status')->default(true);
        $table->json('subscribed_apps')->nullable();
        $table->boolean('is_subscribed_to_meta_app')->default(false);
        $table->boolean('token_can_view_account')->nullable();
        $table->foreignId('subscription_meta_access_token_id')->nullable();
        $table->string('subscription_meta_app_id')->nullable();
        $table->string('subscription_token_source', 40)->nullable();
        $table->timestamp('subscription_checked_at')->nullable();
        $table->timestamp('subscription_updated_at')->nullable();
        $table->text('subscription_last_error')->nullable();
        $table->timestamps();
    });

    Schema::create('customer_meta_whatsapp', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id');
        $table->foreignId('meta_whatsapp_id');
        $table->foreignId('meta_access_token_id')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('customer_meta_whatsapp');
    Schema::dropIfExists('meta_whatsapps');
    Schema::dropIfExists('meta_access_tokens');
    Schema::dropIfExists('customers');
});

test('whatsapp credential resolver uses customer token before default', function () {
    $customer = Customer::query()->create([
        'name' => 'Cliente WhatsApp',
        'status' => true,
    ]);

    MetaAccessToken::query()->create([
        'name' => 'Default WhatsApp',
        'purpose' => MetaAccessToken::PURPOSE_WHATSAPP,
        'token_type' => MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN,
        'long_lived_token' => 'default-token',
        'meta_app_id' => 'app-default',
        'is_active' => true,
        'is_default' => true,
    ]);

    $customerToken = MetaAccessToken::query()->create([
        'name' => 'Customer WhatsApp',
        'customer_id' => $customer->id,
        'purpose' => MetaAccessToken::PURPOSE_WHATSAPP,
        'token_type' => MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN,
        'long_lived_token' => 'customer-token',
        'meta_app_id' => 'app-customer',
        'is_active' => true,
        'is_default' => false,
    ]);

    $whatsapp = MetaWhatsapp::query()->create([
        'waba_id' => '123456789',
        'status' => true,
    ]);
    $whatsapp->customers()->attach($customer->id);

    $credential = app(MetaWhatsappCredentialResolver::class)->resolve($whatsapp->load('customers'));

    expect($credential->accessToken->id)->toBe($customerToken->id)
        ->and($credential->token)->toBe('customer-token')
        ->and($credential->metaAppId)->toBe('app-customer')
        ->and($credential->source)->toBe('customer');
});

test('whatsapp credential resolver allows explicit waba token with a different app', function () {
    $customer = Customer::query()->create([
        'name' => 'Cliente App Distinta',
        'status' => true,
    ]);

    MetaAccessToken::query()->create([
        'name' => 'Customer WhatsApp',
        'customer_id' => $customer->id,
        'purpose' => MetaAccessToken::PURPOSE_WHATSAPP,
        'token_type' => MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN,
        'long_lived_token' => 'customer-token',
        'meta_app_id' => 'app-customer',
        'is_active' => true,
        'is_default' => false,
    ]);

    $wabaToken = MetaAccessToken::query()->create([
        'name' => 'WABA WhatsApp',
        'purpose' => MetaAccessToken::PURPOSE_WHATSAPP,
        'token_type' => MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN,
        'long_lived_token' => 'waba-token',
        'meta_app_id' => 'app-waba',
        'is_active' => true,
        'is_default' => false,
    ]);

    $whatsapp = MetaWhatsapp::query()->create([
        'meta_access_token_id' => $wabaToken->id,
        'waba_id' => '987654321',
        'status' => true,
    ]);
    $whatsapp->customers()->attach($customer->id);

    $credential = app(MetaWhatsappCredentialResolver::class)->resolve($whatsapp->load('customers'));

    expect($credential->accessToken->id)->toBe($wabaToken->id)
        ->and($credential->token)->toBe('waba-token')
        ->and($credential->metaAppId)->toBe('app-waba')
        ->and($credential->source)->toBe('waba');
});

test('whatsapp credential resolver falls back to default token when no customer token exists', function () {
    $customer = Customer::query()->create([
        'name' => 'Cliente Default',
        'status' => true,
    ]);

    $defaultToken = MetaAccessToken::query()->create([
        'name' => 'Default WhatsApp',
        'purpose' => MetaAccessToken::PURPOSE_WHATSAPP,
        'token_type' => MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN,
        'long_lived_token' => 'default-token',
        'meta_app_id' => 'app-shared',
        'is_active' => true,
        'is_default' => true,
    ]);

    $whatsapp = MetaWhatsapp::query()->create([
        'waba_id' => '555555555',
        'status' => true,
    ]);
    $whatsapp->customers()->attach($customer->id);

    $credential = app(MetaWhatsappCredentialResolver::class)->resolve($whatsapp->load('customers'));

    expect($credential->accessToken->id)->toBe($defaultToken->id)
        ->and($credential->token)->toBe('default-token')
        ->and($credential->metaAppId)->toBe('app-shared')
        ->and($credential->source)->toBe('default');
});
