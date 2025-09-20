<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SupplierController extends BaseController
{
    /**
     * Display a listing of suppliers
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.view');

        $query = $this->applyTenantScope(Supplier::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'name',
            'contact_person',
            'email',
            'phone_number'
        ]);

        // Country filter
        if ($request->has('country')) {
            $query->where('country', $request->get('country'));
        }

        // Rating filter
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->get('min_rating'));
        }

        // With products filter
        if ($request->has('has_products') && $request->get('has_products')) {
            $query->withProducts();
        }

        $query->withCount(['products', 'purchases']);

        return $this->paginatedResponse($query, $request, 'Suppliers retrieved successfully');
    }

    /**
     * Store a newly created supplier
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.create');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'rating' => 'nullable|numeric|min:0|max:5'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $supplierData = $validator->validated();
            $supplierData['tenant_id'] = $this->tenant->tenant_id;
            $supplierData['is_active'] = true;

            $supplier = Supplier::create($supplierData);

            $this->logActivity('supplier_created', 'suppliers', $supplier->supplier_id);

            DB::commit();

            return $this->successResponse($supplier, 'Supplier created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified supplier
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.view');

        $supplier = $this->applyTenantScope(Supplier::query())
            ->withCount(['products', 'purchases'])
            ->find($id);

        if (!$supplier) {
            return $this->errorResponse('Supplier not found', 404);
        }

        // Add statistics
        $supplier->stats = [
            'products_count' => $supplier->getProductCount(),
            'active_products_count' => $supplier->getActiveProductCount(),
            'purchases_count' => $supplier->getPurchaseCount(),
            'total_purchase_amount' => $supplier->getTotalPurchaseAmount(),
            'average_purchase_amount' => $supplier->getAveragePurchaseAmount(),
            'pending_purchase_amount' => $supplier->getPendingPurchaseAmount(),
            'outstanding_balance' => $supplier->getOutstandingBalance(),
            'available_credit' => $supplier->getAvailableCredit(),
            'rating_stars' => $supplier->getRatingStars()
        ];

        // Get recent purchases
        $supplier->recent_purchases = $supplier->getRecentPurchases(5);

        return $this->successResponse($supplier, 'Supplier retrieved successfully');
    }

    /**
     * Update the specified supplier
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.edit');

        $supplier = $this->applyTenantScope(Supplier::query())->find($id);

        if (!$supplier) {
            return $this->errorResponse('Supplier not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'rating' => 'nullable|numeric|min:0|max:5',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $supplier->toArray();
            $supplier->update($validator->validated());

            $this->logActivity('supplier_updated', 'suppliers', $supplier->supplier_id, [
                'old_values' => $oldValues,
                'new_values' => $supplier->toArray()
            ]);

            DB::commit();

            return $this->successResponse($supplier, 'Supplier updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified supplier
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.delete');

        $supplier = $this->applyTenantScope(Supplier::query())->find($id);

        if (!$supplier) {
            return $this->errorResponse('Supplier not found', 404);
        }

        if (!$supplier->canBeDeleted()) {
            return $this->errorResponse('Supplier cannot be deleted because it has associated products or purchases', 400);
        }

        try {
            DB::beginTransaction();

            $supplierData = $supplier->toArray();
            $supplier->delete();

            $this->logActivity('supplier_deleted', 'suppliers', $id, ['deleted_supplier' => $supplierData]);

            DB::commit();

            return $this->successResponse(null, 'Supplier deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get supplier products
     */
    public function products(int $id, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.view');

        $supplier = $this->applyTenantScope(Supplier::query())->find($id);

        if (!$supplier) {
            return $this->errorResponse('Supplier not found', 404);
        }

        $query = $supplier->products()->with(['category', 'shop']);

        // Apply product filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        return $this->paginatedResponse($query, $request, 'Supplier products retrieved successfully');
    }

    /**
     * Get supplier purchases
     */
    public function purchases(int $id, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.view');

        $supplier = $this->applyTenantScope(Supplier::query())->find($id);

        if (!$supplier) {
            return $this->errorResponse('Supplier not found', 404);
        }

        $query = $supplier->purchases()->with(['warehouse', 'shop']);

        // Apply purchase filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        return $this->paginatedResponse($query, $request, 'Supplier purchases retrieved successfully');
    }

    /**
     * Update supplier rating
     */
    public function updateRating(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.edit');

        $supplier = $this->applyTenantScope(Supplier::query())->find($id);

        if (!$supplier) {
            return $this->errorResponse('Supplier not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|numeric|min:0|max:5'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $oldRating = $supplier->rating;
        $supplier->updateRating($request->rating);

        $this->logActivity('supplier_rating_updated', 'suppliers', $supplier->supplier_id, [
            'old_rating' => $oldRating,
            'new_rating' => $request->rating
        ]);

        return $this->successResponse($supplier, 'Supplier rating updated successfully');
    }

    /**
     * Get supplier statistics
     */
    public function stats(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('suppliers.view');

        $query = $this->applyTenantScope(Supplier::query());

        $stats = [
            'total_suppliers' => $query->count(),
            'active_suppliers' => $query->active()->count(),
            'inactive_suppliers' => $query->inactive()->count(),
            'suppliers_with_products' => $query->withProducts()->count(),
            'high_rated_suppliers' => $query->highRated(4.0)->count(),
            'suppliers_by_country' => $query->selectRaw('country, COUNT(*) as count')
                ->whereNotNull('country')
                ->groupBy('country')
                ->orderBy('count', 'desc')
                ->get(),
            'top_suppliers_by_purchases' => $query->withCount('purchases')
                ->orderBy('purchases_count', 'desc')
                ->limit(5)
                ->get(['supplier_id', 'name', 'purchases_count']),
            'average_rating' => $query->whereNotNull('rating')->avg('rating')
        ];

        return $this->successResponse($stats, 'Supplier statistics retrieved successfully');
    }
}
