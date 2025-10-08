<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductController extends BaseController
{
    /**
     * Display a listing of products
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.view');

        $query = $this->applyTenantScope(Product::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'name',
            'sku',
            'barcode',
            'description'
        ]);

        // Category filter
        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        // Brand filter
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->get('brand_id'));
        }

        // Supplier filter
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->get('supplier_id'));
        }

        // Shop filter
        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->get('shop_id'));
        }

        // Warehouse filter
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        // Price range filter
        if ($request->has('min_price')) {
            $query->where('selling_price', '>=', $request->get('min_price'));
        }
        if ($request->has('max_price')) {
            $query->where('selling_price', '<=', $request->get('max_price'));
        }

        // Stock filters
        if ($request->has('in_stock') && $request->get('in_stock')) {
            $query->inStock();
        }
        if ($request->has('low_stock') && $request->get('low_stock')) {
            $query->lowStock();
        }
        if ($request->has('needs_reorder') && $request->get('needs_reorder')) {
            $query->needReorder();
        }

        // Featured filter
        if ($request->has('featured') && $request->get('featured')) {
            $query->featured();
        }

        // Digital filter
        if ($request->has('digital')) {
            $query->where('is_digital', (bool) $request->get('digital'));
        }

        // Load relationships efficiently
        $query->with([
            'category:category_id,name,is_active as status',
            'brand:brand_id,name,is_active as status', 
            'supplier:supplier_id,name,is_active as status',
            'shop:shop_id,name,is_active as status',
            'warehouse:warehouse_id,name,is_active as status',
            'variants:variant_id,product_id,name,sku,stock_quantity,is_active as status'
        ]);

        return $this->paginatedResponse($query, $request, 'Products retrieved successfully');
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.create');

        // Check tenant limits
        if (!$this->checkTenantLimits('products')) {
            return $this->errorResponse('Product limit reached for your subscription plan', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:categories,category_id',
            'brand_id' => 'nullable|exists:brands,brand_id',
            'supplier_id' => 'nullable|exists:suppliers,supplier_id',
            'shop_id' => 'nullable|exists:shops,shop_id',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:selling_price',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'min_stock_level' => 'nullable|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:1',
            'reorder_point' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimensions_length' => 'nullable|numeric|min:0',
            'dimensions_width' => 'nullable|numeric|min:0',
            'dimensions_height' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive,discontinued,out_of_stock',
            'is_featured' => 'nullable|boolean',
            'is_digital' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'primary_image_url' => 'nullable|url|max:500',
            'gallery_images' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $productData = $validator->validated();
            $productData['tenant_id'] = $this->tenant->tenant_id;
            $productData['status'] = $productData['status'] ?? 'active';
            $productData['stock_quantity'] = $productData['stock_quantity'] ?? 0;

            // Validate related resources belong to tenant
            $this->validateResourcesBelongToTenant($productData);

            // Generate SKU if not provided
            if (empty($productData['sku'])) {
                $productData['sku'] = $this->generateUniqueCode('PRD', Product::class, 'sku');
            }

            $product = Product::create($productData);

            // Create initial inventory movement if stock quantity > 0
            if ($product->stock_quantity > 0) {
                InventoryMovement::recordAdjustment(
                    $this->tenant->tenant_id,
                    $product->product_id,
                    null,
                    $product->stock_quantity,
                    'Initial stock',
                    $product->warehouse_id,
                    $product->shop_id
                );
            }

            $this->logActivity('product_created', 'products', $product->product_id);

            DB::commit();

            $product->load(['category', 'brand', 'supplier', 'shop', 'warehouse']);

            return $this->successResponse($product, 'Product created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified product
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.view');

        $product = $this->applyTenantScope(Product::query())
            ->with([
                'category:category_id,name,is_active as status',
                'brand:brand_id,name,is_active as status',
                'supplier:supplier_id,name,is_active as status,contact_person,email,phone',
                'shop:shop_id,name,is_active as status,address',
                'warehouse:warehouse_id,name,is_active as status,address',
                'variants:variant_id,product_id,name,sku,stock_quantity,is_active as status'
            ])
            ->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        // Add additional stats efficiently
        $product->recent_movements = $product->inventoryMovements()
            ->with('createdBy:id,user_id,name,email')
            ->latest()
            ->limit(10)
            ->get();

        $product->stats = [
            'total_movements' => $product->inventoryMovements()->count(),
            'stock_status' => $product->getStockStatus(),
            'profit_margin' => $product->getProfitMargin(),
            'variants_count' => $product->variants()->count()
        ];

        return $this->successResponse($product, 'Product retrieved successfully');
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.edit');

        $product = $this->applyTenantScope(Product::query())->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $id . ',product_id',
            'barcode' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:100',
            'category_id' => 'sometimes|exists:categories,category_id',
            'brand_id' => 'nullable|exists:brands,brand_id',
            'supplier_id' => 'nullable|exists:suppliers,supplier_id',
            'shop_id' => 'sometimes|exists:shops,shop_id',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',
            'cost_price' => 'sometimes|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'min_stock_level' => 'nullable|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:1',
            'reorder_point' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimensions_length' => 'nullable|numeric|min:0',
            'dimensions_width' => 'nullable|numeric|min:0',
            'dimensions_height' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'status' => 'sometimes|in:active,inactive,discontinued,out_of_stock',
            'is_featured' => 'sometimes|boolean',
            'is_digital' => 'sometimes|boolean',
            'tags' => 'nullable|array',
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'primary_image_url' => 'nullable|url|max:500',
            'gallery_images' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $product->toArray();
            $productData = $validator->validated();

            // Validate related resources belong to tenant
            $this->validateResourcesBelongToTenant($productData);

            // Validate discount price if provided
            if (isset($productData['discount_price']) && isset($productData['selling_price'])) {
                if ($productData['discount_price'] >= $productData['selling_price']) {
                    return $this->errorResponse('Discount price must be less than selling price', 400);
                }
            } elseif (isset($productData['discount_price']) && $productData['discount_price'] >= $product->selling_price) {
                return $this->errorResponse('Discount price must be less than selling price', 400);
            }

            $product->update($productData);

            $this->logActivity('product_updated', 'products', $product->product_id, [
                'old_values' => $oldValues,
                'new_values' => $product->toArray()
            ]);

            DB::commit();

            $product->load(['category', 'brand', 'supplier', 'shop', 'warehouse']);

            return $this->successResponse($product, 'Product updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified product
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.delete');

        $product = $this->applyTenantScope(Product::query())->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        // Check if product has pending orders
        $pendingOrders = $product->orderItems()
            ->whereHas('order', function ($query) {
                $query->whereIn('status', ['pending', 'confirmed', 'processing']);
            })->count();

        if ($pendingOrders > 0) {
            return $this->errorResponse('Cannot delete product with pending orders', 400);
        }

        try {
            DB::beginTransaction();

            $productData = $product->toArray();
            
            // Delete variants first
            $product->variants()->delete();
            
            $product->delete();

            $this->logActivity('product_deleted', 'products', $id, ['deleted_product' => $productData]);

            DB::commit();

            return $this->successResponse(null, 'Product deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete product: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update product stock
     */
    public function updateStock(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.manage_stock');

        $product = $this->applyTenantScope(Product::query())->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:255',
            'adjustment_type' => 'required|in:set,add,subtract'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldQuantity = $product->stock_quantity;
            $newQuantity = match($request->adjustment_type) {
                'set' => $request->quantity,
                'add' => $oldQuantity + $request->quantity,
                'subtract' => max(0, $oldQuantity - $request->quantity)
            };

            $product->updateStock($newQuantity, $request->reason);

            $this->logActivity('stock_updated', 'products', $product->product_id, [
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'adjustment_type' => $request->adjustment_type,
                'reason' => $request->reason
            ]);

            DB::commit();

            return $this->successResponse([
                'product' => $product,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity
            ], 'Stock updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get product variants
     */
    public function variants(int $id): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.view');

        $product = $this->applyTenantScope(Product::query())->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        $variants = $product->variants()->active()->get();

        return $this->successResponse($variants, 'Product variants retrieved successfully');
    }

    /**
     * Get low stock products
     */
    public function lowStock(Request $request): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.view');

        $query = $this->applyTenantScope(Product::query())
            ->lowStock()
            ->with(['category', 'shop', 'warehouse']);

        return $this->paginatedResponse($query, $request, 'Low stock products retrieved successfully');
    }

    /**
     * Get products needing reorder
     */
    public function needsReorder(Request $request): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.view');

        $query = $this->applyTenantScope(Product::query())
            ->needReorder()
            ->with(['category', 'supplier', 'shop', 'warehouse']);

        return $this->paginatedResponse($query, $request, 'Products needing reorder retrieved successfully');
    }

    /**
     * Get product statistics
     */
    public function stats(): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.view');

        $query = $this->applyTenantScope(Product::query());

        $stats = [
            'total_products' => $query->count(),
            'active_products' => $query->active()->count(),
            'inactive_products' => $query->inactive()->count(),
            'featured_products' => $query->featured()->count(),
            'digital_products' => $query->digital()->count(),
            'physical_products' => $query->physical()->count(),
            'low_stock_products' => $query->lowStock()->count(),
            'out_of_stock_products' => $query->outOfStock()->count(),
            'needs_reorder_products' => $query->needReorder()->count(),
            'total_inventory_value' => $query->sum(DB::raw('stock_quantity * cost_price')),
            'total_retail_value' => $query->sum(DB::raw('stock_quantity * selling_price')),
            'top_categories' => Product::query()
                ->where('products.tenant_id', $this->tenant->tenant_id)
                ->select('categories.name', DB::raw('COUNT(*) as product_count'))
                ->join('categories', 'products.category_id', '=', 'categories.category_id')
                ->groupBy('categories.category_id', 'categories.name')
                ->orderBy('product_count', 'desc')
                ->limit(5)
                ->get()
        ];

        return $this->successResponse($stats, 'Product statistics retrieved successfully');
    }

    /**
     * Bulk operations
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $this->requireTenant();
        // Removed permission check - only requires token authentication
        // $this->requirePermission('products.bulk_actions');

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete,update_category,update_status,feature,unfeature',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,product_id',
            'category_id' => 'required_if:action,update_category|exists:categories,category_id',
            'status' => 'required_if:action,update_status|in:active,inactive,discontinued'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $products = $this->applyTenantScope(Product::query())
            ->whereIn('product_id', $request->product_ids)
            ->get();

        if ($products->count() !== count($request->product_ids)) {
            return $this->errorResponse('Some products not found or do not belong to tenant', 400);
        }

        try {
            DB::beginTransaction();

            $results = [];
            foreach ($products as $product) {
                switch ($request->action) {
                    case 'activate':
                        $product->activate();
                        $results[] = "Product {$product->name} activated";
                        break;
                    case 'deactivate':
                        $product->deactivate();
                        $results[] = "Product {$product->name} deactivated";
                        break;
                    case 'delete':
                        $product->delete();
                        $results[] = "Product {$product->name} deleted";
                        break;
                    case 'update_category':
                        $product->update(['category_id' => $request->category_id]);
                        $results[] = "Product {$product->name} category updated";
                        break;
                    case 'update_status':
                        $product->update(['status' => $request->status]);
                        $results[] = "Product {$product->name} status updated";
                        break;
                    case 'feature':
                        $product->setFeatured(true);
                        $results[] = "Product {$product->name} featured";
                        break;
                    case 'unfeature':
                        $product->setFeatured(false);
                        $results[] = "Product {$product->name} unfeatured";
                        break;
                }
            }

            $this->logActivity('bulk_product_action', 'products', null, [
                'action' => $request->action,
                'product_ids' => $request->product_ids,
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
     * Validate that related resources belong to tenant
     */
    private function validateResourcesBelongToTenant(array &$data): void
    {
        if (!empty($data['category_id'])) {
            $category = $this->tenant->categories()->find($data['category_id']);
            if (!$category) {
                // Gracefully ignore invalid category by clearing it
                $data['category_id'] = null;
            }
        }

        if (!empty($data['brand_id'])) {
            $brand = $this->tenant->brands()->find($data['brand_id']);
            if (!$brand) {
                // Gracefully ignore invalid brand by clearing it
                $data['brand_id'] = null;
            }
        }

        if (!empty($data['supplier_id'])) {
            $supplier = $this->tenant->suppliers()->find($data['supplier_id']);
            if (!$supplier) {
                // Gracefully ignore invalid supplier by clearing it
                $data['supplier_id'] = null;
            }
        }

        if (!empty($data['shop_id'])) {
            $shop = $this->tenant->shops()->find($data['shop_id']);
            if (!$shop) {
                // Gracefully ignore invalid shop by clearing it
                $data['shop_id'] = null;
            }
        }

        if (!empty($data['warehouse_id'])) {
            $warehouse = $this->tenant->warehouses()->find($data['warehouse_id']);
            if (!$warehouse) {
                // Gracefully ignore invalid warehouse by clearing it
                $data['warehouse_id'] = null;
            }
        }
    }
}
