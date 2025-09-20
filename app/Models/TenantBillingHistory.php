<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBillingHistory extends Model
{
    use HasFactory;

    protected $table = 'tenant_billing_history';
    protected $primaryKey = 'billing_id';

    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'billing_period_start',
        'billing_period_end',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'currency',
        'payment_status',
        'payment_method',
        'payment_date',
        'payment_reference',
        'invoice_date',
        'due_date',
        'invoice_url',
        'notes'
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending($query)
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

    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('billing_period_start', [$startDate, $endDate]);
    }

    // Helper methods
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isOverdue(): bool
    {
        return $this->payment_status === 'overdue' || 
               ($this->payment_status === 'pending' && $this->due_date < now());
    }

    public function isCancelled(): bool
    {
        return $this->payment_status === 'cancelled';
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->due_date));
    }

    public function getNetAmount(): float
    {
        return $this->subtotal - $this->discount_amount;
    }

    public function markAsPaid(string $paymentMethod = null, string $paymentReference = null): void
    {
        $this->payment_status = 'paid';
        $this->payment_date = now();
        
        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }
        
        if ($paymentReference) {
            $this->payment_reference = $paymentReference;
        }
        
        $this->save();
    }

    public function markAsOverdue(): void
    {
        if ($this->payment_status === 'pending' && $this->due_date < now()) {
            $this->payment_status = 'overdue';
            $this->save();
        }
    }
}
