<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $table = 'product_variants';
    protected $primaryKey = 'variant_id';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'variant_name',
        'sku',
        'barcode',
        'price',
        'stock_quantity',
        'weight',
        'color',
        'size',
        'image_url',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
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

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id', 'variant_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'variant_id', 'variant_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'variant_id', 'variant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    public function scopeByColor($query, $color)
    {
        return $query->where('color', $color);
    }

    public function scopeBySize($query, $size)
    {
        return $query->where('size', $size);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function getFullName(): string
    {
        return $this->product->name . ' - ' . $this->variant_name;
    }

    public function getDisplayName(): string
    {
        $attributes = [];
        
        if ($this->color) {
            $attributes[] = $this->color;
        }
        
        if ($this->size) {
            $attributes[] = $this->size;
        }
        
        if (empty($attributes)) {
            return $this->variant_name;
        }
        
        return $this->variant_name . ' (' . implode(', ', $attributes) . ')';
    }

    public function updateStock(int $quantity, string $reason = 'manual_adjustment'): void
    {
        $oldQuantity = $this->stock_quantity;
        $this->stock_quantity = max(0, $quantity);
        $this->save();

        // Log inventory movement
        InventoryMovement::create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'movement_type' => 'adjustment',
            'quantity' => $quantity - $oldQuantity,
            'reference_type' => $reason,
            'warehouse_id' => $this->product->warehouse_id,
            'shop_id' => $this->product->shop_id,
            'created_by' => auth()->id() ?? 1
        ]);
    }

    public function addStock(int $quantity, string $reason = 'purchase'): void
    {
        $this->updateStock($this->stock_quantity + $quantity, $reason);
    }

    public function reduceStock(int $quantity, string $reason = 'sale'): void
    {
        $this->updateStock($this->stock_quantity - $quantity, $reason);
    }

    public function getTotalSold(): int
    {
        return $this->orderItems()
            ->whereHas('order', function ($query) {
                $query->where('status', 'delivered');
            })
            ->sum('quantity');
    }

    public function getTotalRevenue(): float
    {
        return $this->orderItems()
            ->whereHas('order', function ($query) {
                $query->where('status', 'delivered');
            })
            ->sum('total_price');
    }

    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    public function getWeight(): float
    {
        return $this->weight ?? $this->product->weight ?? 0;
    }

    public function getBasePrice(): float
    {
        return $this->price ?? $this->product->selling_price ?? 0;
    }

    public function hasDiscount(): bool
    {
        return $this->product->hasDiscount();
    }

    public function getDiscountedPrice(): float
    {
        if (!$this->hasDiscount()) {
            return $this->getBasePrice();
        }

        // Apply same discount percentage as main product
        $discountPercentage = $this->product->getDiscountPercentage();
        return $this->getBasePrice() * (1 - $discountPercentage / 100);
    }

    public function getActualPrice(): float
    {
        return $this->hasDiscount() ? $this->getDiscountedPrice() : $this->getBasePrice();
    }
}
