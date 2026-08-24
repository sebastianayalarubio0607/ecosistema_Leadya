<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_events')) {
            Schema::create('whatsapp_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_name')->unique();
                $table->string('description');
                $table->string('funnel_usefulness', 30)->default('useful');
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('active');
                $table->index('sort_order');
            });
        }

        $now = now();
        foreach ($this->events() as $event) {
            DB::table('whatsapp_events')->updateOrInsert(
                ['event_name' => $event['event_name']],
                array_merge($event, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        if (! Schema::hasColumn('crm_state', 'whatsapp_event_id')) {
            Schema::table('crm_state', function (Blueprint $table) {
                $column = $table->foreignId('whatsapp_event_id')->nullable();

                if (Schema::hasColumn('crm_state', 'meta_event_id')) {
                    $column->after('meta_event_id');
                } elseif (Schema::hasColumn('crm_state', 'qualification')) {
                    $column->after('qualification');
                }

                $column->constrained('whatsapp_events')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_state', 'whatsapp_event_id')) {
            Schema::table('crm_state', function (Blueprint $table) {
                $table->dropConstrainedForeignId('whatsapp_event_id');
            });
        }

        Schema::dropIfExists('whatsapp_events');
    }

    private function events(): array
    {
        return [
            ['event_name' => 'LeadSubmitted', 'description' => 'Lead generado / datos obtenidos', 'funnel_usefulness' => 'useful', 'active' => true, 'sort_order' => 10],
            ['event_name' => 'QualifiedLead', 'description' => 'Lead calificado', 'funnel_usefulness' => 'useful', 'active' => true, 'sort_order' => 20],
            ['event_name' => 'Purchase', 'description' => 'Venta completada', 'funnel_usefulness' => 'useful', 'active' => true, 'sort_order' => 30],
            ['event_name' => 'ViewContent', 'description' => 'Visualizacion de producto/servicio', 'funnel_usefulness' => 'conditional', 'active' => true, 'sort_order' => 40],
            ['event_name' => 'AddToCart', 'description' => 'Producto agregado al carrito', 'funnel_usefulness' => 'conditional', 'active' => true, 'sort_order' => 50],
            ['event_name' => 'InitiateCheckout', 'description' => 'Inicio del proceso de compra', 'funnel_usefulness' => 'conditional', 'active' => true, 'sort_order' => 60],
            ['event_name' => 'OrderCreated', 'description' => 'Pedido creado', 'funnel_usefulness' => 'conditional', 'active' => true, 'sort_order' => 70],
            ['event_name' => 'OrderShipped', 'description' => 'Pedido enviado', 'funnel_usefulness' => 'not_recommended', 'active' => true, 'sort_order' => 80],
            ['event_name' => 'OrderDelivered', 'description' => 'Pedido entregado', 'funnel_usefulness' => 'not_recommended', 'active' => true, 'sort_order' => 90],
            ['event_name' => 'OrderCanceled', 'description' => 'Pedido cancelado', 'funnel_usefulness' => 'not_recommended', 'active' => true, 'sort_order' => 100],
            ['event_name' => 'OrderReturned', 'description' => 'Pedido devuelto', 'funnel_usefulness' => 'not_recommended', 'active' => true, 'sort_order' => 110],
            ['event_name' => 'CartAbandoned', 'description' => 'Carrito abandonado', 'funnel_usefulness' => 'not_recommended', 'active' => true, 'sort_order' => 120],
            ['event_name' => 'RatingProvided', 'description' => 'Calificacion del servicio', 'funnel_usefulness' => 'not_recommended', 'active' => true, 'sort_order' => 130],
            ['event_name' => 'ReviewProvided', 'description' => 'Resena', 'funnel_usefulness' => 'not_recommended', 'active' => true, 'sort_order' => 140],
        ];
    }
};
