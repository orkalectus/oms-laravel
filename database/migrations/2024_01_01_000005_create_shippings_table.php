<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shippings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number', 100)->nullable()->unique();
            $table->string('courier', 30)->comment('jne, jnt, sicepat, pos, tiki');
            $table->string('service', 30)->comment('REG, YES, OKE, etc.');
            $table->string('service_description', 100)->nullable();
            $table->string('status', 30)->default('pending')->index();

            // Origin
            $table->unsignedInteger('origin_city_id')->nullable();
            $table->string('origin_city', 100)->nullable();

            // Destination
            $table->unsignedInteger('destination_city_id')->nullable();
            $table->string('destination_city', 100)->nullable();

            // Recipient info
            $table->string('recipient_name', 255)->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->text('recipient_address')->nullable();
            $table->string('recipient_city', 100)->nullable();
            $table->string('recipient_province', 100)->nullable();
            $table->string('recipient_postal_code', 10)->nullable();

            // Shipping details
            $table->unsignedInteger('weight_grams')->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('estimated_days', 20)->nullable()->comment('e.g. 2-3 HARI');
            $table->date('estimated_delivery_date')->nullable();

            // Timestamps
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Tracking & API data
            $table->json('tracking_history')->nullable();
            $table->json('rajaongkir_response')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index('tracking_number');
            // $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shippings');
    }
};
