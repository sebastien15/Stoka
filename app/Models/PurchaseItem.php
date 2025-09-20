<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_items';
    protected $primaryKey = 'purchase_item_id';

    protected $fillable = [
        'tenant_id',
        'purchase_id',
        'product_id',
        'variant_id',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'total_cost'
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'created_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id', 'purchase_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'variant_id');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPurchase($query, $purchaseId)
    {
        return $query->where('purchase_id', $purchaseId);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeWithVariant($query)
    {
        return $query->whereNotNull('variant_id');
    }

    public function scopeWithoutVariant($query)
    {
        return $query->whereNull('variant_id');
    }

    public function scopeFullyReceived($query)
    {
        return $query->whereRaw('quantity_received >= quantity_ordered');
    }

    public function scopePartiallyReceived($query)
    {
        return $query->where('quantity_received', '>', 0)
                    ->whereRaw('quantity_received < quantity_ordered');
    }

    public function scopeNotReceived($query)
    {
        return $query->where('quantity_received', 0);
    }

    public function scopePending($query)
    {
        return $query->whereRaw('quantity_received < quantity_ordered');
    }

    // Helper methods
    public function hasVariant(): bool
    {
        return $this->variant_id !== null;
    }

    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->quantity_received > 0 && $this->quantity_received < $this->quantity_ordered;
    }

    public function isNotReceived(): bool
    {
        return $this->quantity_received === 0;
    }

    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_received);
    }

    public function getReceivalPercentage(): float
    {
        if ($this->quantity_ordered === 0) {
            return 0;
        }
        
        return round(($this->quantity_received / $this->quantity_ordered) * 100, 2);
    }

    public function getDisplayName(): string
    {
        if ($this->hasVariant()) {
            return $this->variant->getFullName();
        }
        
        return $this->product->name;
    }

    public function getProductName(): string
    {
        return $this->product->name;
    }

    public function getVariantName(): ?string
    {
        return $this->variant?->getDisplayName();
    }

    public function getSku(): string
    {
        if ($this->hasVariant()) {
            return $this->variant->sku;
        }
        
        return $this->product->sku;
    }

    public function getTotalOrderedCost(): float
    {
        return $this->quantity_ordered * $this->unit_cost;
    }

    public function getTotalReceivedCost(): float
    {
        return $this->quantity_received * $this->unit_cost;
    }

    public function getRemainingCost(): float
    {
        return $this->getRemainingQuantity() * $this->unit_cost;
    }

    public function getStatusLabel(): string
    {
        if ($this->isFullyReceived()) {
            return 'Fully Received';
        } elseif ($this->isPartiallyReceived()) {
            return 'Partially Received';
        } else {
            return 'Not Received';
        }
    }

    public function getStatusColor(): string
    {
        if ($this->isFullyReceived()) {
            return 'green';
        } elseif ($this->isPartiallyReceived()) {
            return 'orange';
        } else {
            return 'gray';
        }
    }

    public function canReceive(int $quantity = null): bool
    {
        if ($this->isFullyReceived()) {
            return false;
        }

        if ($quantity === null) {
            return true;
        }

        return $quantity <= $this->getRemainingQuantity();
    }

    public function receive(int $quantity): bool
    {
        if (!$this->canReceive($quantity)) {
            return false;
        }

        $this->quantity_received += $quantity;
        $this->save();

        return true;
    }

    public function receiveAll(): bool
    {
        return $this->receive($this->getRemainingQuantity());
    }

    public function updateQuantityOrdered(int $newQuantity): void
    {
        // Cannot reduce quantity below already received amount
        $this->quantity_ordered = max($newQuantity, $this->quantity_received);
        $this->total_cost = $this->quantity_ordered * $this->unit_cost;
        $this->save();
    }

    public function updateUnitCost(float $newCost): void
    {
        $this->unit_cost = $newCost;
        $this->total_cost = $this->quantity_ordered * $this->unit_cost;
        $this->save();
    }

    public function getCurrentStock(): int
    {
        if ($this->hasVariant()) {
            return $this->variant->stock_quantity;
        }
        
        return $this->product->stock_quantity;
    }

    public function getProductImage(): ?string
    {
        if ($this->hasVariant() && $this->variant->image_url) {
            return $this->variant->image_url;
        }
        
        return $this->product->primary_image_url;
    }

    // Static methods for calculations
    public static function calculateTotalOrdered($purchaseItems): float
    {
        return $purchaseItems->sum('total_cost');
    }

    public static function calculateTotalReceived($purchaseItems): float
    {
        return $purchaseItems->sum(function ($item) {
            return $item->getTotalReceivedCost();
        });
    }

    public static function calculateTotalPending($purchaseItems): float
    {
        return $purchaseItems->sum(function ($item) {
            return $item->getRemainingCost();
        });
    }
}
