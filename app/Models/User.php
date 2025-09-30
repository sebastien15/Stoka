<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'full_name',
        'email',
        'password',
        'pin',
        'phone_number',
        'address',
        'role',
        'warehouse_id',
        'shop_id',
        'is_active',
        'last_login',
        'profile_image_url',
        'date_of_birth',
        'gender',
        'emergency_contact',
        'salary',
        'hire_date',
        'permissions',
        'access_level',
        'mfa_enabled',
        'mfa_secret'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin',
        'remember_token',
        'mfa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'is_active' => 'boolean',
            'last_login' => 'datetime',
            'date_of_birth' => 'date',
            'salary' => 'decimal:2',
            'hire_date' => 'date',
            'permissions' => 'array',
            'mfa_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class, 'customer_id', 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', 'user_id');
    }

    public function createdExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by', 'user_id');
    }

    public function approvedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'approved_by', 'user_id');
    }

    public function createdNoticesEvents(): HasMany
    {
        return $this->hasMany(NoticeEvent::class, 'created_by', 'user_id');
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'user_id', 'user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id', 'user_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'created_by', 'user_id');
    }

    public function createdPurchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'created_by', 'user_id');
    }

    public function managedWarehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'manager_id', 'user_id');
    }

    public function managedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'manager_id', 'user_id');
    }

    public function enabledTenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class, 'enabled_by', 'user_id');
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

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['tenant_admin', 'super_admin', 'admin']);
    }

    public function scopeManagers($query)
    {
        return $query->whereIn('role', ['warehouse_manager', 'shop_manager']);
    }

    public function scopeEmployees($query)
    {
        return $query->where('role', 'employee');
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }

    public function scopeWithMFA($query)
    {
        return $query->where('mfa_enabled', true);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['tenant_admin', 'super_admin', 'admin']);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['warehouse_manager', 'shop_manager']);
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === 'tenant_admin';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check individual permissions first
        $permissions = $this->permissions ?? [];
        if (in_array($permission, $permissions)) {
            return true;
        }

        // Fall back to role-based permissions
        $role = Role::where('name', $this->role)->first();
        if ($role) {
            return $role->hasPermission($permission);
        }

        return false;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function getPermissions(): array
    {
        return $this->permissions ?? [];
    }

    /**
     * Get permissions granted by the user's role.
     */
    public function getRolePermissions(): array
    {
        $role = Role::where('name', $this->role)->first();
        return $role ? $role->getPermissions() : [];
    }

    /**
     * Get effective permissions = role permissions + individual user permissions.
     */
    public function getAllPermissions(): array
    {
        return array_values(array_unique(array_merge($this->getRolePermissions(), $this->getPermissions())));
    }

    /**
     * Get Role model instance.
     */
    public function roleModel(): ?Role
    {
        return Role::where('name', $this->role)->first();
    }

    public function addPermission(string $permission): void
    {
        $permissions = $this->getPermissions();
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    public function removePermission(string $permission): void
    {
        $permissions = $this->getPermissions();
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);
        $this->save();
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled;
    }

    public function getFullName(): string
    {
        return $this->full_name;
    }

    public function updateLastLogin(): void
    {
        $this->last_login = now();
        $this->save();
    }

    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }
}
