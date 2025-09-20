<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';
    protected $primaryKey = 'tenant_id';

    protected $fillable = [
        'tenant_code',
        'company_name',
        'business_type',
        'subscription_plan',
        'contact_person',
        'email',
        'phone_number',
        'website_url',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'timezone',
        'tax_number',
        'registration_number',
        'industry',
        'company_size',
        'subscription_start_date',
        'subscription_end_date',
        'billing_cycle',
        'subscription_amount',
        'currency',
        'max_users',
        'max_products',
        'max_warehouses',
        'max_shops',
        'storage_limit_gb',
        'api_requests_limit',
        'status',
        'is_trial',
        'trial_days_remaining',
        'logo_url',
        'primary_color',
        'secondary_color',
        'custom_domain',
        'database_schema',
        'cdn_url',
        'backup_enabled',
        'ssl_enabled',
        'onboarding_completed',
        'last_login_at'
    ];

    protected $casts = [
        'subscription_start_date' => 'date',
        'subscription_end_date' => 'date',
        'subscription_amount' => 'decimal:2',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'max_warehouses' => 'integer',
        'max_shops' => 'integer',
        'storage_limit_gb' => 'integer',
        'api_requests_limit' => 'integer',
        'trial_days_remaining' => 'integer',
        'is_trial' => 'boolean',
        'backup_enabled' => 'boolean',
        'ssl_enabled' => 'boolean',
        'onboarding_completed' => 'boolean',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id', 'tenant_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'tenant_id', 'tenant_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'tenant_id', 'tenant_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'tenant_id', 'tenant_id');
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'tenant_id', 'tenant_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'tenant_id', 'tenant_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tenant_id', 'tenant_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'tenant_id', 'tenant_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'tenant_id', 'tenant_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'tenant_id', 'tenant_id');
    }

    public function tenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class, 'tenant_id', 'tenant_id');
    }

    public function systemConfigurations(): HasMany
    {
        return $this->hasMany(SystemConfiguration::class, 'tenant_id', 'tenant_id');
    }

    public function billingHistory(): HasMany
    {
        return $this->hasMany(TenantBillingHistory::class, 'tenant_id', 'tenant_id');
    }

    public function noticesEvents(): HasMany
    {
        return $this->hasMany(NoticeEvent::class, 'tenant_id', 'tenant_id');
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'tenant_id', 'tenant_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'tenant_id', 'tenant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrial($query)
    {
        return $query->where('is_trial', true);
    }

    public function scopeSubscribed($query)
    {
        return $query->where('is_trial', false)->where('status', 'active');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrial(): bool
    {
        return $this->is_trial;
    }

    public function isSubscribed(): bool
    {
        return !$this->is_trial && $this->status === 'active';
    }

    public function getRemainingTrialDays(): int
    {
        if (!$this->is_trial) {
            return 0;
        }
        return max(0, $this->trial_days_remaining);
    }

    public function canAddUsers(): bool
    {
        return $this->users()->count() < $this->max_users;
    }

    public function canAddProducts(): bool
    {
        return $this->products()->count() < $this->max_products;
    }

    public function canAddWarehouses(): bool
    {
        return $this->warehouses()->count() < $this->max_warehouses;
    }

    public function canAddShops(): bool
    {
        return $this->shops()->count() < $this->max_shops;
    }
}
