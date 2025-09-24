<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $table = 'inventory_movements';
    protected $primaryKey = 'movement_id';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_variant_id',
        'movement_type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'reference_id',
        'reference_type',
        'reason',
        'warehouse_id',
        'shop_id',
        'created_by'
    ];

    protected $casts = [
        'quantity_before' => 'integer',
        'quantity_change' => 'integer',
        'quantity_after' => 'integer',
        'created_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'variant_id');
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

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForVariant($query, $variantId)
    {
        return $query->where('product_variant_id', $variantId);
    }

    public function scopeForWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeInbound($query)
    {
        return $query->whereIn('movement_type', ['purchase', 'return', 'adjustment'])
                    ->where('quantity', '>', 0);
    }

    public function scopeOutbound($query)
    {
        return $query->whereIn('movement_type', ['sale', 'transfer', 'adjustment', 'damaged', 'expired'])
                    ->where('quantity', '<', 0);
    }

    public function scopePurchases($query)
    {
        return $query->where('movement_type', 'purchase');
    }

    public function scopeSales($query)
    {
        return $query->where('movement_type', 'sale');
    }

    public function scopeReturns($query)
    {
        return $query->where('movement_type', 'return');
    }

    public function scopeAdjustments($query)
    {
        return $query->where('movement_type', 'adjustment');
    }

    public function scopeTransfers($query)
    {
        return $query->where('movement_type', 'transfer');
    }

    public function scopeDamaged($query)
    {
        return $query->where('movement_type', 'damaged');
    }

    public function scopeExpired($query)
    {
        return $query->where('movement_type', 'expired');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isInbound(): bool
    {
        return $this->quantity_change > 0;
    }

    public function isOutbound(): bool
    {
        return $this->quantity_change < 0;
    }

    public function hasVariant(): bool
    {
        return $this->product_variant_id !== null;
    }

    public function getAbsoluteQuantity(): int
    {
        return abs($this->quantity_change);
    }

    public function getMovementDirection(): string
    {
        return $this->isInbound() ? 'in' : 'out';
    }

    public function getDisplayName(): string
    {
        if ($this->hasVariant()) {
            return $this->productVariant->getFullName();
        }
        
        return $this->product->name;
    }

    public function getSku(): string
    {
        if ($this->hasVariant()) {
            return $this->productVariant->sku;
        }
        
        return $this->product->sku;
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

    public function getMovementTypeLabel(): string
    {
        $labels = [
            'purchase' => 'Purchase',
            'sale' => 'Sale',
            'return' => 'Return',
            'adjustment' => 'Adjustment',
            'transfer' => 'Transfer',
            'damaged' => 'Damaged',
            'expired' => 'Expired'
        ];

        return $labels[$this->movement_type] ?? ucfirst($this->movement_type);
    }

    public function getMovementTypeIcon(): string
    {
        $icons = [
            'purchase' => 'plus-circle',
            'sale' => 'minus-circle',
            'return' => 'arrow-left-circle',
            'adjustment' => 'edit',
            'transfer' => 'arrow-right-circle',
            'damaged' => 'x-circle',
            'expired' => 'clock'
        ];

        return $icons[$this->movement_type] ?? 'circle';
    }

    public function getMovementTypeColor(): string
    {
        $colors = [
            'purchase' => 'green',
            'sale' => 'blue',
            'return' => 'orange',
            'adjustment' => 'purple',
            'transfer' => 'indigo',
            'damaged' => 'red',
            'expired' => 'gray'
        ];

        return $colors[$this->movement_type] ?? 'gray';
    }

    public function getReferenceObject()
    {
        if (!$this->reference_id || !$this->reference_type) {
            return null;
        }

        return match($this->reference_type) {
            'order' => Order::find($this->reference_id),
            'purchase' => Purchase::find($this->reference_id),
            default => null
        };
    }

    public function getReferenceNumber(): ?string
    {
        $reference = $this->getReferenceObject();
        
        if (!$reference) {
            return null;
        }

        return match($this->reference_type) {
            'order' => $reference->order_number,
            'purchase' => $reference->purchase_number,
            default => "#{$this->reference_id}"
        };
    }

    public function canBeReversed(): bool
    {
        // Can only reverse manual adjustments
        return $this->movement_type === 'adjustment' && 
               $this->reference_type === 'manual_adjustment';
    }

    public function reverse(string $reason = 'Reversed movement'): ?self
    {
        if (!$this->canBeReversed()) {
            return null;
        }

        return self::create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'movement_type' => 'adjustment',
            'quantity_before' => $this->quantity_after,
            'quantity_change' => -$this->quantity_change,
            'quantity_after' => $this->quantity_before,
            'reference_type' => 'reversal',
            'reference_id' => $this->movement_id,
            'reason' => $reason,
            'warehouse_id' => $this->warehouse_id,
            'shop_id' => $this->shop_id,
            'created_by' => auth()->id() ?? 1
        ]);
    }

    // Static helper methods
    public static function recordPurchase(
        int $tenantId,
        int $productId,
        ?int $productVariantId,
        int $quantityChange,
        int $quantityBefore,
        int $purchaseId,
        ?int $warehouseId = null,
        ?int $shopId = null,
        ?int $createdBy = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'movement_type' => 'purchase',
            'quantity_before' => $quantityBefore,
            'quantity_change' => $quantityChange,
            'quantity_after' => $quantityBefore + $quantityChange,
            'reference_id' => $purchaseId,
            'reference_type' => 'purchase',
            'reason' => 'Purchase received',
            'warehouse_id' => $warehouseId,
            'shop_id' => $shopId,
            'created_by' => $createdBy ?? auth()->id() ?? 1
        ]);
    }

    public static function recordSale(
        int $tenantId,
        int $productId,
        ?int $productVariantId,
        int $quantityChange,
        int $quantityBefore,
        int $orderId,
        ?int $warehouseId = null,
        ?int $shopId = null,
        ?int $createdBy = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'movement_type' => 'sale',
            'quantity_before' => $quantityBefore,
            'quantity_change' => -abs($quantityChange),
            'quantity_after' => $quantityBefore - abs($quantityChange),
            'reference_id' => $orderId,
            'reference_type' => 'order',
            'reason' => 'Sale order',
            'warehouse_id' => $warehouseId,
            'shop_id' => $shopId,
            'created_by' => $createdBy ?? auth()->id() ?? 1
        ]);
    }

    public static function recordAdjustment(
        int $tenantId,
        int $productId,
        ?int $productVariantId,
        int $quantityChange,
        string $reason,
        ?int $warehouseId = null,
        ?int $shopId = null,
        ?int $createdBy = null,
        ?int $quantityBefore = null
    ): self {
        $before = $quantityBefore ?? 0;
        return self::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'movement_type' => 'adjustment',
            'quantity_before' => $before,
            'quantity_change' => $quantityChange,
            'quantity_after' => $before + $quantityChange,
            'reference_type' => 'manual_adjustment',
            'reason' => $reason,
            'warehouse_id' => $warehouseId,
            'shop_id' => $shopId,
            'created_by' => $createdBy ?? auth()->id() ?? 1
        ]);
    }
}
