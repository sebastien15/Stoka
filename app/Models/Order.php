<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    protected $primaryKey = 'order_id';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'customer_id',
        'shop_id',
        'warehouse_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_amount',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'shipping_address',
        'shipping_city',
        'shipping_postal_code',
        'shipping_method',
        'tracking_number',
        'order_date',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'customer_notes',
        'internal_notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'order_date' => 'datetime',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id', 'user_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('payment_status', 'partially_paid');
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('order_date', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === 'partially_paid';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function canBeShipped(): bool
    {
        return in_array($this->status, ['confirmed', 'processing']);
    }

    public function canBeDelivered(): bool
    {
        return $this->status === 'shipped';
    }

    public function getNetAmount(): float
    {
        return $this->subtotal - $this->discount_amount;
    }

    public function getTotalItemCount(): int
    {
        return $this->items()->sum('quantity');
    }

    public function getUniqueItemCount(): int
    {
        return $this->items()->count();
    }

    public function getShippingAddress(): string
    {
        $parts = array_filter([
            $this->shipping_address,
            $this->shipping_city,
            $this->shipping_postal_code
        ]);

        return implode(', ', $parts);
    }

    public function confirm(): void
    {
        if ($this->isPending()) {
            $this->status = 'confirmed';
            $this->confirmed_at = now();
            $this->save();
        }
    }

    public function startProcessing(): void
    {
        if ($this->isConfirmed()) {
            $this->status = 'processing';
            $this->save();
        }
    }

    public function ship(string $trackingNumber = null): void
    {
        if ($this->canBeShipped()) {
            $this->status = 'shipped';
            $this->shipped_at = now();
            
            if ($trackingNumber) {
                $this->tracking_number = $trackingNumber;
            }
            
            $this->save();

            // Reduce stock for all items
            foreach ($this->items as $item) {
                if ($item->variant_id) {
                    $item->variant->reduceStock($item->quantity, 'sale');
                } else {
                    $item->product->reduceStock($item->quantity, 'sale');
                }
            }
        }
    }

    public function deliver(): void
    {
        if ($this->canBeDelivered()) {
            $this->status = 'delivered';
            $this->delivered_at = now();
            $this->save();

            // Update customer profile and product sales stats
            $customerProfile = $this->customer->customerProfile;
            if ($customerProfile) {
                $customerProfile->updateOrderStats($this->total_amount);
                
                // Add loyalty points
                $points = $customerProfile->calculateLoyaltyPoints($this->total_amount);
                $customerProfile->addLoyaltyPoints($points);
            }

            // Update product sales statistics
            foreach ($this->items as $item) {
                $item->product->recordSale($item->quantity, $item->total_price);
            }
        }
    }

    public function cancel(string $reason = null): void
    {
        if ($this->canBeCancelled()) {
            $this->status = 'cancelled';
            
            if ($reason) {
                $this->internal_notes = ($this->internal_notes ? $this->internal_notes . "\n" : '') . 
                                      "Cancelled: " . $reason;
            }
            
            $this->save();
        }
    }

    public function refund(): void
    {
        if ($this->isDelivered() || $this->isShipped()) {
            $this->status = 'refunded';
            $this->payment_status = 'refunded';
            $this->save();

            // Return stock for all items
            foreach ($this->items as $item) {
                if ($item->variant_id) {
                    $item->variant->addStock($item->quantity, 'return');
                } else {
                    $item->product->addStock($item->quantity, 'return');
                }
            }

            // Update customer stats (reverse the order)
            $customerProfile = $this->customer->customerProfile;
            if ($customerProfile) {
                $customerProfile->total_orders = max(0, $customerProfile->total_orders - 1);
                $customerProfile->total_spent = max(0, $customerProfile->total_spent - $this->total_amount);
                $customerProfile->save();
                $customerProfile->updateTier();
            }
        }
    }

    public function markAsPaid(string $paymentMethod = null): void
    {
        $this->payment_status = 'paid';
        
        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }
        
        $this->save();
    }

    public function getStatusBadgeClass(): string
    {
        $classes = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'processing' => 'bg-purple-100 text-purple-800',
            'shipped' => 'bg-orange-100 text-orange-800',
            'delivered' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-gray-100 text-gray-800'
        ];

        return $classes[$this->status] ?? $classes['pending'];
    }

    public function getPaymentStatusBadgeClass(): string
    {
        $classes = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'paid' => 'bg-green-100 text-green-800',
            'partially_paid' => 'bg-orange-100 text-orange-800',
            'refunded' => 'bg-gray-100 text-gray-800'
        ];

        return $classes[$this->payment_status] ?? $classes['pending'];
    }

    public function getDaysInCurrentStatus(): int
    {
        $statusDate = match($this->status) {
            'confirmed' => $this->confirmed_at,
            'shipped' => $this->shipped_at,
            'delivered' => $this->delivered_at,
            default => $this->order_date
        };

        return $statusDate ? now()->diffInDays($statusDate) : 0;
    }
}
