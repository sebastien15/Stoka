<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';
    protected $primaryKey = 'supplier_id';

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_person',
        'email',
        'phone_number',
        'address',
        'city',
        'country',
        'tax_number',
        'payment_terms',
        'credit_limit',
        'rating',
        'is_active'
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'rating' => 'decimal:1',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id', 'supplier_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'supplier_id', 'supplier_id');
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

    public function scopeWithProducts($query)
    {
        return $query->has('products');
    }

    public function scopeHighRated($query, float $minRating = 4.0)
    {
        return $query->where('rating', '>=', $minRating);
    }

    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    public function hasPurchases(): bool
    {
        return $this->purchases()->exists();
    }

    public function getProductCount(): int
    {
        return $this->products()->count();
    }

    public function getActiveProductCount(): int
    {
        return $this->products()->where('status', 'active')->count();
    }

    public function getPurchaseCount(): int
    {
        return $this->purchases()->count();
    }

    public function getTotalPurchaseAmount(): float
    {
        return $this->purchases()
            ->where('status', 'completed')
            ->sum('total_amount');
    }

    public function getAveragePurchaseAmount(): float
    {
        $completedPurchases = $this->purchases()->where('status', 'completed');
        
        if ($completedPurchases->count() === 0) {
            return 0;
        }
        
        return $completedPurchases->sum('total_amount') / $completedPurchases->count();
    }

    public function getPendingPurchaseAmount(): float
    {
        return $this->purchases()
            ->whereIn('status', ['pending', 'confirmed', 'partially_received'])
            ->sum('total_amount');
    }

    public function getRatingStars(): string
    {
        $rating = $this->rating ?? 0;
        $fullStars = floor($rating);
        $hasHalfStar = ($rating - $fullStars) >= 0.5;
        
        $stars = str_repeat('★', $fullStars);
        if ($hasHalfStar) {
            $stars .= '☆';
        }
        $stars .= str_repeat('☆', 5 - strlen($stars));
        
        return $stars;
    }

    public function updateRating(float $newRating): void
    {
        $this->rating = max(0, min(5, $newRating)); // Ensure rating is between 0 and 5
        $this->save();
    }

    public function getOutstandingBalance(): float
    {
        return $this->purchases()
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->sum('total_amount');
    }

    public function isWithinCreditLimit(float $additionalAmount = 0): bool
    {
        if (!$this->credit_limit) {
            return true; // No credit limit set
        }
        
        $currentOutstanding = $this->getOutstandingBalance();
        return ($currentOutstanding + $additionalAmount) <= $this->credit_limit;
    }

    public function getAvailableCredit(): float
    {
        if (!$this->credit_limit) {
            return PHP_FLOAT_MAX; // No limit
        }
        
        return max(0, $this->credit_limit - $this->getOutstandingBalance());
    }

    public function getRecentPurchases(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->purchases()
            ->latest('order_date')
            ->limit($limit)
            ->get();
    }

    public function canBeDeleted(): bool
    {
        return !$this->hasProducts() && !$this->hasPurchases();
    }

    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }
}
