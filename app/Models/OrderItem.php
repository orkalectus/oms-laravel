<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_snapshot',
        'product_name',
        'product_sku',
        'unit_price',
        'quantity',
        'subtotal',
        'weight',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'weight' => 'decimal:3',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (OrderItem $item) {
            $item->subtotal = $item->unit_price * $item->quantity;
        });
    }
}
