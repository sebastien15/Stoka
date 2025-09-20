<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasFactory;

    protected $table = 'shops';
    protected $primaryKey = 'shop_id';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'phone_number',
        'email',
        'manager_id',
        'warehouse_id',
        'shop_type',
        'floor_area',
        'rent_amount',
        'is_active',
        'opening_hours',
        'website_url',
        'social_media_handles',
        'pos_system',
        'online_shop_enabled',
        'delivery_enabled'
    ];

    protected $casts = [
        'floor_area' => 'decimal:2',
        'rent_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'social_media_handles' => 'array',
        'online_shop_enabled' => 'boolean',
        'delivery_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id', 'user_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'shop_id', 'shop_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'shop_id', 'shop_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'shop_id', 'shop_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'shop_id', 'shop_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'shop_id', 'shop_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'shop_id', 'shop_id');
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

    public function scopeByType($query, $type)
    {
        return $query->where('shop_type', $type);
    }

    public function scopeOnlineEnabled($query)
    {
        return $query->where('online_shop_enabled', true);
    }

    public function scopeDeliveryEnabled($query)
    {
        return $query->where('delivery_enabled', true);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isOnlineEnabled(): bool
    {
        return $this->online_shop_enabled;
    }

    public function isDeliveryEnabled(): bool
    {
        return $this->delivery_enabled;
    }

    public function getSocialMediaHandles(): array
    {
        return $this->social_media_handles ?? [];
    }

    public function addSocialMediaHandle(string $platform, string $handle): void
    {
        $handles = $this->getSocialMediaHandles();
        $handles[$platform] = $handle;
        $this->social_media_handles = $handles;
        $this->save();
    }

    public function removeSocialMediaHandle(string $platform): void
    {
        $handles = $this->getSocialMediaHandles();
        unset($handles[$platform]);
        $this->social_media_handles = $handles;
        $this->save();
    }

    public function getProductCount(): int
    {
        return $this->products()->count();
    }

    public function getActiveProductCount(): int
    {
        return $this->products()->where('status', 'active')->count();
    }

    public function getTotalInventoryValue(): float
    {
        return $this->products()
            ->sum(\DB::raw('stock_quantity * cost_price'));
    }

    public function getTotalSalesValue(): float
    {
        return $this->orders()
            ->where('status', 'delivered')
            ->sum('total_amount');
    }

    public function getMonthlyRevenue(\DateTime $month = null): float
    {
        $month = $month ?: now();
        
        return $this->orders()
            ->where('status', 'delivered')
            ->whereYear('delivered_at', $month->format('Y'))
            ->whereMonth('delivered_at', $month->format('m'))
            ->sum('total_amount');
    }

    public function getTopSellingProducts(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->products()
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRecentOrders(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $this->orders()
            ->latest('order_date')
            ->limit($limit)
            ->get();
    }

    public function getPendingOrders(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->orders()
            ->whereIn('status', ['pending', 'confirmed', 'processing'])
            ->orderBy('order_date')
            ->get();
    }
}
