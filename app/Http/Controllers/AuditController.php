<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AuditController extends BaseController
{
    /**
     * Display audit logs
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        $query = $this->applyTenantScope(AuditLog::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, []);

        // User filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Action filter
        if ($request->has('action')) {
            $query->where('action', 'LIKE', '%' . $request->get('action') . '%');
        }

        // Table filter
        if ($request->has('table_name')) {
            $query->where('table_name', $request->get('table_name'));
        }

        // Record filter
        if ($request->has('record_id')) {
            $query->where('record_id', $request->get('record_id'));
        }

        // IP address filter
        if ($request->has('ip_address')) {
            $query->where('ip_address', $request->get('ip_address'));
        }

        // Date range filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // Optimize with eager loading and specific columns
        $query->with('user:id,user_id,full_name,email,role');

        // Order by latest first
        $query->latest('created_at');

        return $this->paginatedResponse($query, $request, 'Audit logs retrieved successfully');
    }

    /**
     * Display specific audit log
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        $auditLog = $this->applyTenantScope(AuditLog::query())
            ->with('user:id,user_id,full_name,email,role')
            ->find($id);

        if (!$auditLog) {
            return $this->errorResponse('Audit log not found', 404);
        }

        // Add additional information
        $auditLog->stats = [
            'changes_count' => $auditLog->getChangesCount(),
            'has_old_values' => $auditLog->hasOldValues(),
            'has_new_values' => $auditLog->hasNewValues(),
            'browser_info' => $auditLog->getBrowserInfo(),
            'operating_system' => $auditLog->getOperatingSystem(),
            'time_ago' => $auditLog->getTimeAgo()
        ];

        return $this->successResponse($auditLog, 'Audit log retrieved successfully');
    }

    /**
     * Get audit trail for specific record
     */
    public function recordTrail(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        $validator = Validator::make($request->all(), [
            'table_name' => 'required|string',
            'record_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $query = $this->applyTenantScope(AuditLog::query())
            ->where('table_name', $request->table_name)
            ->where('record_id', $request->record_id)
            ->with('user:id,user_id,full_name,email,role')
            ->latest('created_at');

        return $this->paginatedResponse($query, $request, 'Record audit trail retrieved successfully');
    }

    /**
     * Get user activity
     */
    public function userActivity(int $userId, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        // Validate user belongs to tenant
        $user = $this->applyTenantScope(\App\Models\User::query())->find($userId);
        if (!$user) {
            return $this->errorResponse('User not found or does not belong to tenant', 404);
        }

        $query = $this->applyTenantScope(AuditLog::query())
            ->where('user_id', $userId)
            ->with('user:id,user_id,full_name,email,role')
            ->latest('created_at');

        // Action filter
        if ($request->has('action')) {
            $query->where('action', 'LIKE', '%' . $request->get('action') . '%');
        }

        // Date range filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        return $this->paginatedResponse($query, $request, 'User activity retrieved successfully');
    }

    /**
     * Get audit statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        $query = $this->applyTenantScope(AuditLog::query());

        // Date range filter for stats
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $stats = [
            'total_entries' => $query->count(),
            'unique_users' => $query->distinct('user_id')->count('user_id'),
            'unique_ip_addresses' => $query->distinct('ip_address')->count('ip_address'),
            'entries_today' => $query->whereDate('created_at', now())->count(),
            'entries_this_week' => $query->where('created_at', '>=', now()->startOfWeek())->count(),
            'entries_this_month' => $query->where('created_at', '>=', now()->startOfMonth())->count(),
            'actions_by_type' => $query->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'tables_by_activity' => $query->selectRaw('table_name, COUNT(*) as count')
                ->whereNotNull('table_name')
                ->groupBy('table_name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'users_by_activity' => $query->join('users', 'audit_logs.user_id', '=', 'users.user_id')
                ->selectRaw('users.full_name, COUNT(*) as count')
                ->groupBy('users.user_id', 'users.full_name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'activity_by_hour' => $query->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('hour')
                ->orderBy('hour')
                ->get(),
            'activity_by_day' => $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
        ];

        return $this->successResponse($stats, 'Audit statistics retrieved successfully');
    }

    /**
     * Get recent activity
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        $limit = min($request->get('limit', 50), 100); // Max 100 items

        $recentLogs = $this->applyTenantScope(AuditLog::query())
            ->with('user:id,user_id,full_name,email,role')
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return $this->successResponse($recentLogs, 'Recent activity retrieved successfully');
    }

    /**
     * Get security events
     */
    public function securityEvents(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        $securityActions = [
            'login', 'logout', 'login_failed', 'password_changed', 'password_reset_requested',
            'user_created', 'user_deleted', 'user_activated', 'user_deactivated',
            'permissions_updated', 'tenant_created', 'tenant_suspended'
        ];

        $query = $this->applyTenantScope(AuditLog::query())
            ->whereIn('action', $securityActions)
            ->with('user:id,user_id,full_name,email,role')
            ->latest('created_at');

        // Date range filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        return $this->paginatedResponse($query, $request, 'Security events retrieved successfully');
    }

    /**
     * Get failed login attempts
     */
    public function failedLogins(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        $query = $this->applyTenantScope(AuditLog::query())
            ->where('action', 'login_failed')
            ->with('user:id,user_id,full_name,email,role')
            ->latest('created_at');

        // IP address filter
        if ($request->has('ip_address')) {
            $query->where('ip_address', $request->get('ip_address'));
        }

        // Date range filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        return $this->paginatedResponse($query, $request, 'Failed login attempts retrieved successfully');
    }

    /**
     * Export audit logs
     */
    public function export(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.export');

        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,json,xlsx',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'user_id' => 'nullable|exists:users,user_id',
            'action' => 'nullable|string',
            'table_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $query = $this->applyTenantScope(AuditLog::query())
            ->with('user:id,user_id,full_name,email,role');

        // Apply filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }
        if ($request->has('action')) {
            $query->where('action', 'LIKE', '%' . $request->get('action') . '%');
        }
        if ($request->has('table_name')) {
            $query->where('table_name', $request->get('table_name'));
        }

        // Limit to prevent memory issues
        $logs = $query->limit(10000)->get();

        // In a real implementation, you would generate and return the file
        // For now, we'll return the data that would be exported
        $exportData = $logs->map(function ($log) {
            return [
                'id' => $log->log_id,
                'user' => $log->user?->full_name ?? 'System',
                'action' => $log->action,
                'table' => $log->table_name,
                'record_id' => $log->record_id,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
                'changes' => $log->getChangesCount()
            ];
        });

        $this->logActivity('audit_logs_exported', 'audit_logs', null, [
            'format' => $request->format,
            'records_count' => $exportData->count(),
            'filters' => $request->only(['date_from', 'date_to', 'user_id', 'action', 'table_name'])
        ]);

        return $this->successResponse([
            'format' => $request->format,
            'records_count' => $exportData->count(),
            'data' => $exportData,
            'filename' => 'audit_logs_' . now()->format('Y-m-d_H-i-s') . '.' . $request->format
        ], 'Audit logs export prepared successfully');
    }

    /**
     * Get system changes
     */
    public function systemChanges(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.view');

        $systemActions = [
            'tenant_created', 'tenant_updated', 'tenant_deleted', 'tenant_suspended', 'tenant_activated',
            'system_configuration_updated', 'feature_enabled', 'feature_disabled'
        ];

        $query = $this->applyTenantScope(AuditLog::query())
            ->whereIn('action', $systemActions)
            ->with('user:id,user_id,full_name,email,role')
            ->latest('created_at');

        return $this->paginatedResponse($query, $request, 'System changes retrieved successfully');
    }

    /**
     * Clean old audit logs
     */
    public function cleanup(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('audit.cleanup');

        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:30|max:365'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $cutoffDate = now()->subDays($request->days);

        $deletedCount = $this->applyTenantScope(AuditLog::query())
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->logActivity('audit_logs_cleanup', 'audit_logs', null, [
            'cutoff_date' => $cutoffDate,
            'deleted_count' => $deletedCount,
            'retention_days' => $request->days
        ]);

        return $this->successResponse([
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate,
            'retention_days' => $request->days
        ], "Cleaned up {$deletedCount} old audit log entries");
    }
}
