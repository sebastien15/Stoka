<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $table = 'brands';
    protected $primaryKey = 'brand_id';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'logo_url',
        'website_url',
        'contact_email',
        'contact_phone',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id', 'brand_id');
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

    public function scopeWithProducts($query)
    {
        return $query->has('products');
    }

    public function scopeWithoutProducts($query)
    {
        return $query->doesntHave('products');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    public function getProductCount(): int
    {
        return $this->products()->count();
    }

    public function getActiveProductCount(): int
    {
        return $this->products()->where('status', 'active')->count();
    }

    public function getTotalStockValue(): float
    {
        return $this->products()
            ->sum(\DB::raw('stock_quantity * cost_price'));
    }

    public function getTotalRevenue(): float
    {
        return $this->products()->sum('total_revenue');
    }

    public function getTopSellingProducts(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->products()
            ->orderBy('total_sold', 'desc')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
    }

    public function canBeDeleted(): bool
    {
        return !$this->hasProducts();
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
}
