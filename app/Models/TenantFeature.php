<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeature extends Model
{
    use HasFactory;

    protected $table = 'tenant_features';
    protected $primaryKey = 'tenant_feature_id';

    protected $fillable = [
        'tenant_id',
        'feature_key',
        'is_enabled',
        'settings',
        'display_order',
        'is_visible_dashboard',
        'is_pinned',
        'custom_name',
        'enabled_by',
        'enabled_at',
        'disabled_at'
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
        'display_order' => 'integer',
        'is_visible_dashboard' => 'boolean',
        'is_pinned' => 'boolean',
        'enabled_at' => 'datetime',
        'disabled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function systemFeature(): BelongsTo
    {
        return $this->belongsTo(SystemFeature::class, 'feature_key', 'feature_key');
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by', 'user_id');
    }

    // Scopes
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeDisabled($query)
    {
        return $query->where('is_enabled', false);
    }

    public function scopeVisibleOnDashboard($query)
    {
        return $query->where('is_visible_dashboard', true)->where('is_enabled', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true)->where('is_enabled', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('custom_name');
    }

    // Helper methods
    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function isVisibleOnDashboard(): bool
    {
        return $this->is_visible_dashboard && $this->is_enabled;
    }

    public function isPinned(): bool
    {
        return $this->is_pinned && $this->is_enabled;
    }

    public function getDisplayName(): string
    {
        return $this->custom_name ?: $this->systemFeature->feature_name ?? 'Unknown Feature';
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    public function updateSettings(array $settings): void
    {
        $this->settings = array_merge($this->getSettings(), $settings);
        $this->save();
    }

    public function enable(): void
    {
        $this->is_enabled = true;
        $this->enabled_at = now();
        $this->disabled_at = null;
        $this->save();
    }

    public function disable(): void
    {
        $this->is_enabled = false;
        $this->disabled_at = now();
        $this->save();
    }
}
