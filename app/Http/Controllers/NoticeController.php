<?php

namespace App\Http\Controllers;

use App\Models\NoticeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class NoticeController extends BaseController
{
    /**
     * Display a listing of notices and events
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.view');

        $query = $this->applyTenantScope(NoticeEvent::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'title',
            'content'
        ]);

        // Type filter
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        // Priority filter
        if ($request->has('priority')) {
            $query->where('priority', $request->get('priority'));
        }

        // Target audience filter
        if ($request->has('target_audience')) {
            $query->where('target_audience', $request->get('target_audience'));
        }

        // Published filter
        if ($request->has('published')) {
            $query->where('is_published', (bool) $request->get('published'));
        }

        // Active filter (not expired)
        if ($request->has('active') && $request->get('active')) {
            $query->active();
        }

        // Expired filter
        if ($request->has('expired') && $request->get('expired')) {
            $query->expired();
        }

        // Date range filters
        if ($request->has('publish_date_from')) {
            $query->whereDate('publish_date', '>=', $request->get('publish_date_from'));
        }
        if ($request->has('publish_date_to')) {
            $query->whereDate('publish_date', '<=', $request->get('publish_date_to'));
        }

        // Optimize with eager loading and specific columns
        $query->with('createdBy:id,user_id,full_name,email,role');

        return $this->paginatedResponse($query, $request, 'Notices retrieved successfully');
    }

    /**
     * Store a newly created notice
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.create');

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'type' => 'required|in:notice,announcement,event,alert,reminder',
            'priority' => 'required|in:low,medium,high,urgent',
            'target_audience' => 'required|in:all,admins,managers,employees,customers,specific',
            'publish_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:publish_date',
            'attachment_url' => 'nullable|url|max:500',
            'is_published' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $noticeData = $validator->validated();
            $noticeData['tenant_id'] = $this->tenant->tenant_id;
            $noticeData['created_by'] = auth()->id();
            $noticeData['publish_date'] = $noticeData['publish_date'] ?? now();
            $noticeData['is_published'] = $noticeData['is_published'] ?? true;

            $notice = NoticeEvent::create($noticeData);

            $this->logActivity('notice_created', 'notice_events', $notice->notice_id);

            DB::commit();

            $notice->load('createdBy:id,user_id,full_name,email,role');

            return $this->successResponse($notice, 'Notice created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create notice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified notice
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.view');

        $notice = $this->applyTenantScope(NoticeEvent::query())
            ->with('createdBy:id,user_id,full_name,email,role')
            ->find($id);

        if (!$notice) {
            return $this->errorResponse('Notice not found', 404);
        }

        // Add additional information
        $notice->stats = [
            'days_until_expiry' => $notice->getDaysUntilExpiry(),
            'days_since_published' => $notice->getDaysSincePublished(),
            'is_active' => $notice->isActive(),
            'is_expired' => $notice->isExpired(),
            'target_audience_display' => $notice->getTargetAudienceDisplay(),
            'priority_display' => $notice->getPriorityDisplay(),
            'type_display' => $notice->getTypeDisplay()
        ];

        return $this->successResponse($notice, 'Notice retrieved successfully');
    }

    /**
     * Update the specified notice
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.edit');

        $notice = $this->applyTenantScope(NoticeEvent::query())->find($id);

        if (!$notice) {
            return $this->errorResponse('Notice not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:200',
            'content' => 'sometimes|string',
            'type' => 'sometimes|in:notice,announcement,event,alert,reminder',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'target_audience' => 'sometimes|in:all,admins,managers,employees,customers,specific',
            'publish_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:publish_date',
            'attachment_url' => 'nullable|url|max:500',
            'is_published' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $notice->toArray();
            $notice->update($validator->validated());

            $this->logActivity('notice_updated', 'notice_events', $notice->notice_id, [
                'old_values' => $oldValues,
                'new_values' => $notice->toArray()
            ]);

            DB::commit();

            $notice->load('createdBy:id,user_id,full_name,email,role');

            return $this->successResponse($notice, 'Notice updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update notice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified notice
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.delete');

        $notice = $this->applyTenantScope(NoticeEvent::query())->find($id);

        if (!$notice) {
            return $this->errorResponse('Notice not found', 404);
        }

        try {
            DB::beginTransaction();

            $noticeData = $notice->toArray();
            $notice->delete();

            $this->logActivity('notice_deleted', 'notice_events', $id, ['deleted_notice' => $noticeData]);

            DB::commit();

            return $this->successResponse(null, 'Notice deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete notice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Publish notice
     */
    public function publish(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.publish');

        $notice = $this->applyTenantScope(NoticeEvent::query())->find($id);

        if (!$notice) {
            return $this->errorResponse('Notice not found', 404);
        }

        if ($notice->is_published) {
            return $this->errorResponse('Notice is already published', 400);
        }

        try {
            DB::beginTransaction();

            $notice->publish();
            $this->logActivity('notice_published', 'notice_events', $notice->notice_id);

            DB::commit();

            return $this->successResponse($notice, 'Notice published successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to publish notice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Unpublish notice
     */
    public function unpublish(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.publish');

        $notice = $this->applyTenantScope(NoticeEvent::query())->find($id);

        if (!$notice) {
            return $this->errorResponse('Notice not found', 404);
        }

        if (!$notice->is_published) {
            return $this->errorResponse('Notice is not published', 400);
        }

        try {
            DB::beginTransaction();

            $notice->unpublish();
            $this->logActivity('notice_unpublished', 'notice_events', $notice->notice_id);

            DB::commit();

            return $this->successResponse($notice, 'Notice unpublished successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to unpublish notice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get public notices for current user
     */
    public function publicNotices(Request $request): JsonResponse
    {
        $this->requireTenant();

        $user = auth()->user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $query = $this->applyTenantScope(NoticeEvent::query())
            ->published()
            ->active()
            ->forUserRole($user->role);

        // Priority filter
        if ($request->has('priority')) {
            $query->where('priority', $request->get('priority'));
        }

        // Type filter
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        $query->orderBy('priority', 'desc')
              ->orderBy('publish_date', 'desc');

        return $this->paginatedResponse($query, $request, 'Public notices retrieved successfully');
    }

    /**
     * Get urgent notices
     */
    public function urgentNotices(): JsonResponse
    {
        $this->requireTenant();

        $user = auth()->user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $notices = $this->applyTenantScope(NoticeEvent::query())
            ->published()
            ->active()
            ->urgent()
            ->forUserRole($user->role)
            ->orderBy('publish_date', 'desc')
            ->limit(10)
            ->get();

        return $this->successResponse($notices, 'Urgent notices retrieved successfully');
    }

    /**
     * Get expiring notices
     */
    public function expiringNotices(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.view');

        $days = $request->get('days', 7); // Default to 7 days

        $query = $this->applyTenantScope(NoticeEvent::query())
            ->published()
            ->expiringIn($days)
            ->with('createdBy:id,user_id,full_name,email,role');

        return $this->paginatedResponse($query, $request, 'Expiring notices retrieved successfully');
    }

    /**
     * Get notice statistics
     */
    public function stats(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.view');

        $query = $this->applyTenantScope(NoticeEvent::query());

        $stats = [
            'total_notices' => $query->count(),
            'published_notices' => $query->published()->count(),
            'unpublished_notices' => $query->unpublished()->count(),
            'active_notices' => $query->active()->count(),
            'expired_notices' => $query->expired()->count(),
            'urgent_notices' => $query->urgent()->count(),
            'notices_by_type' => $query->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get(),
            'notices_by_priority' => $query->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->get(),
            'notices_by_target_audience' => $query->selectRaw('target_audience, COUNT(*) as count')
                ->groupBy('target_audience')
                ->get(),
            'recent_notices' => $query->published()
                ->where('publish_date', '>=', now()->subDays(30))
                ->count(),
            'expiring_soon' => $query->published()
                ->expiringIn(7)
                ->count()
        ];

        return $this->successResponse($stats, 'Notice statistics retrieved successfully');
    }

    /**
     * Get notice types
     */
    public function types(): JsonResponse
    {
        $types = [
            'notice' => 'General Notice',
            'announcement' => 'Announcement',
            'event' => 'Event',
            'alert' => 'Alert',
            'reminder' => 'Reminder'
        ];

        return $this->successResponse($types, 'Notice types retrieved successfully');
    }

    /**
     * Get priority levels
     */
    public function priorities(): JsonResponse
    {
        $priorities = [
            'low' => 'Low Priority',
            'medium' => 'Medium Priority',
            'high' => 'High Priority',
            'urgent' => 'Urgent'
        ];

        return $this->successResponse($priorities, 'Priority levels retrieved successfully');
    }

    /**
     * Get target audiences
     */
    public function targetAudiences(): JsonResponse
    {
        $audiences = [
            'all' => 'All Users',
            'admins' => 'Administrators',
            'managers' => 'Managers',
            'employees' => 'Employees',
            'customers' => 'Customers',
            'specific' => 'Specific Users'
        ];

        return $this->successResponse($audiences, 'Target audiences retrieved successfully');
    }

    /**
     * Bulk publish notices
     */
    public function bulkPublish(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.publish');

        $validator = Validator::make($request->all(), [
            'notice_ids' => 'required|array|min:1',
            'notice_ids.*' => 'integer|exists:notice_events,notice_id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $notices = $this->applyTenantScope(NoticeEvent::query())
            ->whereIn('notice_id', $request->notice_ids)
            ->where('is_published', false)
            ->get();

        if ($notices->count() !== count($request->notice_ids)) {
            return $this->errorResponse('Some notices not found, do not belong to tenant, or are already published', 400);
        }

        try {
            DB::beginTransaction();

            $results = [];
            foreach ($notices as $notice) {
                $notice->publish();
                $results[] = "Notice '{$notice->title}' published";
            }

            $this->logActivity('bulk_notice_publish', 'notice_events', null, [
                'notice_ids' => $request->notice_ids,
                'results' => $results
            ]);

            DB::commit();

            return $this->successResponse($results, 'Notices published successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk publish failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk delete notices
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('notices.delete');

        $validator = Validator::make($request->all(), [
            'notice_ids' => 'required|array|min:1',
            'notice_ids.*' => 'integer|exists:notice_events,notice_id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $notices = $this->applyTenantScope(NoticeEvent::query())
            ->whereIn('notice_id', $request->notice_ids)
            ->get();

        if ($notices->count() !== count($request->notice_ids)) {
            return $this->errorResponse('Some notices not found or do not belong to tenant', 400);
        }

        try {
            DB::beginTransaction();

            $results = [];
            foreach ($notices as $notice) {
                $title = $notice->title;
                $notice->delete();
                $results[] = "Notice '{$title}' deleted";
            }

            $this->logActivity('bulk_notice_delete', 'notice_events', null, [
                'notice_ids' => $request->notice_ids,
                'results' => $results
            ]);

            DB::commit();

            return $this->successResponse($results, 'Notices deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk delete failed: ' . $e->getMessage(), 500);
        }
    }
}
