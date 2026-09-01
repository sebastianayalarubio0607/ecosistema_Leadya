<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'google_ads_conversion_template_histories';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('google_ads_conversion_template_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('google_ads_customer_id', 32)->nullable();
                $table->string('template_name')->nullable();
                $table->string('action', 80);
                $table->string('actor_type', 32)->default('system');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('payload')->nullable();
                $table->json('response')->nullable();
                $table->string('request_id')->nullable();
                $table->boolean('success')->default(false);
                $table->text('error_message')->nullable();
                $table->timestamp('consulted_at')->nullable();
                $table->timestamps();
            });
        }

        $this->addMissingIndexes();
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function addMissingIndexes(): void
    {
        $indexes = [
            'gads_conv_tpl_hist_template_idx' => ['google_ads_conversion_template_id'],
            'gads_conv_tpl_hist_customer_idx' => ['customer_id'],
            'gads_conv_tpl_hist_user_idx' => ['user_id'],
            'gads_conv_tpl_hist_gads_customer_idx' => ['google_ads_customer_id'],
            'gads_conv_tpl_hist_action_idx' => ['action'],
            'gads_conv_tpl_hist_actor_type_idx' => ['actor_type'],
            'gads_conv_tpl_hist_actor_id_idx' => ['actor_id'],
            'gads_conv_tpl_hist_request_idx' => ['request_id'],
            'gads_conv_tpl_hist_success_idx' => ['success'],
            'gads_conv_tpl_hist_consulted_idx' => ['consulted_at'],
            'gads_conv_tpl_hist_created_idx' => ['created_at'],
        ];

        Schema::table(self::TABLE, function (Blueprint $table) use ($indexes) {
            foreach ($indexes as $name => $columns) {
                if (! $this->indexExists($name)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select('SHOW INDEX FROM `'.self::TABLE.'` WHERE Key_name = ?', [$name]))->isNotEmpty();
    }
};
