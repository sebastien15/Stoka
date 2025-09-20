<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    use HasFactory;

    protected $table = 'customer_profiles';
    protected $primaryKey = 'customer_id';
    public $incrementing = false; // customer_id is foreign key

    protected $fillable = [
        'customer_id',
        'tenant_id',
        'phone_number',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'date_of_birth',
        'gender',
        'preferred_language',
        'loyalty_points',
        'total_orders',
        'total_spent',
        'customer_tier',
        'marketing_consent',
        'preferred_contact_method'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'loyalty_points' => 'integer',
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
        'marketing_consent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id', 'user_id');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByTier($query, $tier)
    {
        return $query->where('customer_tier', $tier);
    }

    public function scopeWithMarketingConsent($query)
    {
        return $query->where('marketing_consent', true);
    }

    public function scopeByCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    public function scopeHighValue($query, $minSpent = 1000)
    {
        return $query->where('total_spent', '>=', $minSpent);
    }

    // Helper methods
    public function hasMarketingConsent(): bool
    {
        return $this->marketing_consent;
    }

    public function getAge(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return now()->diffInYears($this->date_of_birth);
    }

    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country
        ]);

        return implode(', ', $parts);
    }

    public function addLoyaltyPoints(int $points): void
    {
        $this->loyalty_points += $points;
        $this->save();
        $this->updateTier();
    }

    public function redeemLoyaltyPoints(int $points): bool
    {
        if ($this->loyalty_points < $points) {
            return false;
        }

        $this->loyalty_points -= $points;
        $this->save();
        $this->updateTier();
        
        return true;
    }

    public function updateOrderStats(float $orderAmount): void
    {
        $this->total_orders++;
        $this->total_spent += $orderAmount;
        $this->save();
        $this->updateTier();
    }

    public function updateTier(): void
    {
        $newTier = $this->calculateTier();
        
        if ($this->customer_tier !== $newTier) {
            $this->customer_tier = $newTier;
            $this->save();
        }
    }

    private function calculateTier(): string
    {
        if ($this->total_spent >= 5000) {
            return 'platinum';
        } elseif ($this->total_spent >= 2000) {
            return 'gold';
        } elseif ($this->total_spent >= 500) {
            return 'silver';
        } else {
            return 'bronze';
        }
    }

    public function getTierBenefits(): array
    {
        $benefits = [
            'bronze' => [
                'discount_percentage' => 0,
                'free_shipping_threshold' => 100,
                'points_multiplier' => 1
            ],
            'silver' => [
                'discount_percentage' => 5,
                'free_shipping_threshold' => 75,
                'points_multiplier' => 1.2
            ],
            'gold' => [
                'discount_percentage' => 10,
                'free_shipping_threshold' => 50,
                'points_multiplier' => 1.5
            ],
            'platinum' => [
                'discount_percentage' => 15,
                'free_shipping_threshold' => 0,
                'points_multiplier' => 2
            ]
        ];

        return $benefits[$this->customer_tier] ?? $benefits['bronze'];
    }

    public function getDiscountPercentage(): float
    {
        return $this->getTierBenefits()['discount_percentage'];
    }

    public function getFreeShippingThreshold(): float
    {
        return $this->getTierBenefits()['free_shipping_threshold'];
    }

    public function getPointsMultiplier(): float
    {
        return $this->getTierBenefits()['points_multiplier'];
    }

    public function isEligibleForFreeShipping(float $orderAmount): bool
    {
        return $orderAmount >= $this->getFreeShippingThreshold();
    }

    public function calculateLoyaltyPoints(float $orderAmount): int
    {
        $basePoints = floor($orderAmount / 10); // 1 point per $10 spent
        return intval($basePoints * $this->getPointsMultiplier());
    }

    public function getAverageOrderValue(): float
    {
        if ($this->total_orders === 0) {
            return 0;
        }

        return $this->total_spent / $this->total_orders;
    }

    public function getTierName(): string
    {
        return ucfirst($this->customer_tier);
    }

    public function getTierColor(): string
    {
        $colors = [
            'bronze' => '#CD7F32',
            'silver' => '#C0C0C0',
            'gold' => '#FFD700',
            'platinum' => '#E5E4E2'
        ];

        return $colors[$this->customer_tier] ?? $colors['bronze'];
    }
}
