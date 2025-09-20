<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';
    protected $primaryKey = 'order_item_id';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price',
        'total_price',
        'discount_amount',
        'tax_amount'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'created_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
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

    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
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

    // Helper methods
    public function hasVariant(): bool
    {
        return $this->variant_id !== null;
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

    public function getNetPrice(): float
    {
        return $this->unit_price - $this->discount_amount;
    }

    public function getNetTotal(): float
    {
        return $this->total_price - ($this->discount_amount * $this->quantity);
    }

    public function getTotalWithTax(): float
    {
        return $this->total_price + $this->tax_amount;
    }

    public function getDiscountPercentage(): float
    {
        if ($this->unit_price <= 0 || $this->discount_amount <= 0) {
            return 0;
        }

        return round(($this->discount_amount / $this->unit_price) * 100, 2);
    }

    public function getTaxPercentage(): float
    {
        if ($this->getNetTotal() <= 0 || $this->tax_amount <= 0) {
            return 0;
        }

        return round(($this->tax_amount / $this->getNetTotal()) * 100, 2);
    }

    public function getWeight(): float
    {
        if ($this->hasVariant()) {
            return $this->variant->getWeight() * $this->quantity;
        }
        
        return $this->product->getWeight() * $this->quantity;
    }

    public function canBeReturned(): bool
    {
        return $this->order->isDelivered() && !$this->order->isRefunded();
    }

    public function isDigital(): bool
    {
        return $this->product->isDigital();
    }

    public function getProductImage(): ?string
    {
        if ($this->hasVariant() && $this->variant->image_url) {
            return $this->variant->image_url;
        }
        
        return $this->product->primary_image_url;
    }

    public function getProductUrl(): ?string
    {
        // This would generate a URL to the product page
        // Implementation depends on your routing structure
        return null;
    }

    public function calculateTax(float $taxRate): void
    {
        $this->tax_amount = $this->getNetTotal() * ($taxRate / 100);
        $this->save();
    }

    public function applyDiscount(float $discountAmount): void
    {
        $this->discount_amount = min($discountAmount, $this->unit_price);
        $this->total_price = ($this->unit_price - $this->discount_amount) * $this->quantity;
        $this->save();
    }

    public function applyDiscountPercentage(float $discountPercentage): void
    {
        $discountAmount = $this->unit_price * ($discountPercentage / 100);
        $this->applyDiscount($discountAmount);
    }

    public function updateQuantity(int $newQuantity): void
    {
        $this->quantity = $newQuantity;
        $this->total_price = ($this->unit_price - $this->discount_amount) * $this->quantity;
        $this->save();
    }

    public function updateUnitPrice(float $newPrice): void
    {
        $this->unit_price = $newPrice;
        $this->total_price = ($this->unit_price - $this->discount_amount) * $this->quantity;
        $this->save();
    }

    // Static methods for calculations
    public static function calculateSubtotal($orderItems): float
    {
        return $orderItems->sum('total_price');
    }

    public static function calculateTotalDiscount($orderItems): float
    {
        return $orderItems->sum(function ($item) {
            return $item->discount_amount * $item->quantity;
        });
    }

    public static function calculateTotalTax($orderItems): float
    {
        return $orderItems->sum('tax_amount');
    }

    public static function calculateTotalWeight($orderItems): float
    {
        return $orderItems->sum(function ($item) {
            return $item->getWeight();
        });
    }
}
