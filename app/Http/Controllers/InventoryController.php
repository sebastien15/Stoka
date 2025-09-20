<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryController extends BaseController
{
    /**
     * Display inventory movements
     */
    public function movements(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('inventory.view');

        $query = $this->applyTenantScope(InventoryMovement::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, ['notes']);

        // Product filter
        if ($request->has('product_id')) {
            $query->where('product_id', $request->get('product_id'));
        }

        // Variant filter
        if ($request->has('variant_id')) {
            $query->where('variant_id', $request->get('variant_id'));
        }

        // Movement type filter
        if ($request->has('movement_type')) {
            $query->where('movement_type', $request->get('movement_type'));
        }

        // Warehouse filter
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        // Shop filter
        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->get('shop_id'));
        }

        // Direction filter (inbound/outbound)
        if ($request->has('direction')) {
            if ($request->get('direction') === 'inbound') {
                $query->inbound();
            } elseif ($request->get('direction') === 'outbound') {
                $query->outbound();
            }
        }

        // Load relationships
        $query->with(['product', 'variant', 'warehouse', 'shop', 'createdBy']);

        return $this->paginatedResponse($query, $request, 'Inventory movements retrieved successfully');
    }

    /**
     * Create inventory adjustment
     */
    public function createAdjustment(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('inventory.adjust');

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,product_id',
            'variant_id' => 'nullable|exists:product_variants,variant_id',
            'adjustment_type' => 'required|in:increase,decrease,set',
            'quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:255',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',
            'shop_id' => 'nullable|exists:shops,shop_id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            // Validate product belongs to tenant
            $product = $this->applyTenantScope(Product::query())->find($request->product_id);
            if (!$product) {
                return $this->errorResponse('Product not found or does not belong to tenant', 400);
            }

            // Validate variant if provided
            $variant = null;
            if ($request->variant_id) {
                $variant = $product->variants()->find($request->variant_id);
                if (!$variant) {
                    return $this->errorResponse('Product variant not found', 400);
                }
            }

            // Validate warehouse if provided
            if ($request->warehouse_id) {
                $warehouse = $this->tenant->warehouses()->find($request->warehouse_id);
                if (!$warehouse) {
                    return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);
                }
            }

            // Validate shop if provided
            if ($request->shop_id) {
                $shop = $this->tenant->shops()->find($request->shop_id);
                if (!$shop) {
                    return $this->errorResponse('Shop not found or does not belong to tenant', 400);
                }
            }

            // Calculate adjustment quantity
            $currentStock = $variant ? $variant->stock_quantity : $product->stock_quantity;
            $adjustmentQuantity = match($request->adjustment_type) {
                'increase' => $request->quantity,
                'decrease' => -$request->quantity,
                'set' => $request->quantity - $currentStock
            };

            // Validate decrease doesn't go below zero
            if ($request->adjustment_type === 'decrease' && $currentStock < $request->quantity) {
                return $this->errorResponse('Cannot decrease stock below zero', 400);
            }

            // Create inventory movement
            $movement = InventoryMovement::recordAdjustment(
                $this->tenant->tenant_id,
                $product->product_id,
                $variant?->variant_id,
                $adjustmentQuantity,
                $request->reason,
                $request->warehouse_id,
                $request->shop_id,
                auth()->id()
            );

            // Update product/variant stock
            $newStock = match($request->adjustment_type) {
                'increase' => $currentStock + $request->quantity,
                'decrease' => $currentStock - $request->quantity,
                'set' => $request->quantity
            };

            if ($variant) {
                $variant->updateStock($newStock, $request->reason);
            } else {
                $product->updateStock($newStock, $request->reason);
            }

            $this->logActivity('inventory_adjustment', 'inventory_movements', $movement->movement_id, [
                'product' => $product->name,
                'variant' => $variant?->variant_name,
                'adjustment_type' => $request->adjustment_type,
                'quantity' => $request->quantity,
                'old_stock' => $currentStock,
                'new_stock' => $newStock,
                'reason' => $request->reason
            ]);

            DB::commit();

            $movement->load(['product', 'variant', 'warehouse', 'shop', 'createdBy']);

            return $this->successResponse([
                'movement' => $movement,
                'old_stock' => $currentStock,
                'new_stock' => $newStock
            ], 'Inventory adjustment created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create adjustment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get inventory overview
     */
    public function overview(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('inventory.view');

        $overview = [
            'total_products' => $this->applyTenantScope(Product::query())->count(),
            'total_variants' => $this->applyTenantScope(ProductVariant::query())->count(),
            'total_stock_items' => $this->applyTenantScope(Product::query())->sum('stock_quantity'),
            'low_stock_products' => $this->applyTenantScope(Product::query())->lowStock()->count(),
            'out_of_stock_products' => $this->applyTenantScope(Product::query())->outOfStock()->count(),
            'needs_reorder_products' => $this->applyTenantScope(Product::query())->needReorder()->count(),
            'total_inventory_value' => $this->applyTenantScope(Product::query())
                ->sum(DB::raw('stock_quantity * cost_price')),
            'total_retail_value' => $this->applyTenantScope(Product::query())
                ->sum(DB::raw('stock_quantity * selling_price')),
            'recent_movements_count' => $this->applyTenantScope(InventoryMovement::query())
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'movement_types_today' => $this->applyTenantScope(InventoryMovement::query())
                ->whereDate('created_at', now())
                ->selectRaw('movement_type, COUNT(*) as count, SUM(ABS(quantity)) as total_quantity')
                ->groupBy('movement_type')
                ->get()
        ];

        return $this->successResponse($overview, 'Inventory overview retrieved successfully');
    }

    /**
     * Get stock levels
     */
    public function stockLevels(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('inventory.view');

        $query = $this->applyTenantScope(Product::query())
            ->with(['category', 'brand', 'shop', 'warehouse']);

        // Filter by status
        if ($request->has('stock_status')) {
            switch ($request->get('stock_status')) {
                case 'in_stock':
                    $query->inStock();
                    break;
                case 'low_stock':
                    $query->lowStock();
                    break;
                case 'out_of_stock':
                    $query->outOfStock();
                    break;
                case 'needs_reorder':
                    $query->needReorder();
                    break;
            }
        }

        // Category filter
        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        // Warehouse filter
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        // Shop filter
        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->get('shop_id'));
        }

        // Add calculated fields
        $query->selectRaw('products.*, 
            (stock_quantity * cost_price) as stock_value,
            (stock_quantity * selling_price) as retail_value,
            CASE 
                WHEN stock_quantity <= 0 THEN "out_of_stock"
                WHEN stock_quantity <= min_stock_level THEN "low_stock"
                WHEN stock_quantity <= reorder_point THEN "needs_reorder"
                ELSE "in_stock"
            END as stock_status');

        return $this->paginatedResponse($query, $request, 'Stock levels retrieved successfully');
    }

    /**
     * Get movement statistics
     */
    public function movementStats(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('inventory.view');

        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays($period);

        $query = $this->applyTenantScope(InventoryMovement::query())
            ->where('created_at', '>=', $startDate);

        $stats = [
            'total_movements' => $query->count(),
            'inbound_movements' => $query->inbound()->count(),
            'outbound_movements' => $query->outbound()->count(),
            'movements_by_type' => $query->selectRaw('movement_type, COUNT(*) as count, SUM(ABS(quantity)) as total_quantity')
                ->groupBy('movement_type')
                ->get(),
            'movements_by_day' => $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'top_moving_products' => $query->join('products', 'inventory_movements.product_id', '=', 'products.product_id')
                ->selectRaw('products.name, products.sku, SUM(ABS(inventory_movements.quantity)) as total_movement')
                ->groupBy('products.product_id', 'products.name', 'products.sku')
                ->orderBy('total_movement', 'desc')
                ->limit(10)
                ->get(),
            'movements_by_warehouse' => $query->join('warehouses', 'inventory_movements.warehouse_id', '=', 'warehouses.warehouse_id')
                ->selectRaw('warehouses.name, COUNT(*) as count')
                ->groupBy('warehouses.warehouse_id', 'warehouses.name')
                ->orderBy('count', 'desc')
                ->get(),
            'movements_by_user' => $query->join('users', 'inventory_movements.created_by', '=', 'users.user_id')
                ->selectRaw('users.full_name, COUNT(*) as count')
                ->groupBy('users.user_id', 'users.full_name')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
        ];

        return $this->successResponse($stats, 'Movement statistics retrieved successfully');
    }

    /**
     * Get valuation report
     */
    public function valuationReport(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('inventory.view');

        $query = $this->applyTenantScope(Product::query())
            ->with(['category', 'brand']);

        // Category filter
        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        // Warehouse filter
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        $products = $query->selectRaw('products.*, 
            (stock_quantity * cost_price) as cost_value,
            (stock_quantity * selling_price) as retail_value,
            ((selling_price - cost_price) * stock_quantity) as potential_profit')
            ->where('stock_quantity', '>', 0)
            ->get();

        $report = [
            'summary' => [
                'total_items' => $products->count(),
                'total_stock_quantity' => $products->sum('stock_quantity'),
                'total_cost_value' => $products->sum('cost_value'),
                'total_retail_value' => $products->sum('retail_value'),
                'total_potential_profit' => $products->sum('potential_profit'),
                'average_markup_percentage' => $products->avg(function ($product) {
                    return $product->cost_price > 0 ? 
                        (($product->selling_price - $product->cost_price) / $product->cost_price) * 100 : 0;
                })
            ],
            'by_category' => $products->groupBy('category.name')->map(function ($categoryProducts) {
                return [
                    'items_count' => $categoryProducts->count(),
                    'stock_quantity' => $categoryProducts->sum('stock_quantity'),
                    'cost_value' => $categoryProducts->sum('cost_value'),
                    'retail_value' => $categoryProducts->sum('retail_value'),
                    'potential_profit' => $categoryProducts->sum('potential_profit')
                ];
            }),
            'top_value_products' => $products->sortByDesc('retail_value')->take(10)->values(),
            'highest_margin_products' => $products->sortByDesc(function ($product) {
                return $product->cost_price > 0 ? 
                    (($product->selling_price - $product->cost_price) / $product->cost_price) * 100 : 0;
            })->take(10)->values()
        ];

        return $this->successResponse($report, 'Valuation report retrieved successfully');
    }

    /**
     * Get stock alerts
     */
    public function alerts(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('inventory.view');

        $alerts = [
            'low_stock' => $this->applyTenantScope(Product::query())
                ->lowStock()
                ->with(['category', 'shop', 'warehouse'])
                ->limit(20)
                ->get(),
            'out_of_stock' => $this->applyTenantScope(Product::query())
                ->outOfStock()
                ->with(['category', 'shop', 'warehouse'])
                ->limit(20)
                ->get(),
            'needs_reorder' => $this->applyTenantScope(Product::query())
                ->needReorder()
                ->with(['category', 'shop', 'warehouse'])
                ->limit(20)
                ->get(),
            'overstocked' => $this->applyTenantScope(Product::query())
                ->whereRaw('stock_quantity > max_stock_level')
                ->whereNotNull('max_stock_level')
                ->with(['category', 'shop', 'warehouse'])
                ->limit(20)
                ->get()
        ];

        return $this->successResponse($alerts, 'Stock alerts retrieved successfully');
    }

    /**
     * Bulk stock adjustment
     */
    public function bulkAdjustment(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('inventory.adjust');

        $validator = Validator::make($request->all(), [
            'adjustments' => 'required|array|min:1',
            'adjustments.*.product_id' => 'required|exists:products,product_id',
            'adjustments.*.variant_id' => 'nullable|exists:product_variants,variant_id',
            'adjustments.*.adjustment_type' => 'required|in:increase,decrease,set',
            'adjustments.*.quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:255',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',
            'shop_id' => 'nullable|exists:shops,shop_id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $results = [];
            $movements = [];

            foreach ($request->adjustments as $adjustment) {
                // Validate product belongs to tenant
                $product = $this->applyTenantScope(Product::query())->find($adjustment['product_id']);
                if (!$product) {
                    throw new \Exception("Product {$adjustment['product_id']} not found or does not belong to tenant");
                }

                // Validate variant if provided
                $variant = null;
                if (!empty($adjustment['variant_id'])) {
                    $variant = $product->variants()->find($adjustment['variant_id']);
                    if (!$variant) {
                        throw new \Exception("Product variant {$adjustment['variant_id']} not found");
                    }
                }

                // Calculate adjustment
                $currentStock = $variant ? $variant->stock_quantity : $product->stock_quantity;
                $adjustmentQuantity = match($adjustment['adjustment_type']) {
                    'increase' => $adjustment['quantity'],
                    'decrease' => -$adjustment['quantity'],
                    'set' => $adjustment['quantity'] - $currentStock
                };

                // Validate decrease doesn't go below zero
                if ($adjustment['adjustment_type'] === 'decrease' && $currentStock < $adjustment['quantity']) {
                    throw new \Exception("Cannot decrease stock below zero for product {$product->name}");
                }

                // Create movement
                $movement = InventoryMovement::recordAdjustment(
                    $this->tenant->tenant_id,
                    $product->product_id,
                    $variant?->variant_id,
                    $adjustmentQuantity,
                    $request->reason,
                    $request->warehouse_id,
                    $request->shop_id,
                    auth()->id()
                );

                // Update stock
                $newStock = match($adjustment['adjustment_type']) {
                    'increase' => $currentStock + $adjustment['quantity'],
                    'decrease' => $currentStock - $adjustment['quantity'],
                    'set' => $adjustment['quantity']
                };

                if ($variant) {
                    $variant->updateStock($newStock, $request->reason);
                } else {
                    $product->updateStock($newStock, $request->reason);
                }

                $movements[] = $movement;
                $results[] = [
                    'product' => $product->name,
                    'variant' => $variant?->variant_name,
                    'old_stock' => $currentStock,
                    'new_stock' => $newStock,
                    'adjustment' => $adjustmentQuantity
                ];
            }

            $this->logActivity('bulk_inventory_adjustment', 'inventory_movements', null, [
                'reason' => $request->reason,
                'adjustments_count' => count($request->adjustments),
                'results' => $results
            ]);

            DB::commit();

            return $this->successResponse([
                'movements' => $movements,
                'results' => $results
            ], 'Bulk inventory adjustment completed successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk adjustment failed: ' . $e->getMessage(), 500);
        }
    }
}
