<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $table = 'expenses';
    protected $primaryKey = 'expense_id';

    protected $fillable = [
        'tenant_id',
        'expense_number',
        'shop_id',
        'warehouse_id',
        'category',
        'subcategory',
        'amount',
        'description',
        'receipt_url',
        'vendor_name',
        'payment_method',
        'payment_status',
        'expense_date',
        'due_date',
        'approved_by',
        'approval_status',
        'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('payment_status', 'overdue')
                    ->orWhere(function ($q) {
                        $q->where('payment_status', 'pending')
                          ->where('due_date', '<', now());
                    });
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBySubcategory($query, $subcategory)
    {
        return $query->where('subcategory', $subcategory);
    }

    public function scopeByShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeByAmountRange($query, $minAmount, $maxAmount)
    {
        return $query->whereBetween('amount', [$minAmount, $maxAmount]);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('expense_date', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isOverdue(): bool
    {
        return $this->payment_status === 'overdue' || 
               ($this->payment_status === 'pending' && $this->due_date && $this->due_date < now());
    }

    public function canBeApproved(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function canBeRejected(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function canBePaid(): bool
    {
        return $this->isApproved() && !$this->isPaid();
    }

    public function hasReceipt(): bool
    {
        return !empty($this->receipt_url);
    }

    public function getLocationName(): string
    {
        if ($this->shop_id) {
            return $this->shop->name ?? 'Unknown Shop';
        }
        
        if ($this->warehouse_id) {
            return $this->warehouse->name ?? 'Unknown Warehouse';
        }
        
        return 'General';
    }

    public function getFullCategory(): string
    {
        if ($this->subcategory) {
            return $this->category . ' > ' . $this->subcategory;
        }
        
        return $this->category;
    }

    public function getDaysUntilDue(): int
    {
        if (!$this->due_date) {
            return 0;
        }
        
        return now()->diffInDays($this->due_date, false);
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        return now()->diffInDays($this->due_date);
    }

    public function approve(int $approverId = null): void
    {
        if ($this->canBeApproved()) {
            $this->approval_status = 'approved';
            $this->approved_by = $approverId ?? auth()->id();
            $this->save();
        }
    }

    public function reject(int $approverId = null): void
    {
        if ($this->canBeRejected()) {
            $this->approval_status = 'rejected';
            $this->approved_by = $approverId ?? auth()->id();
            $this->save();
        }
    }

    public function markAsPaid(string $paymentMethod = null): void
    {
        if ($this->canBePaid()) {
            $this->payment_status = 'paid';
            
            if ($paymentMethod) {
                $this->payment_method = $paymentMethod;
            }
            
            $this->save();
        }
    }

    public function markAsOverdue(): void
    {
        if ($this->payment_status === 'pending' && $this->due_date && $this->due_date < now()) {
            $this->payment_status = 'overdue';
            $this->save();
        }
    }

    public function getApprovalStatusBadgeClass(): string
    {
        $classes = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800'
        ];

        return $classes[$this->approval_status] ?? $classes['pending'];
    }

    public function getPaymentStatusBadgeClass(): string
    {
        $classes = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'paid' => 'bg-green-100 text-green-800',
            'overdue' => 'bg-red-100 text-red-800'
        ];

        return $classes[$this->payment_status] ?? $classes['pending'];
    }

    public function getCategoryIcon(): string
    {
        $icons = [
            'office_supplies' => 'clipboard',
            'utilities' => 'lightning-bolt',
            'rent' => 'home',
            'insurance' => 'shield-check',
            'marketing' => 'speakerphone',
            'travel' => 'airplane',
            'meals' => 'cake',
            'equipment' => 'desktop-computer',
            'software' => 'code',
            'services' => 'cog',
            'maintenance' => 'wrench',
            'fuel' => 'truck',
            'other' => 'document'
        ];

        return $icons[strtolower(str_replace(' ', '_', $this->category))] ?? $icons['other'];
    }

    public function getCategoryColor(): string
    {
        $colors = [
            'office_supplies' => 'blue',
            'utilities' => 'yellow',
            'rent' => 'green',
            'insurance' => 'purple',
            'marketing' => 'pink',
            'travel' => 'indigo',
            'meals' => 'orange',
            'equipment' => 'gray',
            'software' => 'blue',
            'services' => 'teal',
            'maintenance' => 'red',
            'fuel' => 'orange',
            'other' => 'gray'
        ];

        return $colors[strtolower(str_replace(' ', '_', $this->category))] ?? $colors['other'];
    }

    public function generateExpenseNumber(): string
    {
        $date = now()->format('Ymd');
        $count = static::where('tenant_id', $this->tenant_id)
            ->whereDate('created_at', now())
            ->count() + 1;
            
        return "EXP-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // Static methods for common expense categories
    public static function getCommonCategories(): array
    {
        return [
            'Office Supplies',
            'Utilities',
            'Rent',
            'Insurance',
            'Marketing',
            'Travel',
            'Meals',
            'Equipment',
            'Software',
            'Services',
            'Maintenance',
            'Fuel',
            'Other'
        ];
    }

    public static function getPaymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'card' => 'Credit/Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'mobile_money' => 'Mobile Money'
        ];
    }

    // Reporting methods
    public static function getTotalByCategory($tenantId, $startDate = null, $endDate = null): \Illuminate\Support\Collection
    {
        $query = static::where('tenant_id', $tenantId)
            ->where('approval_status', 'approved');

        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        return $query->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get();
    }

    public static function getMonthlyTotals($tenantId, $year = null): \Illuminate\Support\Collection
    {
        $year = $year ?? now()->year;

        return static::where('tenant_id', $tenantId)
            ->where('approval_status', 'approved')
            ->whereYear('expense_date', $year)
            ->selectRaw('MONTH(expense_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }
}
