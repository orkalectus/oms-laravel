<?php

namespace App\Models;

use App\Enums\ShippingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Shipping extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'courier',
        'service',
        'service_description',
        'status',
        'origin_city_id',
        'origin_city',
        'destination_city_id',
        'destination_city',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_city',
        'recipient_province',
        'recipient_postal_code',
        'weight_grams',
        'cost',
        'estimated_days',
        'estimated_delivery_date',
        'shipped_at',
        'delivered_at',
        'tracking_history',
        'rajaongkir_response',
        'notes',
    ];

    protected $casts = [
        'status' => ShippingStatus::class,
        'cost' => 'decimal:2',
        'weight_grams' => 'integer',
        'estimated_days' => 'integer',
        'estimated_delivery_date' => 'date',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'tracking_history' => 'array',
        'rajaongkir_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function addTrackingEvent(string $status, string $description, ?string $location = null): void
    {
        $history = $this->tracking_history ?? [];
        $history[] = [
            'status' => $status,
            'description' => $description,
            'location' => $location,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->tracking_history = $history;
        $this->save();
    }

    public function generateTrackingNumber(): string
    {
        return strtoupper($this->courier) . '-' . strtoupper(Str::random(10));
    }
}
