<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BrandController extends BaseController
{
    /**
     * Display a listing of brands
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('brands.view');

        $query = $this->applyTenantScope(Brand::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'name',
            'description',
            'contact_email'
        ]);

        // With products filter
        if ($request->has('has_products') && $request->get('has_products')) {
            $query->withProducts();
        }

        // Optimize with eager loading and specific columns
        $query->withCount('products')
            ->with([
                'products:id,product_id,name,sku,status,selling_price,stock_quantity'
            ]);

        return $this->paginatedResponse($query, $request, 'Brands retrieved successfully');
    }

    /**
     * Store a newly created brand
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('brands.create');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url|max:500',
            'website_url' => 'nullable|url|max:500',
            'contact_email' => 'nullable|email|max:150',
            'contact_phone' => 'nullable|string|max:20'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $brandData = $validator->validated();
            $brandData['tenant_id'] = $this->tenant->tenant_id;
            $brandData['is_active'] = true;

            $brand = Brand::create($brandData);

            $this->logActivity('brand_created', 'brands', $brand->brand_id);

            DB::commit();

            return $this->successResponse($brand, 'Brand created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create brand: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified brand
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('brands.view');

        $brand = $this->applyTenantScope(Brand::query())
            ->withCount('products')
            ->with([
                'products:id,product_id,name,sku,status,selling_price,stock_quantity'
            ])
            ->find($id);

        if (!$brand) {
            return $this->errorResponse('Brand not found', 404);
        }

        // Add statistics
        $brand->stats = [
            'products_count' => $brand->getProductCount(),
            'active_products_count' => $brand->getActiveProductCount(),
            'total_stock_value' => $brand->getTotalStockValue(),
            'total_revenue' => $brand->getTotalRevenue()
        ];

        // Get top selling products
        $brand->top_products = $brand->getTopSellingProducts(5);

        return $this->successResponse($brand, 'Brand retrieved successfully');
    }

    /**
     * Update the specified brand
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('brands.edit');

        $brand = $this->applyTenantScope(Brand::query())->find($id);

        if (!$brand) {
            return $this->errorResponse('Brand not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url|max:500',
            'website_url' => 'nullable|url|max:500',
            'contact_email' => 'nullable|email|max:150',
            'contact_phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $brand->toArray();
            $brand->update($validator->validated());

            $this->logActivity('brand_updated', 'brands', $brand->brand_id, [
                'old_values' => $oldValues,
                'new_values' => $brand->toArray()
            ]);

            DB::commit();

            return $this->successResponse($brand, 'Brand updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update brand: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified brand
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('brands.delete');

        $brand = $this->applyTenantScope(Brand::query())->find($id);

        if (!$brand) {
            return $this->errorResponse('Brand not found', 404);
        }

        if (!$brand->canBeDeleted()) {
            return $this->errorResponse('Brand cannot be deleted because it has associated products', 400);
        }

        try {
            DB::beginTransaction();

            $brandData = $brand->toArray();
            $brand->delete();

            $this->logActivity('brand_deleted', 'brands', $id, ['deleted_brand' => $brandData]);

            DB::commit();

            return $this->successResponse(null, 'Brand deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete brand: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get brand products
     */
    public function products(int $id, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('brands.view');

        $brand = $this->applyTenantScope(Brand::query())->find($id);

        if (!$brand) {
            return $this->errorResponse('Brand not found', 404);
        }

        $query = $brand->products()->with([
            'category:id,category_id,name,status',
            'shop:id,shop_id,name,status,address',
            'warehouse:id,warehouse_id,name,status,address',
            'supplier:id,supplier_id,name,contact_person,email'
        ]);

        // Apply product filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        return $this->paginatedResponse($query, $request, 'Brand products retrieved successfully');
    }

    /**
     * Get brand statistics
     */
    public function stats(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('brands.view');

        $query = $this->applyTenantScope(Brand::query());

        $stats = [
            'total_brands' => $query->count(),
            'active_brands' => $query->active()->count(),
            'inactive_brands' => $query->inactive()->count(),
            'brands_with_products' => $query->withProducts()->count(),
            'brands_without_products' => $query->withoutProducts()->count(),
            'top_brands_by_products' => $query->withCount('products')
                ->orderBy('products_count', 'desc')
                ->limit(5)
                ->get(['brand_id', 'name', 'products_count', 'status', 'is_active']),
            'top_brands_by_revenue' => $query->get()
                ->map(function ($brand) {
                    return [
                        'brand_id' => $brand->brand_id,
                        'name' => $brand->name,
                        'total_revenue' => $brand->getTotalRevenue()
                    ];
                })
                ->sortByDesc('total_revenue')
                ->take(5)
                ->values()
        ];

        return $this->successResponse($stats, 'Brand statistics retrieved successfully');
    }
}
