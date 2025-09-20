<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WarehouseController extends BaseController
{
    /**
     * Display a listing of warehouses
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.view');

        $query = $this->applyTenantScope(Warehouse::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'name',
            'code',
            'address',
            'city'
        ]);

        // Manager filter
        if ($request->has('manager_id')) {
            $query->where('manager_id', $request->get('manager_id'));
        }

        // Warehouse type filter
        if ($request->has('warehouse_type')) {
            $query->where('warehouse_type', $request->get('warehouse_type'));
        }

        // Temperature controlled filter
        if ($request->has('temperature_controlled')) {
            $query->where('temperature_controlled', (bool) $request->get('temperature_controlled'));
        }

        // Capacity range filter
        if ($request->has('min_capacity')) {
            $query->where('capacity', '>=', $request->get('min_capacity'));
        }
        if ($request->has('max_capacity')) {
            $query->where('capacity', '<=', $request->get('max_capacity'));
        }

        // Load relationships
        $query->with(['manager', 'products', 'shops'])
             ->withCount(['products', 'shops']);

        return $this->paginatedResponse($query, $request, 'Warehouses retrieved successfully');
    }

    /**
     * Store a newly created warehouse
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.create');

        // Check tenant limits
        if (!$this->checkTenantLimits('warehouses')) {
            return $this->errorResponse('Warehouse limit reached for your subscription plan', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20|unique:warehouses,code',
            'address' => 'required|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'manager_id' => 'required|exists:users,user_id',
            'capacity' => 'nullable|numeric|min:0',
            'warehouse_type' => 'nullable|in:main,distribution,storage,cold_storage',
            'operating_hours' => 'nullable|string|max:100',
            'temperature_controlled' => 'nullable|boolean',
            'security_level' => 'nullable|in:basic,standard,high'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $warehouseData = $validator->validated();
            $warehouseData['tenant_id'] = $this->tenant->tenant_id;
            $warehouseData['is_active'] = true;
            $warehouseData['current_utilization'] = 0.00;

            // Validate manager belongs to tenant
            $manager = $this->applyTenantScope(\App\Models\User::query())->find($warehouseData['manager_id']);
            if (!$manager) {
                return $this->errorResponse('Manager not found or does not belong to tenant', 400);
            }

            // Generate unique code if not provided
            if (empty($warehouseData['code'])) {
                $warehouseData['code'] = $this->generateUniqueCode('WH', Warehouse::class, 'code');
            }

            $warehouse = Warehouse::create($warehouseData);

            $this->logActivity('warehouse_created', 'warehouses', $warehouse->warehouse_id);

            DB::commit();

            $warehouse->load(['manager', 'products', 'shops']);

            return $this->successResponse($warehouse, 'Warehouse created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create warehouse: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified warehouse
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.view');

        $warehouse = $this->applyTenantScope(Warehouse::query())
            ->with(['manager', 'products', 'shops'])
            ->withCount(['products', 'shops'])
            ->find($id);

        if (!$warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        // Add statistics
        $warehouse->stats = [
            'products_count' => $warehouse->getProductCount(),
            'total_stock_value' => $warehouse->getTotalStockValue(),
            'utilization_percentage' => $warehouse->getUtilizationPercentage(),
            'available_capacity' => $warehouse->getAvailableCapacity(),
            'low_stock_products' => $warehouse->getProductsLowStock()->count(),
            'shops_served' => $warehouse->shops()->count()
        ];

        // Get recent inventory movements
        $warehouse->recent_movements = $warehouse->inventoryMovements()
            ->with(['product', 'createdBy'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->successResponse($warehouse, 'Warehouse retrieved successfully');
    }

    /**
     * Update the specified warehouse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.edit');

        $warehouse = $this->applyTenantScope(Warehouse::query())->find($id);

        if (!$warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'code' => 'sometimes|string|max:20|unique:warehouses,code,' . $id . ',warehouse_id',
            'address' => 'sometimes|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'manager_id' => 'sometimes|exists:users,user_id',
            'capacity' => 'nullable|numeric|min:0',
            'warehouse_type' => 'nullable|in:main,distribution,storage,cold_storage',
            'is_active' => 'sometimes|boolean',
            'operating_hours' => 'nullable|string|max:100',
            'temperature_controlled' => 'nullable|boolean',
            'security_level' => 'nullable|in:basic,standard,high'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $warehouse->toArray();
            $warehouseData = $validator->validated();

            // Validate manager belongs to tenant if provided
            if (isset($warehouseData['manager_id'])) {
                $manager = $this->applyTenantScope(\App\Models\User::query())->find($warehouseData['manager_id']);
                if (!$manager) {
                    return $this->errorResponse('Manager not found or does not belong to tenant', 400);
                }
            }

            $warehouse->update($warehouseData);

            // Update utilization if capacity changed
            if (isset($warehouseData['capacity'])) {
                $warehouse->updateUtilization();
            }

            $this->logActivity('warehouse_updated', 'warehouses', $warehouse->warehouse_id, [
                'old_values' => $oldValues,
                'new_values' => $warehouse->toArray()
            ]);

            DB::commit();

            $warehouse->load(['manager', 'products', 'shops']);

            return $this->successResponse($warehouse, 'Warehouse updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update warehouse: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified warehouse
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.delete');

        $warehouse = $this->applyTenantScope(Warehouse::query())->find($id);

        if (!$warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        // Check if warehouse has products
        if ($warehouse->products()->count() > 0) {
            return $this->errorResponse('Cannot delete warehouse with existing products', 400);
        }

        // Check if warehouse serves shops
        if ($warehouse->shops()->count() > 0) {
            return $this->errorResponse('Cannot delete warehouse that serves shops', 400);
        }

        try {
            DB::beginTransaction();

            $warehouseData = $warehouse->toArray();
            $warehouse->delete();

            $this->logActivity('warehouse_deleted', 'warehouses', $id, ['deleted_warehouse' => $warehouseData]);

            DB::commit();

            return $this->successResponse(null, 'Warehouse deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete warehouse: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get warehouse products
     */
    public function products(int $id, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.view');

        $warehouse = $this->applyTenantScope(Warehouse::query())->find($id);

        if (!$warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        $query = $warehouse->products()->with(['category', 'brand', 'supplier']);

        // Apply product filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('low_stock') && $request->get('low_stock')) {
            $query->lowStock();
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        return $this->paginatedResponse($query, $request, 'Warehouse products retrieved successfully');
    }

    /**
     * Get warehouse inventory movements
     */
    public function movements(int $id, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.view');

        $warehouse = $this->applyTenantScope(Warehouse::query())->find($id);

        if (!$warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        $query = $warehouse->inventoryMovements()
            ->with(['product', 'variant', 'createdBy']);

        // Apply movement filters
        if ($request->has('movement_type')) {
            $query->where('movement_type', $request->get('movement_type'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        return $this->paginatedResponse($query, $request, 'Warehouse movements retrieved successfully');
    }

    /**
     * Update warehouse utilization
     */
    public function updateUtilization(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.edit');

        $warehouse = $this->applyTenantScope(Warehouse::query())->find($id);

        if (!$warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        $oldUtilization = $warehouse->current_utilization;
        $warehouse->updateUtilization();

        $this->logActivity('warehouse_utilization_updated', 'warehouses', $warehouse->warehouse_id, [
            'old_utilization' => $oldUtilization,
            'new_utilization' => $warehouse->current_utilization
        ]);

        return $this->successResponse([
            'warehouse' => $warehouse,
            'utilization_changed' => $oldUtilization !== $warehouse->current_utilization
        ], 'Warehouse utilization updated successfully');
    }

    /**
     * Get warehouse capacity analysis
     */
    public function capacityAnalysis(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.view');

        $warehouse = $this->applyTenantScope(Warehouse::query())->find($id);

        if (!$warehouse) {
            return $this->errorResponse('Warehouse not found', 404);
        }

        $analysis = [
            'total_capacity' => $warehouse->capacity,
            'current_utilization_percentage' => $warehouse->getUtilizationPercentage(),
            'available_capacity' => $warehouse->getAvailableCapacity(),
            'status' => $warehouse->getUtilizationPercentage() > 90 ? 'critical' : 
                       ($warehouse->getUtilizationPercentage() > 80 ? 'high' : 
                       ($warehouse->getUtilizationPercentage() > 60 ? 'medium' : 'low')),
            'recommendations' => $this->getCapacityRecommendations($warehouse),
            'product_breakdown' => $warehouse->products()
                ->select(['name', 'stock_quantity'])
                ->selectRaw('(stock_quantity * dimensions_length * dimensions_width * dimensions_height / 1000000) as volume_used')
                ->whereNotNull('dimensions_length')
                ->whereNotNull('dimensions_width')
                ->whereNotNull('dimensions_height')
                ->orderBy('volume_used', 'desc')
                ->limit(10)
                ->get()
        ];

        return $this->successResponse($analysis, 'Warehouse capacity analysis retrieved successfully');
    }

    /**
     * Get warehouse statistics
     */
    public function stats(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.view');

        $query = $this->applyTenantScope(Warehouse::query());

        $stats = [
            'total_warehouses' => $query->count(),
            'active_warehouses' => $query->active()->count(),
            'inactive_warehouses' => $query->inactive()->count(),
            'temperature_controlled' => $query->temperatureControlled()->count(),
            'average_utilization' => $query->whereNotNull('capacity')->avg('current_utilization'),
            'total_capacity' => $query->sum('capacity'),
            'warehouses_by_type' => $query->selectRaw('warehouse_type, COUNT(*) as count')
                ->whereNotNull('warehouse_type')
                ->groupBy('warehouse_type')
                ->get(),
            'high_utilization_warehouses' => $query->where('current_utilization', '>', 80)->count(),
            'products_stored' => Product::whereIn('warehouse_id', 
                $query->pluck('warehouse_id')
            )->sum('stock_quantity')
        ];

        return $this->successResponse($stats, 'Warehouse statistics retrieved successfully');
    }

    /**
     * Transfer products between warehouses
     */
    public function transferProducts(Request $request, int $fromWarehouseId, int $toWarehouseId): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('warehouses.transfer');

        $fromWarehouse = $this->applyTenantScope(Warehouse::query())->find($fromWarehouseId);
        $toWarehouse = $this->applyTenantScope(Warehouse::query())->find($toWarehouseId);

        if (!$fromWarehouse || !$toWarehouse) {
            return $this->errorResponse('Source or destination warehouse not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'transfers' => 'required|array|min:1',
            'transfers.*.product_id' => 'required|exists:products,product_id',
            'transfers.*.variant_id' => 'nullable|exists:product_variants,variant_id',
            'transfers.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $transferredItems = [];

            foreach ($request->transfers as $transfer) {
                $product = $this->applyTenantScope(Product::query())->find($transfer['product_id']);
                
                if (!$product || $product->warehouse_id !== $fromWarehouseId) {
                    throw new \Exception("Product {$transfer['product_id']} not found in source warehouse");
                }

                $variant = null;
                if (!empty($transfer['variant_id'])) {
                    $variant = $product->variants()->find($transfer['variant_id']);
                    if (!$variant) {
                        throw new \Exception("Product variant {$transfer['variant_id']} not found");
                    }
                }

                // Check stock availability
                $availableStock = $variant ? $variant->stock_quantity : $product->stock_quantity;
                if ($availableStock < $transfer['quantity']) {
                    throw new \Exception("Insufficient stock for product {$product->name}");
                }

                // Check if destination warehouse can accommodate
                if (!$toWarehouse->canStoreProduct($product, $transfer['quantity'])) {
                    throw new \Exception("Destination warehouse cannot accommodate product {$product->name}");
                }

                // Perform transfer
                if ($variant) {
                    $variant->reduceStock($transfer['quantity'], 'transfer');
                } else {
                    $product->reduceStock($transfer['quantity'], 'transfer');
                }

                // Create inventory movements
                \App\Models\InventoryMovement::create([
                    'tenant_id' => $this->tenant->tenant_id,
                    'product_id' => $product->product_id,
                    'variant_id' => $transfer['variant_id'] ?? null,
                    'movement_type' => 'transfer',
                    'quantity' => -$transfer['quantity'],
                    'warehouse_id' => $fromWarehouseId,
                    'notes' => $request->notes ?? "Transfer to {$toWarehouse->name}",
                    'created_by' => auth()->id()
                ]);

                \App\Models\InventoryMovement::create([
                    'tenant_id' => $this->tenant->tenant_id,
                    'product_id' => $product->product_id,
                    'variant_id' => $transfer['variant_id'] ?? null,
                    'movement_type' => 'transfer',
                    'quantity' => $transfer['quantity'],
                    'warehouse_id' => $toWarehouseId,
                    'notes' => $request->notes ?? "Transfer from {$fromWarehouse->name}",
                    'created_by' => auth()->id()
                ]);

                $transferredItems[] = [
                    'product' => $product->name,
                    'quantity' => $transfer['quantity'],
                    'variant' => $variant?->variant_name
                ];
            }

            // Update warehouse utilizations
            $fromWarehouse->updateUtilization();
            $toWarehouse->updateUtilization();

            $this->logActivity('warehouse_transfer', 'warehouses', null, [
                'from_warehouse' => $fromWarehouse->name,
                'to_warehouse' => $toWarehouse->name,
                'items' => $transferredItems,
                'notes' => $request->notes
            ]);

            DB::commit();

            return $this->successResponse([
                'transferred_items' => $transferredItems,
                'from_warehouse' => $fromWarehouse,
                'to_warehouse' => $toWarehouse
            ], 'Products transferred successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Transfer failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get capacity recommendations
     */
    private function getCapacityRecommendations(Warehouse $warehouse): array
    {
        $recommendations = [];
        $utilization = $warehouse->getUtilizationPercentage();

        if ($utilization > 90) {
            $recommendations[] = 'Critical: Warehouse is at maximum capacity. Consider expanding or redistributing inventory.';
        } elseif ($utilization > 80) {
            $recommendations[] = 'Warning: Warehouse is nearing capacity. Plan for expansion or inventory optimization.';
        } elseif ($utilization < 30) {
            $recommendations[] = 'Opportunity: Warehouse has significant unused capacity. Consider consolidating inventory.';
        }

        $lowStockCount = $warehouse->getProductsLowStock()->count();
        if ($lowStockCount > 0) {
            $recommendations[] = "Restock needed: {$lowStockCount} products are running low on stock.";
        }

        return $recommendations;
    }
}
