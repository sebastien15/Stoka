<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeEvent extends Model
{
    use HasFactory;

    protected $table = 'notices_events';
    protected $primaryKey = 'notice_id';

    protected $fillable = [
        'tenant_id',
        'title',
        'content',
        'type',
        'priority',
        'target_audience',
        'is_published',
        'publish_date',
        'expiry_date',
        'attachment_url',
        'created_by'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'publish_date' => 'datetime',
        'expiry_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
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

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeDraft($query)
    {
        return $query->where('is_published', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_published', true)
                    ->where(function ($q) {
                        $q->whereNull('publish_date')
                          ->orWhere('publish_date', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>=', now());
                    });
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByAudience($query, $audience)
    {
        return $query->where('target_audience', $audience);
    }

    public function scopeNotices($query)
    {
        return $query->where('type', 'notice');
    }

    public function scopeEvents($query)
    {
        return $query->where('type', 'event');
    }

    public function scopeAnnouncements($query)
    {
        return $query->where('type', 'announcement');
    }

    public function scopeAlerts($query)
    {
        return $query->where('type', 'alert');
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isPublished(): bool
    {
        return $this->is_published;
    }

    public function isDraft(): bool
    {
        return !$this->is_published;
    }

    public function isActive(): bool
    {
        if (!$this->is_published) {
            return false;
        }

        if ($this->publish_date && $this->publish_date > now()) {
            return false;
        }

        if ($this->expiry_date && $this->expiry_date < now()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date < now();
    }

    public function isScheduled(): bool
    {
        return $this->publish_date && $this->publish_date > now();
    }

    public function hasAttachment(): bool
    {
        return !empty($this->attachment_url);
    }

    public function getTypeLabel(): string
    {
        $labels = [
            'notice' => 'Notice',
            'event' => 'Event',
            'announcement' => 'Announcement',
            'alert' => 'Alert',
            'maintenance' => 'Maintenance'
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }

    public function getPriorityLabel(): string
    {
        $labels = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];

        return $labels[$this->priority] ?? ucfirst($this->priority);
    }

    public function getAudienceLabel(): string
    {
        $labels = [
            'all' => 'Everyone',
            'admins' => 'Administrators',
            'managers' => 'Managers',
            'employees' => 'Employees',
            'customers' => 'Customers'
        ];

        return $labels[$this->target_audience] ?? ucfirst($this->target_audience);
    }

    public function getTypeIcon(): string
    {
        $icons = [
            'notice' => 'information-circle',
            'event' => 'calendar',
            'announcement' => 'speakerphone',
            'alert' => 'exclamation-triangle',
            'maintenance' => 'cog'
        ];

        return $icons[$this->type] ?? 'document';
    }

    public function getTypeColor(): string
    {
        $colors = [
            'notice' => 'blue',
            'event' => 'green',
            'announcement' => 'purple',
            'alert' => 'red',
            'maintenance' => 'orange'
        ];

        return $colors[$this->type] ?? 'gray';
    }

    public function getPriorityColor(): string
    {
        $colors = [
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red'
        ];

        return $colors[$this->priority] ?? 'gray';
    }

    public function getPriorityBadgeClass(): string
    {
        $classes = [
            'low' => 'bg-gray-100 text-gray-800',
            'medium' => 'bg-blue-100 text-blue-800',
            'high' => 'bg-orange-100 text-orange-800',
            'urgent' => 'bg-red-100 text-red-800'
        ];

        return $classes[$this->priority] ?? $classes['medium'];
    }

    public function getStatusBadgeClass(): string
    {
        if ($this->isDraft()) {
            return 'bg-gray-100 text-gray-800';
        } elseif ($this->isExpired()) {
            return 'bg-red-100 text-red-800';
        } elseif ($this->isScheduled()) {
            return 'bg-yellow-100 text-yellow-800';
        } elseif ($this->isActive()) {
            return 'bg-green-100 text-green-800';
        }

        return 'bg-gray-100 text-gray-800';
    }

    public function getStatusLabel(): string
    {
        if ($this->isDraft()) {
            return 'Draft';
        } elseif ($this->isExpired()) {
            return 'Expired';
        } elseif ($this->isScheduled()) {
            return 'Scheduled';
        } elseif ($this->isActive()) {
            return 'Active';
        }

        return 'Unknown';
    }

    public function getDaysUntilPublish(): ?int
    {
        if (!$this->publish_date || $this->publish_date <= now()) {
            return null;
        }

        return now()->diffInDays($this->publish_date);
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiry_date || $this->expiry_date <= now()) {
            return null;
        }

        return now()->diffInDays($this->expiry_date);
    }

    public function publish(): void
    {
        $this->is_published = true;
        
        if (!$this->publish_date) {
            $this->publish_date = now();
        }
        
        $this->save();
    }

    public function unpublish(): void
    {
        $this->is_published = false;
        $this->save();
    }

    public function schedule(\DateTime $publishDate, \DateTime $expiryDate = null): void
    {
        $this->publish_date = $publishDate;
        
        if ($expiryDate) {
            $this->expiry_date = $expiryDate;
        }
        
        $this->is_published = true;
        $this->save();
    }

    public function extend(\DateTime $newExpiryDate): void
    {
        $this->expiry_date = $newExpiryDate;
        $this->save();
    }

    public function canBeViewedBy(User $user): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return match($this->target_audience) {
            'all' => true,
            'admins' => $user->isAdmin(),
            'managers' => $user->isManager() || $user->isAdmin(),
            'employees' => $user->isEmployee() || $user->isManager() || $user->isAdmin(),
            'customers' => $user->isCustomer(),
            default => false
        };
    }

    public function getShortContent(int $length = 150): string
    {
        if (strlen($this->content) <= $length) {
            return $this->content;
        }

        return substr($this->content, 0, $length) . '...';
    }

    // Static helper methods
    public static function getTypes(): array
    {
        return [
            'notice' => 'Notice',
            'event' => 'Event',
            'announcement' => 'Announcement',
            'alert' => 'Alert',
            'maintenance' => 'Maintenance'
        ];
    }

    public static function getPriorities(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];
    }

    public static function getAudiences(): array
    {
        return [
            'all' => 'Everyone',
            'admins' => 'Administrators',
            'managers' => 'Managers',
            'employees' => 'Employees',
            'customers' => 'Customers'
        ];
    }
}
