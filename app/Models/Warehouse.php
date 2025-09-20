<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';
    protected $primaryKey = 'warehouse_id';

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
        'capacity',
        'current_utilization',
        'warehouse_type',
        'is_active',
        'operating_hours',
        'temperature_controlled',
        'security_level'
    ];

    protected $casts = [
        'capacity' => 'decimal:2',
        'current_utilization' => 'decimal:2',
        'is_active' => 'boolean',
        'temperature_controlled' => 'boolean',
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'warehouse_id', 'warehouse_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'warehouse_id', 'warehouse_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'warehouse_id', 'warehouse_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'warehouse_id', 'warehouse_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'warehouse_id', 'warehouse_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'warehouse_id', 'warehouse_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'warehouse_id', 'warehouse_id');
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
        return $query->where('warehouse_type', $type);
    }

    public function scopeTemperatureControlled($query)
    {
        return $query->where('temperature_controlled', true);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isTemperatureControlled(): bool
    {
        return $this->temperature_controlled;
    }

    public function getUtilizationPercentage(): float
    {
        return $this->current_utilization ?? 0.0;
    }

    public function getAvailableCapacity(): float
    {
        if (!$this->capacity) {
            return 0;
        }
        
        $used = ($this->capacity * $this->current_utilization) / 100;
        return $this->capacity - $used;
    }

    public function updateUtilization(): void
    {
        if (!$this->capacity) {
            $this->current_utilization = 0;
            return;
        }

        // Calculate utilization based on products stored
        $totalProductVolume = $this->products()
            ->where('stock_quantity', '>', 0)
            ->sum(\DB::raw('stock_quantity * (dimensions_length * dimensions_width * dimensions_height) / 1000000')); // Convert cm³ to m³

        $this->current_utilization = min(100, ($totalProductVolume / $this->capacity) * 100);
        $this->save();
    }

    public function canStoreProduct(Product $product, int $quantity = 1): bool
    {
        if (!$this->capacity || !$product->hasPhysicalDimensions()) {
            return true; // No capacity limit or product has no dimensions
        }

        $productVolume = $product->getVolume() * $quantity;
        $availableSpace = $this->getAvailableCapacity();
        
        return $productVolume <= $availableSpace;
    }

    public function getProductCount(): int
    {
        return $this->products()->count();
    }

    public function getTotalStockValue(): float
    {
        return $this->products()
            ->sum(\DB::raw('stock_quantity * cost_price'));
    }

    public function getProductsLowStock(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->products()
            ->whereRaw('stock_quantity <= min_stock_level')
            ->where('status', 'active')
            ->get();
    }
}
