<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CategoryController extends BaseController
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.view');

        $query = $this->applyTenantScope(Category::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'name',
            'description',
            'category_code'
        ]);

        // Parent filter
        if ($request->has('parent_id')) {
            if ($request->get('parent_id') === 'null') {
                $query->whereNull('parent_category_id');
            } else {
                $query->where('parent_category_id', $request->get('parent_id'));
            }
        }

        // Include children if requested
        if ($request->has('include_children') && $request->get('include_children')) {
            $query->with(['subcategories:id,category_id,parent_category_id,name,status,is_active']);
        }

        // Include parent if requested
        if ($request->has('include_parent') && $request->get('include_parent')) {
            $query->with(['parentCategory:id,category_id,name,status,is_active']);
        }

        // Always include product count for performance
        $query->withCount(['products', 'subcategories']);

        return $this->paginatedResponse($query, $request, 'Categories retrieved successfully');
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.create');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'parent_category_id' => 'nullable|exists:categories,category_id',
            'category_code' => 'required|string|max:20|unique:categories,category_code',
            'image_url' => 'nullable|url|max:500',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $categoryData = $validator->validated();
            $categoryData['tenant_id'] = $this->tenant->tenant_id;
            $categoryData['is_active'] = true;

            // Validate parent category belongs to tenant if provided
            if (!empty($categoryData['parent_category_id'])) {
                $parentCategory = $this->tenant->categories()->find($categoryData['parent_category_id']);
                if (!$parentCategory) {
                    return $this->errorResponse('Parent category not found or does not belong to tenant', 400);
                }

                // Check for circular reference
                if ($this->wouldCreateCircularReference($categoryData['parent_category_id'], null)) {
                    return $this->errorResponse('Cannot create circular reference in category hierarchy', 400);
                }
            }

            $category = Category::create($categoryData);

            $this->logActivity('category_created', 'categories', $category->category_id);

            DB::commit();

            $category->load(['parentCategory', 'subcategories']);

            return $this->successResponse($category, 'Category created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified category
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.view');

        $category = $this->applyTenantScope(Category::query())
            ->with([
                'parentCategory:id,category_id,name,status,is_active',
                'subcategories:id,category_id,parent_category_id,name,status,is_active',
                'products:id,product_id,name,sku,status,selling_price,stock_quantity'
            ])
            ->withCount(['products', 'subcategories'])
            ->find($id);

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        // Add additional statistics
        $category->stats = [
            'products_count' => $category->getProductCount(),
            'total_products_count' => $category->getTotalProductCount(),
            'active_products_count' => $category->getActiveProductCount(),
            'depth' => $category->getDepth(),
            'path' => $category->getPath(),
            'has_children' => $category->hasChildren(),
            'can_be_deleted' => $category->canBeDeleted()
        ];

        return $this->successResponse($category, 'Category retrieved successfully');
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.edit');

        $category = $this->applyTenantScope(Category::query())->find($id);

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'parent_category_id' => 'nullable|exists:categories,category_id',
            'category_code' => 'sometimes|string|max:20|unique:categories,category_code,' . $id . ',category_id',
            'image_url' => 'nullable|url|max:500',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $category->toArray();
            $categoryData = $validator->validated();

            // Validate parent category belongs to tenant if provided
            if (isset($categoryData['parent_category_id']) && $categoryData['parent_category_id']) {
                $parentCategory = $this->tenant->categories()->find($categoryData['parent_category_id']);
                if (!$parentCategory) {
                    return $this->errorResponse('Parent category not found or does not belong to tenant', 400);
                }

                // Check for circular reference
                if ($this->wouldCreateCircularReference($categoryData['parent_category_id'], $id)) {
                    return $this->errorResponse('Cannot create circular reference in category hierarchy', 400);
                }
            }

            $category->update($categoryData);

            $this->logActivity('category_updated', 'categories', $category->category_id, [
                'old_values' => $oldValues,
                'new_values' => $category->toArray()
            ]);

            DB::commit();

            $category->load(['parentCategory', 'subcategories']);

            return $this->successResponse($category, 'Category updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified category
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.delete');

        $category = $this->applyTenantScope(Category::query())->find($id);

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        if (!$category->canBeDeleted()) {
            return $this->errorResponse('Category cannot be deleted because it has products or subcategories', 400);
        }

        try {
            DB::beginTransaction();

            $categoryData = $category->toArray();
            $category->delete();

            $this->logActivity('category_deleted', 'categories', $id, ['deleted_category' => $categoryData]);

            DB::commit();

            return $this->successResponse(null, 'Category deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get category hierarchy
     */
    public function hierarchy(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.view');

        $hierarchy = Category::getHierarchy($this->tenant->tenant_id);

        return $this->successResponse($hierarchy, 'Category hierarchy retrieved successfully');
    }

    /**
     * Get root categories (parents only)
     */
    public function roots(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.view');

        $rootCategories = Category::getRootCategories($this->tenant->tenant_id);

        return $this->successResponse($rootCategories, 'Root categories retrieved successfully');
    }

    /**
     * Get category children
     */
    public function children(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.view');

        $category = $this->applyTenantScope(Category::query())->find($id);

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $children = $category->subcategories()
            ->active()
            ->ordered()
            ->withCount(['products', 'subcategories'])
            ->get(['id', 'category_id', 'parent_category_id', 'name', 'status', 'is_active', 'sort_order']);

        return $this->successResponse($children, 'Category children retrieved successfully');
    }

    /**
     * Activate category
     */
    public function activate(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.edit');

        $category = $this->applyTenantScope(Category::query())->find($id);

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $category->activate();
        $this->logActivity('category_activated', 'categories', $category->category_id);

        return $this->successResponse($category, 'Category activated successfully');
    }

    /**
     * Deactivate category
     */
    public function deactivate(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.edit');

        $category = $this->applyTenantScope(Category::query())->find($id);

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $category->deactivate();
        $this->logActivity('category_deactivated', 'categories', $category->category_id);

        return $this->successResponse($category, 'Category deactivated successfully');
    }

    /**
     * Get category statistics
     */
    public function stats(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.view');

        $query = $this->applyTenantScope(Category::query());

        $stats = [
            'total_categories' => $query->count(),
            'active_categories' => $query->active()->count(),
            'inactive_categories' => $query->inactive()->count(),
            'root_categories' => $query->parent()->count(),
            'child_categories' => $query->children()->count(),
            'categories_with_products' => $query->has('products')->count(),
            'empty_categories' => $query->doesntHave('products')->count(),
            'most_used_categories' => $query->withCount('products')
                ->orderBy('products_count', 'desc')
                ->limit(5)
                ->get(['category_id', 'name', 'products_count', 'status', 'is_active'])
        ];

        return $this->successResponse($stats, 'Category statistics retrieved successfully');
    }

    /**
     * Bulk operations
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('categories.bulk_actions');

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,category_id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $categories = $this->applyTenantScope(Category::query())
            ->whereIn('category_id', $request->category_ids)
            ->get();

        if ($categories->count() !== count($request->category_ids)) {
            return $this->errorResponse('Some categories not found or do not belong to tenant', 400);
        }

        try {
            DB::beginTransaction();

            $results = [];
            foreach ($categories as $category) {
                switch ($request->action) {
                    case 'activate':
                        $category->activate();
                        $results[] = "Category {$category->name} activated";
                        break;
                    case 'deactivate':
                        $category->deactivate();
                        $results[] = "Category {$category->name} deactivated";
                        break;
                    case 'delete':
                        if ($category->canBeDeleted()) {
                            $category->delete();
                            $results[] = "Category {$category->name} deleted";
                        } else {
                            $results[] = "Category {$category->name} skipped (has products or subcategories)";
                        }
                        break;
                }
            }

            $this->logActivity('bulk_category_action', 'categories', null, [
                'action' => $request->action,
                'category_ids' => $request->category_ids,
                'results' => $results
            ]);

            DB::commit();

            return $this->successResponse($results, 'Bulk action completed successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk action failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check if changing parent would create circular reference
     */
    private function wouldCreateCircularReference(int $parentId, ?int $categoryId): bool
    {
        if (!$categoryId) {
            return false; // New category, no circular reference possible
        }

        $category = $this->applyTenantScope(Category::query())->find($categoryId);
        if (!$category) {
            return false;
        }

        // Check if the proposed parent is a descendant of this category
        $allChildren = $category->getAllChildren();
        return $allChildren->contains('category_id', $parentId);
    }
}
