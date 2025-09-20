<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $primaryKey = 'product_id';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'short_description',
        'sku',
        'barcode',
        'qr_code',
        'category_id',
        'brand_id',
        'supplier_id',
        'shop_id',
        'warehouse_id',
        'cost_price',
        'selling_price',
        'discount_price',
        'tax_rate',
        'stock_quantity',
        'min_stock_level',
        'max_stock_level',
        'reorder_point',
        'weight',
        'dimensions_length',
        'dimensions_width',
        'dimensions_height',
        'color',
        'size',
        'status',
        'is_featured',
        'is_digital',
        'tags',
        'meta_title',
        'meta_description',
        'primary_image_url',
        'gallery_images',
        'total_sold',
        'total_revenue',
        'last_sold_at'
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
        'reorder_point' => 'integer',
        'weight' => 'decimal:2',
        'dimensions_length' => 'decimal:2',
        'dimensions_width' => 'decimal:2',
        'dimensions_height' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_digital' => 'boolean',
        'tags' => 'array',
        'gallery_images' => 'array',
        'total_sold' => 'integer',
        'total_revenue' => 'decimal:2',
        'last_sold_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'brand_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id', 'product_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'product_id', 'product_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id', 'product_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeDiscontinued($query)
    {
        return $query->where('status', 'discontinued');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('status', 'out_of_stock');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock_quantity <= min_stock_level');
    }

    public function scopeNeedReorder($query)
    {
        return $query->whereRaw('stock_quantity <= reorder_point');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeDigital($query)
    {
        return $query->where('is_digital', true);
    }

    public function scopePhysical($query)
    {
        return $query->where('is_digital', false);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeWithDiscount($query)
    {
        return $query->whereNotNull('discount_price');
    }

    public function scopeByPriceRange($query, $minPrice, $maxPrice)
    {
        return $query->whereBetween('selling_price', [$minPrice, $maxPrice]);
    }

    public function scopeByTag($query, $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }

    public function needsReorder(): bool
    {
        return $this->stock_quantity <= $this->reorder_point;
    }

    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    public function isDigital(): bool
    {
        return $this->is_digital;
    }

    public function hasDiscount(): bool
    {
        return $this->discount_price !== null && $this->discount_price < $this->selling_price;
    }

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    public function hasPhysicalDimensions(): bool
    {
        return $this->dimensions_length && $this->dimensions_width && $this->dimensions_height;
    }

    public function getActualPrice(): float
    {
        return $this->hasDiscount() ? $this->discount_price : $this->selling_price;
    }

    public function getDiscountPercentage(): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return round((($this->selling_price - $this->discount_price) / $this->selling_price) * 100, 2);
    }

    public function getDiscountAmount(): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return $this->selling_price - $this->discount_price;
    }

    public function getVolume(): float
    {
        if (!$this->hasPhysicalDimensions()) {
            return 0;
        }

        // Convert cm³ to m³
        return ($this->dimensions_length * $this->dimensions_width * $this->dimensions_height) / 1000000;
    }

    public function getWeight(): float
    {
        return $this->weight ?? 0;
    }

    public function getTags(): array
    {
        return $this->tags ?? [];
    }

    public function getGalleryImages(): array
    {
        return $this->gallery_images ?? [];
    }

    public function addTag(string $tag): void
    {
        $tags = $this->getTags();
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
            $this->save();
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->getTags();
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        $this->tags = array_values($tags);
        $this->save();
    }

    public function addGalleryImage(string $imageUrl): void
    {
        $images = $this->getGalleryImages();
        if (!in_array($imageUrl, $images)) {
            $images[] = $imageUrl;
            $this->gallery_images = $images;
            $this->save();
        }
    }

    public function removeGalleryImage(string $imageUrl): void
    {
        $images = $this->getGalleryImages();
        $images = array_filter($images, fn($img) => $img !== $imageUrl);
        $this->gallery_images = array_values($images);
        $this->save();
    }

    public function getProfit(): float
    {
        return $this->getActualPrice() - $this->cost_price;
    }

    public function getProfitMargin(): float
    {
        if ($this->cost_price <= 0) {
            return 0;
        }

        return round(($this->getProfit() / $this->cost_price) * 100, 2);
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
            'movement_type' => 'adjustment',
            'quantity' => $quantity - $oldQuantity,
            'reference_type' => $reason,
            'warehouse_id' => $this->warehouse_id,
            'shop_id' => $this->shop_id,
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

    public function recordSale(int $quantity, float $amount): void
    {
        $this->total_sold += $quantity;
        $this->total_revenue += $amount;
        $this->last_sold_at = now();
        $this->save();
    }

    public function activate(): void
    {
        $this->status = 'active';
        $this->save();
    }

    public function deactivate(): void
    {
        $this->status = 'inactive';
        $this->save();
    }

    public function discontinue(): void
    {
        $this->status = 'discontinued';
        $this->save();
    }

    public function markAsOutOfStock(): void
    {
        $this->status = 'out_of_stock';
        $this->save();
    }

    public function setFeatured(bool $featured = true): void
    {
        $this->is_featured = $featured;
        $this->save();
    }

    public function getStockStatus(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        } elseif ($this->isLowStock()) {
            return 'low_stock';
        } elseif ($this->needsReorder()) {
            return 'needs_reorder';
        } else {
            return 'in_stock';
        }
    }

    public function getAverageRating(): float
    {
        // This would need to be implemented when you add product reviews
        // For now, return 0
        return 0;
    }

    public function getTotalReviews(): int
    {
        // This would need to be implemented when you add product reviews
        // For now, return 0
        return 0;
    }
}
