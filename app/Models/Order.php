<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'idempotency_key',
        'user_id',
        'status',
        'subtotal',
        'shipping_cost',
        'total_amount',
        'currency',
        'notes',
        'metadata',
        'cancelled_at',
        'cancelled_reason',
        'completed_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(date('Ymd')) . '-' . strtoupper(Str::random(6));
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function shipping(): HasOne
    {
        return $this->hasOne(Shipping::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    // Status Management
    public function transitionTo(OrderStatus $newStatus, ?string $notes = null, array $metadata = []): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidStatusTransitionException(
                "Cannot transition order from [{$this->status->value}] to [{$newStatus->value}]"
            );
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;

        if ($newStatus === OrderStatus::CANCELLED) {
            $this->cancelled_at = now();
            $this->cancelled_reason = $notes;
        }

        if ($newStatus === OrderStatus::COMPLETED) {
            $this->completed_at = now();
        }

        $this->save();

        // Record history
        $this->statusHistories()->create([
            'from_status' => $oldStatus->value,
            'to_status' => $newStatus->value,
            'notes' => $notes,
            'metadata' => $metadata,
            'changed_by' => auth()->id(),
        ]);
    }

    // Scopes
    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            OrderStatus::CREATED->value,
            OrderStatus::PENDING_PAYMENT->value,
            OrderStatus::PAID->value,
            OrderStatus::PROCESSING->value,
        ]);
    }

    // Accessors
    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === OrderStatus::COMPLETED;
    }

    public function getIsCancellableAttribute(): bool
    {
        return in_array($this->status, [
            OrderStatus::CREATED,
            OrderStatus::PENDING_PAYMENT,
        ]);
    }
}
