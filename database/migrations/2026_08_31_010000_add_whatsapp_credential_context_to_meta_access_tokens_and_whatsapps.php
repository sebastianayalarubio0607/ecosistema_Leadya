<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meta_access_tokens')) {
            Schema::table('meta_access_tokens', function (Blueprint $table) {
                if (! Schema::hasColumn('meta_access_tokens', 'name')) {
                    $table->string('name')->nullable()->after('id');
                }

                if (! Schema::hasColumn('meta_access_tokens', 'purpose')) {
                    $table->string('purpose', 40)->nullable()->after('token_type')->index('mat_purpose_idx');
                }

                if (! Schema::hasColumn('meta_access_tokens', 'is_default')) {
                    $table->boolean('is_default')->default(false)->after('is_active')->index('mat_default_idx');
                }

                if (! Schema::hasColumn('meta_access_tokens', 'meta_business_id')) {
                    $table->string('meta_business_id')->nullable()->after('meta_app_secret');
                }

                if (! Schema::hasColumn('meta_access_tokens', 'meta_system_user_id')) {
                    $table->string('meta_system_user_id')->nullable()->after('meta_business_id');
                }

                if (! Schema::hasColumn('meta_access_tokens', 'permissions_payload')) {
                    $table->json('permissions_payload')->nullable()->after('last_error');
                }

                if (! Schema::hasColumn('meta_access_tokens', 'last_validated_at')) {
                    $table->timestamp('last_validated_at')->nullable()->after('permissions_payload');
                }
            });
        }

        if (Schema::hasTable('meta_whatsapps')) {
            Schema::table('meta_whatsapps', function (Blueprint $table) {
                if (! Schema::hasColumn('meta_whatsapps', 'meta_access_token_id')) {
                    $table->foreignId('meta_access_token_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('meta_access_tokens')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('meta_whatsapps', 'subscription_meta_access_token_id')) {
                    $table->foreignId('subscription_meta_access_token_id')
                        ->nullable()
                        ->after('token_can_view_account')
                        ->constrained('meta_access_tokens')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('meta_whatsapps', 'subscription_meta_app_id')) {
                    $table->string('subscription_meta_app_id')->nullable()->after('subscription_meta_access_token_id');
                }

                if (! Schema::hasColumn('meta_whatsapps', 'subscription_token_source')) {
                    $table->string('subscription_token_source', 40)->nullable()->after('subscription_meta_app_id');
                }
            });
        }

        if (Schema::hasTable('customer_meta_whatsapp')) {
            Schema::table('customer_meta_whatsapp', function (Blueprint $table) {
                if (! Schema::hasColumn('customer_meta_whatsapp', 'meta_access_token_id')) {
                    $table->foreignId('meta_access_token_id')
                        ->nullable()
                        ->after('meta_whatsapp_id')
                        ->constrained('meta_access_tokens')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_meta_whatsapp') && Schema::hasColumn('customer_meta_whatsapp', 'meta_access_token_id')) {
            Schema::table('customer_meta_whatsapp', function (Blueprint $table) {
                $table->dropConstrainedForeignId('meta_access_token_id');
            });
        }

        if (Schema::hasTable('meta_whatsapps')) {
            Schema::table('meta_whatsapps', function (Blueprint $table) {
                foreach (['subscription_token_source', 'subscription_meta_app_id'] as $column) {
                    if (Schema::hasColumn('meta_whatsapps', $column)) {
                        $table->dropColumn($column);
                    }
                }

                if (Schema::hasColumn('meta_whatsapps', 'subscription_meta_access_token_id')) {
                    $table->dropConstrainedForeignId('subscription_meta_access_token_id');
                }

                if (Schema::hasColumn('meta_whatsapps', 'meta_access_token_id')) {
                    $table->dropConstrainedForeignId('meta_access_token_id');
                }
            });
        }

        if (Schema::hasTable('meta_access_tokens')) {
            Schema::table('meta_access_tokens', function (Blueprint $table) {
                foreach ([
                    'last_validated_at',
                    'permissions_payload',
                    'meta_system_user_id',
                    'meta_business_id',
                    'is_default',
                    'purpose',
                    'name',
                ] as $column) {
                    if (Schema::hasColumn('meta_access_tokens', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
