<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemConfiguration extends Model
{
    use HasFactory;

    protected $table = 'system_configurations';
    protected $primaryKey = 'config_id';

    protected $fillable = [
        'tenant_id',
        'config_group',
        'config_key',
        'config_value',
        'data_type',
        'description',
        'is_encrypted',
        'is_public',
        'default_value',
        'validation_rules'
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_public' => 'boolean',
        'validation_rules' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    // Scopes
    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByGroup($query, $group)
    {
        return $query->where('config_group', $group);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_public', false);
    }

    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    // Helper methods
    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }

    public function isPublic(): bool
    {
        return $this->is_public;
    }

    public function isEncrypted(): bool
    {
        return $this->is_encrypted;
    }

    public function getValue()
    {
        $value = $this->config_value;

        if ($this->is_encrypted && $value) {
            $value = decrypt($value);
        }

        return $this->castValue($value);
    }

    public function setValue($value): void
    {
        if ($this->is_encrypted && $value !== null) {
            $this->config_value = encrypt($value);
        } else {
            $this->config_value = $value;
        }
    }

    private function castValue($value)
    {
        switch ($this->data_type) {
            case 'integer':
                return (int) $value;
            case 'decimal':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
            case 'array':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    public function getValidationRules(): array
    {
        return $this->validation_rules ?? [];
    }

    public function getDefaultValue()
    {
        return $this->castValue($this->default_value);
    }
}
