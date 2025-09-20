<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';
    protected $primaryKey = 'plan_id';

    protected $fillable = [
        'plan_name',
        'display_name',
        'description',
        'price_monthly',
        'price_yearly',
        'currency',
        'max_users',
        'max_products',
        'max_warehouses',
        'max_shops',
        'storage_limit_gb',
        'api_requests_limit',
        'features',
        'modules',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'max_warehouses' => 'integer',
        'max_shops' => 'integer',
        'storage_limit_gb' => 'integer',
        'api_requests_limit' => 'integer',
        'features' => 'array',
        'modules' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price_monthly');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getFeatures(): array
    {
        return $this->features ?? [];
    }

    public function getModules(): array
    {
        return $this->modules ?? [];
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures());
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->getModules());
    }

    public function getYearlyDiscount(): float
    {
        if (!$this->price_monthly || !$this->price_yearly) {
            return 0;
        }

        $yearlyFromMonthly = $this->price_monthly * 12;
        $discount = $yearlyFromMonthly - $this->price_yearly;
        
        return round(($discount / $yearlyFromMonthly) * 100, 2);
    }

    public function getMonthlyPrice(): float
    {
        return $this->price_monthly ?? 0;
    }

    public function getYearlyPrice(): float
    {
        return $this->price_yearly ?? 0;
    }

    public function getEffectiveMonthlyPrice(): float
    {
        return $this->price_yearly ? ($this->price_yearly / 12) : $this->price_monthly;
    }
}
