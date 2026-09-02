<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MetaAdAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetaAdAccountCustomerPivotMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        foreach ([
            '2025_04_25_210705_create_customers_table.php',
            '2026_01_27_000001_create_meta_ad_accounts_table.php',
            '2026_01_27_000006_add_customer_id_to_meta_ad_accounts_table.php',
        ] as $migrationPath) {
            $migration = require database_path('migrations/'.$migrationPath);
            $migration->up();
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customer_meta_ad_account');
        Schema::dropIfExists('meta_ad_accounts');
        Schema::dropIfExists('customers');

        parent::tearDown();
    }

    public function test_it_backfills_legacy_relations_without_overwriting_an_existing_default(): void
    {
        $legacyCustomer = Customer::query()->create([
            'name' => 'Cliente Legacy',
            'status' => true,
        ]);
        $defaultCustomer = Customer::query()->create([
            'name' => 'Cliente Default',
            'status' => true,
        ]);

        $account = MetaAdAccount::withoutEvents(fn () => MetaAdAccount::query()->create([
            'customer_id' => $legacyCustomer->id,
            'meta_account_id' => '123456789',
            'name' => 'Cuenta Compartida',
            'status' => 'active',
        ]));

        $migration = require database_path('migrations/2026_09_01_000000_create_customer_meta_ad_account_table.php');
        $migration->up();

        $this->assertSame(
            $legacyCustomer->id,
            DB::table('customer_meta_ad_account')
                ->where('meta_ad_account_id', $account->id)
                ->where('is_default_for_whatsapp_leads', true)
                ->value('customer_id')
        );

        DB::table('customer_meta_ad_account')
            ->where('meta_ad_account_id', $account->id)
            ->where('customer_id', $legacyCustomer->id)
            ->update(['is_default_for_whatsapp_leads' => false]);

        DB::table('customer_meta_ad_account')->insert([
            'customer_id' => $defaultCustomer->id,
            'meta_ad_account_id' => $account->id,
            'is_default_for_whatsapp_leads' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_01_000000_create_customer_meta_ad_account_table.php');
        $migration->up();

        $this->assertSame(2, DB::table('customer_meta_ad_account')->where('meta_ad_account_id', $account->id)->count());
        $this->assertSame(
            $defaultCustomer->id,
            DB::table('customer_meta_ad_account')
                ->where('meta_ad_account_id', $account->id)
                ->where('is_default_for_whatsapp_leads', true)
                ->value('customer_id')
        );
    }

    public function test_rollback_does_not_drop_existing_customer_ad_account_relations(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Cliente Seguro',
            'status' => true,
        ]);

        MetaAdAccount::withoutEvents(fn () => MetaAdAccount::query()->create([
            'customer_id' => $customer->id,
            'meta_account_id' => '987654321',
            'name' => 'Cuenta Segura',
            'status' => 'active',
        ]));

        $migration = require database_path('migrations/2026_09_01_000000_create_customer_meta_ad_account_table.php');
        $migration->up();
        $migration->down();

        $this->assertTrue(Schema::hasTable('customer_meta_ad_account'));
        $this->assertSame(1, DB::table('customer_meta_ad_account')->count());
    }

    public function test_customer_meta_ad_account_eager_loading_uses_unambiguous_columns(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Cliente Vista',
            'status' => true,
        ]);

        $account = MetaAdAccount::withoutEvents(fn () => MetaAdAccount::query()->create([
            'customer_id' => $customer->id,
            'meta_account_id' => '123456789',
            'name' => 'Cuenta Vista',
            'status' => 'active',
        ]));

        $migration = require database_path('migrations/2026_09_01_000000_create_customer_meta_ad_account_table.php');
        $migration->up();

        $loadedCustomer = Customer::query()
            ->with([
                'metaAdAccounts' => fn ($query) => $query->select([
                    'meta_ad_accounts.id',
                    'meta_ad_accounts.customer_id',
                    'meta_ad_accounts.meta_account_id',
                    'meta_ad_accounts.name',
                ]),
                'metaAdAccounts.customers' => fn ($query) => $query->select(['customers.id', 'customers.name']),
            ])
            ->findOrFail($customer->id);

        $this->assertTrue($loadedCustomer->metaAdAccounts->first()->is($account));
        $this->assertSame('Cliente Vista', $loadedCustomer->metaAdAccounts->first()->customers->first()->name);
    }
}
