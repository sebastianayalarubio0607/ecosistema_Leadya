<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('position')->nullable();
            $table->string('city')->nullable();
            $table->string('age')->nullable();
            $table->string('company')->nullable();
            $table->string('country')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('status')->nullable();
            $table->boolean('tc')->nullable();
            $table->json('fields_custom')->nullable();


            $table->string('agent')->nullable();
            $table->string('service_city')->nullable();
            $table->string('children')->nullable();
            $table->string('opening_hours')->nullable();
            $table->string('effective_lead')->nullable();
            $table->string('reference')->nullable();
            $table->string('service')->nullable();
            $table->mediumText('remote_iP')->nullable();
            $table->string('page')->nullable();
            $table->mediumText('page_url')->nullable();
            $table->string('campaign_origin')->nullable();

            $table->string('fbp')->nullable();
            $table->string('fbc')->nullable();




            $table->foreignId('customer_id')->nullable() ->constrained('customers')->onDelete('cascade');
            $table->foreignId('integration_id')->nullable() ->constrained('integrations')->onDelete('cascade');
            
            $table->mediumText('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
