<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'leads_customer_created_at_idx');
            $table->index('created_at', 'leads_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_customer_created_at_idx');
            $table->dropIndex('leads_created_at_idx');
        });
    }
};
