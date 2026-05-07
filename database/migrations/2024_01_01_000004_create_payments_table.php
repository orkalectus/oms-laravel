<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('payment_code', 30)->unique()->comment('Internal payment reference code');
            $table->string('gateway_transaction_id', 100)->nullable()->unique()
                ->comment('Transaction ID from payment gateway');
            $table->string('gateway_reference', 255)->nullable()
                ->comment('VA number, QR code URL, or other gateway reference');
            $table->string('payment_method', 50)->comment('bank_transfer, credit_card, e_wallet, qris');
            $table->string('payment_channel', 50)->nullable()->comment('bca_va, gopay, visa, etc.');
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->json('gateway_response')->nullable()->comment('Full response from payment gateway');
            $table->json('webhook_payload')->nullable()->comment('Raw webhook payload received');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('gateway_transaction_id');
            // $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
