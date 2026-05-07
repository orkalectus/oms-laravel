<?php

namespace App\Providers;

use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\PaymentFailed;
use App\Events\PaymentSuccess;
use App\Listeners\ProcessOrderAfterPayment;
use App\Listeners\SendOrderConfirmationEmail;
use App\Listeners\SendPaymentFailedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCreated::class => [
            SendOrderConfirmationEmail::class,
        ],
        PaymentSuccess::class => [
            ProcessOrderAfterPayment::class,
        ],
        PaymentFailed::class => [
            SendPaymentFailedNotification::class,
        ],
        OrderCancelled::class => [
            \App\Listeners\SendCancellationEmail::class,
        ],
        OrderStatusChanged::class => [
            \App\Listeners\LogOrderStatusChange::class,
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
