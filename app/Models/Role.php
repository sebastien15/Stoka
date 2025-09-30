<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $primaryKey = 'role_id';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'default_permissions',
        'is_system_role',
        'is_active'
    ];

    protected $casts = [
        'default_permissions' => 'array',
        'is_system_role' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id', 'role_id');
    }

    public function getPermissions(): array
    {
        return $this->permissions()->pluck('permission')->toArray();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }

    public function addPermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            $this->permissions()->create(['permission' => $permission]);
        }
    }

    public function removePermission(string $permission): void
    {
        $this->permissions()->where('permission', $permission)->delete();
    }

    public function syncPermissions(array $permissions): void
    {
        $this->permissions()->delete();
        foreach ($permissions as $permission) {
            $this->permissions()->create(['permission' => $permission]);
        }
    }

    public function isSystemRole(): bool
    {
        return $this->is_system_role;
    }

    public function canBeDeleted(): bool
    {
        return $this->name !== 'super_admin' && !User::where('role', $this->name)->exists();
    }
}


