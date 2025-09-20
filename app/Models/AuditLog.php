<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';
    protected $primaryKey = 'log_id';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'ip_address' => 'string',
        'created_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTable($query, $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function scopeForRecord($query, $tableName, $recordId)
    {
        return $query->where('table_name', $tableName)->where('record_id', $recordId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeCreated($query)
    {
        return $query->where('action', 'created');
    }

    public function scopeUpdated($query)
    {
        return $query->where('action', 'updated');
    }

    public function scopeDeleted($query)
    {
        return $query->where('action', 'deleted');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    // Helper methods
    public function getActionLabel(): string
    {
        $labels = [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'viewed' => 'Viewed',
            'exported' => 'Exported',
            'imported' => 'Imported',
            'login' => 'Logged In',
            'logout' => 'Logged Out',
            'password_change' => 'Password Changed',
            'settings_change' => 'Settings Changed'
        ];

        return $labels[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    public function getActionIcon(): string
    {
        $icons = [
            'created' => 'plus',
            'updated' => 'pencil',
            'deleted' => 'trash',
            'viewed' => 'eye',
            'exported' => 'download',
            'imported' => 'upload',
            'login' => 'login',
            'logout' => 'logout',
            'password_change' => 'key',
            'settings_change' => 'cog'
        ];

        return $icons[$this->action] ?? 'document';
    }

    public function getActionColor(): string
    {
        $colors = [
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'viewed' => 'gray',
            'exported' => 'purple',
            'imported' => 'indigo',
            'login' => 'green',
            'logout' => 'orange',
            'password_change' => 'yellow',
            'settings_change' => 'blue'
        ];

        return $colors[$this->action] ?? 'gray';
    }

    public function getTableDisplayName(): string
    {
        $names = [
            'users' => 'User',
            'products' => 'Product',
            'orders' => 'Order',
            'purchases' => 'Purchase',
            'categories' => 'Category',
            'suppliers' => 'Supplier',
            'warehouses' => 'Warehouse',
            'shops' => 'Shop',
            'expenses' => 'Expense',
            'inventory_movements' => 'Inventory Movement',
            'tenants' => 'Tenant',
            'system_features' => 'System Feature',
            'tenant_features' => 'Tenant Feature'
        ];

        return $names[$this->table_name] ?? ucfirst(str_replace('_', ' ', $this->table_name));
    }

    public function hasOldValues(): bool
    {
        return !empty($this->old_values);
    }

    public function hasNewValues(): bool
    {
        return !empty($this->new_values);
    }

    public function getChangedFields(): array
    {
        if (!$this->hasOldValues() || !$this->hasNewValues()) {
            return [];
        }

        $changed = [];
        foreach ($this->new_values as $field => $newValue) {
            $oldValue = $this->old_values[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changed[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        return $changed;
    }

    public function getChangedFieldsCount(): int
    {
        return count($this->getChangedFields());
    }

    public function getDescription(): string
    {
        $user = $this->user ? $this->user->getFullName() : 'System';
        $table = $this->getTableDisplayName();
        $action = strtolower($this->getActionLabel());
        
        $description = "{$user} {$action} {$table}";
        
        if ($this->record_id) {
            $description .= " (ID: {$this->record_id})";
        }

        return $description;
    }

    public function getDetailedDescription(): string
    {
        $description = $this->getDescription();
        
        if ($this->action === 'updated' && $this->getChangedFieldsCount() > 0) {
            $fields = array_keys($this->getChangedFields());
            $description .= ". Changed fields: " . implode(', ', $fields);
        }

        return $description;
    }

    public function getUserInfo(): array
    {
        return [
            'user_id' => $this->user_id,
            'user_name' => $this->user ? $this->user->getFullName() : null,
            'user_email' => $this->user ? $this->user->email : null,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent
        ];
    }

    public function formatOldValue($field): string
    {
        $value = $this->old_values[$field] ?? null;
        return $this->formatValue($value);
    }

    public function formatNewValue($field): string
    {
        $value = $this->new_values[$field] ?? null;
        return $this->formatValue($value);
    }

    private function formatValue($value): string
    {
        if ($value === null) {
            return '(null)';
        }
        
        if ($value === '') {
            return '(empty)';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_array($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }

    // Static methods for logging
    public static function logCreate(
        int $tenantId,
        string $tableName,
        int $recordId,
        array $newValues,
        int $userId = null
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId ?: auth()->id(),
            'action' => 'created',
            'table_name' => $tableName,
            'record_id' => $recordId,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public static function logUpdate(
        int $tenantId,
        string $tableName,
        int $recordId,
        array $oldValues,
        array $newValues,
        int $userId = null
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId ?: auth()->id(),
            'action' => 'updated',
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public static function logDelete(
        int $tenantId,
        string $tableName,
        int $recordId,
        array $oldValues,
        int $userId = null
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId ?: auth()->id(),
            'action' => 'deleted',
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public static function logAction(
        int $tenantId,
        string $action,
        string $tableName = null,
        int $recordId = null,
        array $data = [],
        int $userId = null
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId ?: auth()->id(),
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'new_values' => $data,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    // Reporting methods
    public static function getActivityByUser(int $tenantId, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('user_id, COUNT(*) as activity_count')
            ->with('user')
            ->groupBy('user_id')
            ->orderBy('activity_count', 'desc')
            ->get();
    }

    public static function getActivityByAction(int $tenantId, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get();
    }

    public static function getActivityByTable(int $tenantId, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('table_name, COUNT(*) as count')
            ->groupBy('table_name')
            ->orderBy('count', 'desc')
            ->get();
    }

    public static function getRecentActivity(int $tenantId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tenant_id', $tenantId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
