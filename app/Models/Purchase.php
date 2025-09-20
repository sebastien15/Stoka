<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use HasFactory;

    protected $table = 'purchases';
    protected $primaryKey = 'purchase_id';

    protected $fillable = [
        'tenant_id',
        'purchase_number',
        'supplier_id',
        'warehouse_id',
        'shop_id',
        'total_amount',
        'status',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'payment_terms',
        'payment_status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id', 'purchase_id');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopePartiallyReceived($query)
    {
        return $query->where('status', 'partially_received');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
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

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('expected_delivery_date', '<', now())
                    ->whereIn('status', ['confirmed', 'partially_received']);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('order_date', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === 'partially_received';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
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

    public function canBeConfirmed(): bool
    {
        return in_array($this->status, ['draft', 'pending']);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'pending', 'confirmed']);
    }

    public function canReceiveItems(): bool
    {
        return in_array($this->status, ['confirmed', 'partially_received']);
    }

    public function isOverdue(): bool
    {
        return $this->expected_delivery_date && 
               $this->expected_delivery_date < now() && 
               in_array($this->status, ['confirmed', 'partially_received']);
    }

    public function getTotalItemCount(): int
    {
        return $this->items()->sum('quantity_ordered');
    }

    public function getTotalReceivedCount(): int
    {
        return $this->items()->sum('quantity_received');
    }

    public function getUniqueItemCount(): int
    {
        return $this->items()->count();
    }

    public function getReceivalPercentage(): float
    {
        $totalOrdered = $this->getTotalItemCount();
        
        if ($totalOrdered === 0) {
            return 0;
        }
        
        return round(($this->getTotalReceivedCount() / $totalOrdered) * 100, 2);
    }

    public function getDaysUntilDelivery(): int
    {
        if (!$this->expected_delivery_date) {
            return 0;
        }
        
        return now()->diffInDays($this->expected_delivery_date, false);
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        return now()->diffInDays($this->expected_delivery_date);
    }

    public function getLocationName(): string
    {
        if ($this->warehouse_id) {
            return $this->warehouse->name ?? 'Unknown Warehouse';
        }
        
        if ($this->shop_id) {
            return $this->shop->name ?? 'Unknown Shop';
        }
        
        return 'No Location';
    }

    public function confirm(): void
    {
        if ($this->canBeConfirmed()) {
            $this->status = 'confirmed';
            $this->save();
        }
    }

    public function cancel(string $reason = null): void
    {
        if ($this->canBeCancelled()) {
            $this->status = 'cancelled';
            
            if ($reason) {
                $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
                              "Cancelled: " . $reason;
            }
            
            $this->save();
        }
    }

    public function receiveItem(int $purchaseItemId, int $quantityReceived): bool
    {
        if (!$this->canReceiveItems()) {
            return false;
        }

        $item = $this->items()->find($purchaseItemId);
        
        if (!$item) {
            return false;
        }

        $maxReceivable = $item->quantity_ordered - $item->quantity_received;
        $actualReceived = min($quantityReceived, $maxReceivable);
        
        if ($actualReceived <= 0) {
            return false;
        }

        $item->quantity_received += $actualReceived;
        $item->save();

        // Record inventory movement
        InventoryMovement::recordPurchase(
            $this->tenant_id,
            $item->product_id,
            $item->variant_id,
            $actualReceived,
            $item->unit_cost,
            $this->purchase_id,
            $this->warehouse_id,
            $this->shop_id,
            $this->created_by
        );

        // Update product/variant stock
        if ($item->variant_id) {
            $item->variant->addStock($actualReceived, 'purchase');
        } else {
            $item->product->addStock($actualReceived, 'purchase');
        }

        // Update purchase status
        $this->updateStatus();
        
        return true;
    }

    public function receiveAllItems(): void
    {
        if (!$this->canReceiveItems()) {
            return;
        }

        foreach ($this->items as $item) {
            $remainingQuantity = $item->quantity_ordered - $item->quantity_received;
            
            if ($remainingQuantity > 0) {
                $this->receiveItem($item->purchase_item_id, $remainingQuantity);
            }
        }
    }

    private function updateStatus(): void
    {
        $totalOrdered = $this->getTotalItemCount();
        $totalReceived = $this->getTotalReceivedCount();

        if ($totalReceived === 0) {
            // No items received yet
            if ($this->status === 'partially_received') {
                $this->status = 'confirmed';
            }
        } elseif ($totalReceived >= $totalOrdered) {
            // All items received
            $this->status = 'completed';
            $this->actual_delivery_date = now()->toDateString();
        } else {
            // Some items received
            $this->status = 'partially_received';
        }

        $this->save();
    }

    public function markAsPaid(): void
    {
        $this->payment_status = 'paid';
        $this->save();
    }

    public function markAsPartiallyPaid(): void
    {
        $this->payment_status = 'partially_paid';
        $this->save();
    }

    public function getStatusBadgeClass(): string
    {
        $classes = [
            'draft' => 'bg-gray-100 text-gray-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'partially_received' => 'bg-orange-100 text-orange-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800'
        ];

        return $classes[$this->status] ?? $classes['draft'];
    }

    public function getPaymentStatusBadgeClass(): string
    {
        $classes = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'paid' => 'bg-green-100 text-green-800',
            'partially_paid' => 'bg-orange-100 text-orange-800'
        ];

        return $classes[$this->payment_status] ?? $classes['pending'];
    }

    public function generatePurchaseNumber(): string
    {
        $date = now()->format('Ymd');
        $count = static::where('tenant_id', $this->tenant_id)
            ->whereDate('created_at', now())
            ->count() + 1;
            
        return "PO-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // Calculate total based on items
    public function calculateTotal(): void
    {
        $this->total_amount = $this->items()->sum(\DB::raw('quantity_ordered * unit_cost'));
        $this->save();
    }

    public function addItem(int $productId, ?int $variantId, int $quantity, float $unitCost): PurchaseItem
    {
        $item = $this->items()->create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity_ordered' => $quantity,
            'quantity_received' => 0,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost
        ]);

        $this->calculateTotal();
        
        return $item;
    }

    public function removeItem(int $purchaseItemId): bool
    {
        $item = $this->items()->find($purchaseItemId);
        
        if (!$item || $item->quantity_received > 0) {
            return false; // Cannot remove item that has been partially/fully received
        }

        $item->delete();
        $this->calculateTotal();
        
        return true;
    }
}
