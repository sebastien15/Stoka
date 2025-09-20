<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemFeature extends Model
{
    use HasFactory;

    protected $table = 'system_features';
    protected $primaryKey = 'feature_id';

    protected $fillable = [
        'feature_key',
        'feature_name',
        'description',
        'category',
        'icon',
        'sort_order',
        'dependencies',
        'is_premium',
        'is_active'
    ];

    protected $casts = [
        'dependencies' => 'array',
        'sort_order' => 'integer',
        'is_premium' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class, 'feature_key', 'feature_key');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('feature_name');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isPremium(): bool
    {
        return $this->is_premium;
    }

    public function hasDependencies(): bool
    {
        return !empty($this->dependencies);
    }

    public function getDependencies(): array
    {
        return $this->dependencies ?? [];
    }
}
