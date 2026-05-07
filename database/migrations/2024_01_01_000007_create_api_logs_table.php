<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 50)->index()->comment('fakestore, dummyjson, rajaongkir, payment_gateway');
            $table->string('method', 10);
            $table->text('url');
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable()->comment('Response time in milliseconds');
            $table->boolean('is_success')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable()->comment('Additional context like order_id, action');
            $table->timestamps();

            $table->index(['service', 'is_success']);
            $table->index(['service', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
