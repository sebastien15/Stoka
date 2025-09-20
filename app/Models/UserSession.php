<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use HasFactory;

    protected $table = 'user_sessions';
    protected $primaryKey = 'session_id';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'session_token',
        'ip_address',
        'user_agent',
        'login_at',
        'logout_at',
        'is_active'
    ];

    protected $casts = [
        'ip_address' => 'string',
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'is_active' => 'boolean'
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeLoggedOut($query)
    {
        return $query->whereNotNull('logout_at');
    }

    public function scopeByIpAddress($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('login_at', '>=', now()->subHours($hours));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('login_at', now()->toDateString());
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active && $this->logout_at === null;
    }

    public function isLoggedOut(): bool
    {
        return !$this->is_active || $this->logout_at !== null;
    }

    public function getDuration(): ?int
    {
        if (!$this->logout_at) {
            return now()->diffInMinutes($this->login_at);
        }

        return $this->logout_at->diffInMinutes($this->login_at);
    }

    public function getDurationFormatted(): string
    {
        $minutes = $this->getDuration();
        
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($hours < 24) {
            return "{$hours}h {$remainingMinutes}m";
        }
        
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        
        return "{$days}d {$remainingHours}h";
    }

    public function getBrowser(): string
    {
        if (!$this->user_agent) {
            return 'Unknown';
        }

        $userAgent = $this->user_agent;

        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge';
        } elseif (strpos($userAgent, 'Opera') !== false) {
            return 'Opera';
        } elseif (strpos($userAgent, 'Internet Explorer') !== false) {
            return 'Internet Explorer';
        }

        return 'Unknown';
    }

    public function getOperatingSystem(): string
    {
        if (!$this->user_agent) {
            return 'Unknown';
        }

        $userAgent = $this->user_agent;

        if (strpos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'Mac OS') !== false) {
            return 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            return 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            return 'iOS';
        }

        return 'Unknown';
    }

    public function getDeviceType(): string
    {
        if (!$this->user_agent) {
            return 'Unknown';
        }

        $userAgent = strtolower($this->user_agent);

        if (strpos($userAgent, 'mobile') !== false || strpos($userAgent, 'android') !== false) {
            return 'Mobile';
        } elseif (strpos($userAgent, 'tablet') !== false || strpos($userAgent, 'ipad') !== false) {
            return 'Tablet';
        }

        return 'Desktop';
    }

    public function getLocationInfo(): array
    {
        // This would typically integrate with a GeoIP service
        // For now, return basic info
        return [
            'ip' => $this->ip_address,
            'country' => null,
            'city' => null,
            'region' => null
        ];
    }

    public function terminate(): void
    {
        $this->is_active = false;
        $this->logout_at = now();
        $this->save();
    }

    public function extend(): void
    {
        // Update last activity time (this could be handled differently)
        $this->touch();
    }

    // Static methods
    public static function createSession(int $tenantId, int $userId, string $token, string $ipAddress = null, string $userAgent = null): self
    {
        return static::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'session_token' => $token,
            'ip_address' => $ipAddress ?: request()->ip(),
            'user_agent' => $userAgent ?: request()->userAgent(),
            'login_at' => now(),
            'is_active' => true
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('session_token', $token)->where('is_active', true)->first();
    }

    public static function terminateAllForUser(int $userId): void
    {
        static::where('user_id', $userId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'logout_at' => now()
            ]);
    }

    public static function terminateAllForTenant(int $tenantId): void
    {
        static::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'logout_at' => now()
            ]);
    }

    public static function cleanupExpiredSessions(int $hours = 24): int
    {
        return static::where('is_active', true)
            ->where('login_at', '<', now()->subHours($hours))
            ->update([
                'is_active' => false,
                'logout_at' => now()
            ]);
    }

    public static function getActiveSessionsCount(int $tenantId = null): int
    {
        $query = static::where('is_active', true);
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        return $query->count();
    }

    public static function getUserSessionsCount(int $userId): int
    {
        return static::where('user_id', $userId)->where('is_active', true)->count();
    }

    public static function getRecentLogins(int $tenantId, int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tenant_id', $tenantId)
            ->where('login_at', '>=', now()->subHours($hours))
            ->with('user')
            ->orderBy('login_at', 'desc')
            ->get();
    }

    public static function getTopUsersByActivity(int $tenantId, int $days = 30, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tenant_id', $tenantId)
            ->where('login_at', '>=', now()->subDays($days))
            ->selectRaw('user_id, COUNT(*) as login_count, SUM(TIMESTAMPDIFF(MINUTE, login_at, COALESCE(logout_at, NOW()))) as total_minutes')
            ->with('user')
            ->groupBy('user_id')
            ->orderBy('login_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getSessionsByDay(int $tenantId, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tenant_id', $tenantId)
            ->where('login_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(login_at) as date, COUNT(*) as session_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
