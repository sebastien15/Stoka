<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ExpenseController extends BaseController
{
    /**
     * Display a listing of expenses
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.view');

        $query = $this->applyTenantScope(Expense::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'expense_number',
            'description',
            'vendor_name'
        ]);

        // Category filter
        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }

        // Subcategory filter
        if ($request->has('subcategory')) {
            $query->where('subcategory', $request->get('subcategory'));
        }

        // Shop filter
        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->get('shop_id'));
        }

        // Warehouse filter
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        // Payment status filter
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        // Approval status filter
        if ($request->has('approval_status')) {
            $query->where('approval_status', $request->get('approval_status'));
        }

        // Payment method filter
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        // Date range filters
        if ($request->has('expense_date_from')) {
            $query->whereDate('expense_date', '>=', $request->get('expense_date_from'));
        }
        if ($request->has('expense_date_to')) {
            $query->whereDate('expense_date', '<=', $request->get('expense_date_to'));
        }

        // Amount range filters
        if ($request->has('min_amount')) {
            $query->where('amount', '>=', $request->get('min_amount'));
        }
        if ($request->has('max_amount')) {
            $query->where('amount', '<=', $request->get('max_amount'));
        }

        // Load relationships
        $query->with(['shop', 'warehouse', 'approvedBy', 'createdBy']);

        return $this->paginatedResponse($query, $request, 'Expenses retrieved successfully');
    }

    /**
     * Store a newly created expense
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.create');

        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'vendor_name' => 'nullable|string|max:150',
            'expense_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:expense_date',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,mobile_money',
            'receipt_url' => 'nullable|url|max:500',
            'shop_id' => 'nullable|exists:shops,shop_id',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $expenseData = $validator->validated();
            $expenseData['tenant_id'] = $this->tenant->tenant_id;
            $expenseData['expense_number'] = $this->generateExpenseNumber();
            $expenseData['approval_status'] = 'pending';
            $expenseData['payment_status'] = 'pending';
            $expenseData['created_by'] = auth()->id();

            // Validate shop belongs to tenant if provided
            if (!empty($expenseData['shop_id'])) {
                $shop = $this->tenant->shops()->find($expenseData['shop_id']);
                if (!$shop) {
                    return $this->errorResponse('Shop not found or does not belong to tenant', 400);
                }
            }

            // Validate warehouse belongs to tenant if provided
            if (!empty($expenseData['warehouse_id'])) {
                $warehouse = $this->tenant->warehouses()->find($expenseData['warehouse_id']);
                if (!$warehouse) {
                    return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);
                }
            }

            $expense = Expense::create($expenseData);

            $this->logActivity('expense_created', 'expenses', $expense->expense_id);

            DB::commit();

            $expense->load(['shop', 'warehouse', 'approvedBy', 'createdBy']);

            return $this->successResponse($expense, 'Expense created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create expense: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified expense
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.view');

        $expense = $this->applyTenantScope(Expense::query())
            ->with(['shop', 'warehouse', 'approvedBy', 'createdBy'])
            ->find($id);

        if (!$expense) {
            return $this->errorResponse('Expense not found', 404);
        }

        // Add additional information
        $expense->stats = [
            'days_until_due' => $expense->getDaysUntilDue(),
            'days_overdue' => $expense->getDaysOverdue(),
            'location_name' => $expense->getLocationName(),
            'full_category' => $expense->getFullCategory(),
            'can_be_approved' => $expense->canBeApproved(),
            'can_be_rejected' => $expense->canBeRejected(),
            'can_be_paid' => $expense->canBePaid(),
            'has_receipt' => $expense->hasReceipt()
        ];

        return $this->successResponse($expense, 'Expense retrieved successfully');
    }

    /**
     * Update the specified expense
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.edit');

        $expense = $this->applyTenantScope(Expense::query())->find($id);

        if (!$expense) {
            return $this->errorResponse('Expense not found', 404);
        }

        // Only allow updates for pending expenses
        if ($expense->approval_status !== 'pending') {
            return $this->errorResponse('Expense cannot be modified after approval/rejection', 400);
        }

        $validator = Validator::make($request->all(), [
            'category' => 'sometimes|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'amount' => 'sometimes|numeric|min:0',
            'description' => 'sometimes|string',
            'vendor_name' => 'nullable|string|max:150',
            'expense_date' => 'sometimes|date',
            'due_date' => 'nullable|date|after_or_equal:expense_date',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,mobile_money',
            'receipt_url' => 'nullable|url|max:500',
            'shop_id' => 'nullable|exists:shops,shop_id',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $expense->toArray();
            $expenseData = $validator->validated();

            // Validate shop belongs to tenant if provided
            if (isset($expenseData['shop_id']) && $expenseData['shop_id']) {
                $shop = $this->tenant->shops()->find($expenseData['shop_id']);
                if (!$shop) {
                    return $this->errorResponse('Shop not found or does not belong to tenant', 400);
                }
            }

            // Validate warehouse belongs to tenant if provided
            if (isset($expenseData['warehouse_id']) && $expenseData['warehouse_id']) {
                $warehouse = $this->tenant->warehouses()->find($expenseData['warehouse_id']);
                if (!$warehouse) {
                    return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);
                }
            }

            $expense->update($expenseData);

            $this->logActivity('expense_updated', 'expenses', $expense->expense_id, [
                'old_values' => $oldValues,
                'new_values' => $expense->toArray()
            ]);

            DB::commit();

            $expense->load(['shop', 'warehouse', 'approvedBy', 'createdBy']);

            return $this->successResponse($expense, 'Expense updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update expense: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified expense
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.delete');

        $expense = $this->applyTenantScope(Expense::query())->find($id);

        if (!$expense) {
            return $this->errorResponse('Expense not found', 404);
        }

        // Only allow deletion of pending expenses
        if ($expense->approval_status !== 'pending') {
            return $this->errorResponse('Only pending expenses can be deleted', 400);
        }

        try {
            DB::beginTransaction();

            $expenseData = $expense->toArray();
            $expense->delete();

            $this->logActivity('expense_deleted', 'expenses', $id, ['deleted_expense' => $expenseData]);

            DB::commit();

            return $this->successResponse(null, 'Expense deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete expense: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve expense
     */
    public function approve(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.approve');

        $expense = $this->applyTenantScope(Expense::query())->find($id);

        if (!$expense) {
            return $this->errorResponse('Expense not found', 404);
        }

        if (!$expense->canBeApproved()) {
            return $this->errorResponse('Expense cannot be approved in current status', 400);
        }

        try {
            DB::beginTransaction();

            $expense->approve(auth()->id());
            $this->logActivity('expense_approved', 'expenses', $expense->expense_id);

            DB::commit();

            return $this->successResponse($expense, 'Expense approved successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to approve expense: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject expense
     */
    public function reject(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.approve');

        $expense = $this->applyTenantScope(Expense::query())->find($id);

        if (!$expense) {
            return $this->errorResponse('Expense not found', 404);
        }

        if (!$expense->canBeRejected()) {
            return $this->errorResponse('Expense cannot be rejected in current status', 400);
        }

        try {
            DB::beginTransaction();

            $expense->reject(auth()->id());
            $this->logActivity('expense_rejected', 'expenses', $expense->expense_id);

            DB::commit();

            return $this->successResponse($expense, 'Expense rejected successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to reject expense: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark expense as paid
     */
    public function markPaid(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.manage_payment');

        $expense = $this->applyTenantScope(Expense::query())->find($id);

        if (!$expense) {
            return $this->errorResponse('Expense not found', 404);
        }

        if (!$expense->canBePaid()) {
            return $this->errorResponse('Expense cannot be marked as paid in current status', 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:cash,card,bank_transfer,mobile_money'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $expense->markAsPaid($request->payment_method);
            $this->logActivity('expense_payment_made', 'expenses', $expense->expense_id, [
                'payment_method' => $request->payment_method
            ]);

            DB::commit();

            return $this->successResponse($expense, 'Expense marked as paid successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to mark expense as paid: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get expense categories
     */
    public function categories(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.view');

        $categories = Expense::getCommonCategories();

        return $this->successResponse($categories, 'Expense categories retrieved successfully');
    }

    /**
     * Get expense statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.view');

        $query = $this->applyTenantScope(Expense::query());

        // Date range filter for stats
        if ($request->has('date_from')) {
            $query->whereDate('expense_date', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('expense_date', '<=', $request->get('date_to'));
        }

        $stats = [
            'total_expenses' => $query->count(),
            'pending_expenses' => $query->pending()->count(),
            'approved_expenses' => $query->approved()->count(),
            'rejected_expenses' => $query->rejected()->count(),
            'paid_expenses' => $query->paid()->count(),
            'unpaid_expenses' => $query->unpaid()->count(),
            'overdue_expenses' => $query->overdue()->count(),
            'total_amount' => $query->sum('amount'),
            'approved_amount' => $query->approved()->sum('amount'),
            'paid_amount' => $query->paid()->sum('amount'),
            'pending_approval_amount' => $query->pending()->sum('amount'),
            'overdue_amount' => $query->overdue()->sum('amount'),
            'expenses_by_category' => Expense::getTotalByCategory($this->tenant->tenant_id),
            'monthly_totals' => Expense::getMonthlyTotals($this->tenant->tenant_id),
            'expenses_by_payment_method' => $query->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->whereNotNull('payment_method')
                ->groupBy('payment_method')
                ->get()
        ];

        return $this->successResponse($stats, 'Expense statistics retrieved successfully');
    }

    /**
     * Get pending approvals
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.approve');

        $query = $this->applyTenantScope(Expense::query())
            ->pending()
            ->with(['shop', 'warehouse', 'createdBy']);

        return $this->paginatedResponse($query, $request, 'Pending expense approvals retrieved successfully');
    }

    /**
     * Get overdue expenses
     */
    public function overdue(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.view');

        $query = $this->applyTenantScope(Expense::query())
            ->overdue()
            ->with(['shop', 'warehouse', 'createdBy', 'approvedBy']);

        return $this->paginatedResponse($query, $request, 'Overdue expenses retrieved successfully');
    }

    /**
     * Bulk approve expenses
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('expenses.approve');

        $validator = Validator::make($request->all(), [
            'expense_ids' => 'required|array|min:1',
            'expense_ids.*' => 'integer|exists:expenses,expense_id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $expenses = $this->applyTenantScope(Expense::query())
            ->whereIn('expense_id', $request->expense_ids)
            ->where('approval_status', 'pending')
            ->get();

        if ($expenses->count() !== count($request->expense_ids)) {
            return $this->errorResponse('Some expenses not found, do not belong to tenant, or are not pending', 400);
        }

        try {
            DB::beginTransaction();

            $results = [];
            foreach ($expenses as $expense) {
                $expense->approve(auth()->id());
                $results[] = "Expense {$expense->expense_number} approved";
            }

            $this->logActivity('bulk_expense_approval', 'expenses', null, [
                'expense_ids' => $request->expense_ids,
                'results' => $results
            ]);

            DB::commit();

            return $this->successResponse($results, 'Expenses approved successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk approval failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate unique expense number
     */
    private function generateExpenseNumber(): string
    {
        $date = now()->format('Ymd');
        $count = $this->applyTenantScope(Expense::query())
            ->whereDate('created_at', now())
            ->count() + 1;
            
        return "EXP-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
