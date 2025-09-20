<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ShopController extends BaseController
{
    /**
     * Display a listing of shops
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.view');

        $query = $this->applyTenantScope(Shop::query());

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

        // Warehouse filter
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        // Shop type filter
        if ($request->has('shop_type')) {
            $query->where('shop_type', $request->get('shop_type'));
        }

        // Online enabled filter
        if ($request->has('online_enabled')) {
            $query->where('online_shop_enabled', (bool) $request->get('online_enabled'));
        }

        // Delivery enabled filter
        if ($request->has('delivery_enabled')) {
            $query->where('delivery_enabled', (bool) $request->get('delivery_enabled'));
        }

        // Load relationships
        $query->with(['manager', 'warehouse', 'products', 'orders'])
             ->withCount(['products', 'orders']);

        return $this->paginatedResponse($query, $request, 'Shops retrieved successfully');
    }

    /**
     * Store a newly created shop
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.create');

        // Check tenant limits
        if (!$this->checkTenantLimits('shops')) {
            return $this->errorResponse('Shop limit reached for your subscription plan', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20|unique:shops,code',
            'address' => 'required|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'manager_id' => 'required|exists:users,user_id',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',
            'shop_type' => 'nullable|in:retail,wholesale,online,franchise',
            'floor_area' => 'nullable|numeric|min:0',
            'rent_amount' => 'nullable|numeric|min:0',
            'opening_hours' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:500',
            'social_media_handles' => 'nullable|array',
            'pos_system' => 'nullable|string|max:50',
            'online_shop_enabled' => 'nullable|boolean',
            'delivery_enabled' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $shopData = $validator->validated();
            $shopData['tenant_id'] = $this->tenant->tenant_id;
            $shopData['is_active'] = true;

            // Validate manager belongs to tenant
            $manager = $this->applyTenantScope(\App\Models\User::query())->find($shopData['manager_id']);
            if (!$manager) {
                return $this->errorResponse('Manager not found or does not belong to tenant', 400);
            }

            // Validate warehouse belongs to tenant if provided
            if (!empty($shopData['warehouse_id'])) {
                $warehouse = $this->tenant->warehouses()->find($shopData['warehouse_id']);
                if (!$warehouse) {
                    return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);
                }
            }

            // Generate unique code if not provided
            if (empty($shopData['code'])) {
                $shopData['code'] = $this->generateUniqueCode('SH', Shop::class, 'code');
            }

            $shop = Shop::create($shopData);

            $this->logActivity('shop_created', 'shops', $shop->shop_id);

            DB::commit();

            $shop->load(['manager', 'warehouse', 'products', 'orders']);

            return $this->successResponse($shop, 'Shop created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified shop
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.view');

        $shop = $this->applyTenantScope(Shop::query())
            ->with(['manager', 'warehouse', 'products', 'orders'])
            ->withCount(['products', 'orders'])
            ->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        // Add statistics
        $shop->stats = [
            'products_count' => $shop->getProductCount(),
            'active_products_count' => $shop->getActiveProductCount(),
            'total_inventory_value' => $shop->getTotalInventoryValue(),
            'total_sales_value' => $shop->getTotalSalesValue(),
            'monthly_revenue' => $shop->getMonthlyRevenue(),
            'pending_orders' => $shop->getPendingOrders()->count(),
            'orders_today' => $shop->orders()->whereDate('order_date', now())->count()
        ];

        // Get recent orders
        $shop->recent_orders = $shop->getRecentOrders(10);

        // Get top selling products
        $shop->top_products = $shop->getTopSellingProducts(5);

        return $this->successResponse($shop, 'Shop retrieved successfully');
    }

    /**
     * Update the specified shop
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.edit');

        $shop = $this->applyTenantScope(Shop::query())->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'code' => 'sometimes|string|max:20|unique:shops,code,' . $id . ',shop_id',
            'address' => 'sometimes|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'manager_id' => 'sometimes|exists:users,user_id',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',
            'shop_type' => 'nullable|in:retail,wholesale,online,franchise',
            'floor_area' => 'nullable|numeric|min:0',
            'rent_amount' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'opening_hours' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:500',
            'social_media_handles' => 'nullable|array',
            'pos_system' => 'nullable|string|max:50',
            'online_shop_enabled' => 'nullable|boolean',
            'delivery_enabled' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $shop->toArray();
            $shopData = $validator->validated();

            // Validate manager belongs to tenant if provided
            if (isset($shopData['manager_id'])) {
                $manager = $this->applyTenantScope(\App\Models\User::query())->find($shopData['manager_id']);
                if (!$manager) {
                    return $this->errorResponse('Manager not found or does not belong to tenant', 400);
                }
            }

            // Validate warehouse belongs to tenant if provided
            if (isset($shopData['warehouse_id']) && $shopData['warehouse_id']) {
                $warehouse = $this->tenant->warehouses()->find($shopData['warehouse_id']);
                if (!$warehouse) {
                    return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);
                }
            }

            $shop->update($shopData);

            $this->logActivity('shop_updated', 'shops', $shop->shop_id, [
                'old_values' => $oldValues,
                'new_values' => $shop->toArray()
            ]);

            DB::commit();

            $shop->load(['manager', 'warehouse', 'products', 'orders']);

            return $this->successResponse($shop, 'Shop updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified shop
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.delete');

        $shop = $this->applyTenantScope(Shop::query())->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        // Check if shop has pending orders
        $pendingOrders = $shop->orders()->whereIn('status', ['pending', 'confirmed', 'processing'])->count();
        if ($pendingOrders > 0) {
            return $this->errorResponse('Cannot delete shop with pending orders', 400);
        }

        try {
            DB::beginTransaction();

            $shopData = $shop->toArray();

            // Update products to remove shop reference
            $shop->products()->update(['shop_id' => null]);

            $shop->delete();

            $this->logActivity('shop_deleted', 'shops', $id, ['deleted_shop' => $shopData]);

            DB::commit();

            return $this->successResponse(null, 'Shop deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get shop products
     */
    public function products(int $id, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.view');

        $shop = $this->applyTenantScope(Shop::query())->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        $query = $shop->products()->with(['category', 'brand', 'supplier']);

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

        return $this->paginatedResponse($query, $request, 'Shop products retrieved successfully');
    }

    /**
     * Get shop orders
     */
    public function orders(int $id, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.view');

        $shop = $this->applyTenantScope(Shop::query())->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        $query = $shop->orders()->with(['customer', 'items.product']);

        // Apply order filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('order_date', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('order_date', '<=', $request->get('date_to'));
        }

        return $this->paginatedResponse($query, $request, 'Shop orders retrieved successfully');
    }

    /**
     * Get shop sales analytics
     */
    public function salesAnalytics(int $id, Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.view');

        $shop = $this->applyTenantScope(Shop::query())->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays($period);

        $analytics = [
            'revenue_trend' => $shop->orders()
                ->where('status', 'delivered')
                ->where('delivered_at', '>=', $startDate)
                ->selectRaw('DATE(delivered_at) as date, SUM(total_amount) as revenue')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            
            'order_trend' => $shop->orders()
                ->where('order_date', '>=', $startDate)
                ->selectRaw('DATE(order_date) as date, COUNT(*) as orders')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            
            'top_products' => $shop->products()
                ->join('order_items', 'products.product_id', '=', 'order_items.product_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.order_id')
                ->where('orders.status', 'delivered')
                ->where('orders.delivered_at', '>=', $startDate)
                ->selectRaw('products.name, SUM(order_items.quantity) as total_sold, SUM(order_items.total_price) as total_revenue')
                ->groupBy('products.product_id', 'products.name')
                ->orderBy('total_revenue', 'desc')
                ->limit(10)
                ->get(),
            
            'payment_methods' => $shop->orders()
                ->where('payment_status', 'paid')
                ->where('order_date', '>=', $startDate)
                ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
                ->whereNotNull('payment_method')
                ->groupBy('payment_method')
                ->get(),
            
            'daily_summary' => [
                'orders_today' => $shop->orders()->whereDate('order_date', now())->count(),
                'revenue_today' => $shop->orders()
                    ->whereDate('order_date', now())
                    ->where('status', 'delivered')
                    ->sum('total_amount'),
                'pending_orders' => $shop->orders()->whereIn('status', ['pending', 'confirmed'])->count(),
                'average_order_value' => $shop->orders()
                    ->where('order_date', '>=', $startDate)
                    ->avg('total_amount')
            ]
        ];

        return $this->successResponse($analytics, 'Shop sales analytics retrieved successfully');
    }

    /**
     * Update social media handles
     */
    public function updateSocialMedia(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.edit');

        $shop = $this->applyTenantScope(Shop::query())->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'social_media_handles' => 'required|array',
            'social_media_handles.facebook' => 'nullable|string|max:255',
            'social_media_handles.instagram' => 'nullable|string|max:255',
            'social_media_handles.twitter' => 'nullable|string|max:255',
            'social_media_handles.linkedin' => 'nullable|string|max:255',
            'social_media_handles.youtube' => 'nullable|string|max:255',
            'social_media_handles.tiktok' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $shop->update([
            'social_media_handles' => $request->social_media_handles
        ]);

        $this->logActivity('shop_social_media_updated', 'shops', $shop->shop_id);

        return $this->successResponse($shop, 'Social media handles updated successfully');
    }

    /**
     * Get shop statistics
     */
    public function stats(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.view');

        $query = $this->applyTenantScope(Shop::query());

        $stats = [
            'total_shops' => $query->count(),
            'active_shops' => $query->active()->count(),
            'inactive_shops' => $query->inactive()->count(),
            'online_enabled_shops' => $query->onlineEnabled()->count(),
            'delivery_enabled_shops' => $query->deliveryEnabled()->count(),
            'shops_by_type' => $query->selectRaw('shop_type, COUNT(*) as count')
                ->whereNotNull('shop_type')
                ->groupBy('shop_type')
                ->get(),
            'total_orders_today' => Order::whereIn('shop_id', $query->pluck('shop_id'))
                ->whereDate('order_date', now())
                ->count(),
            'total_revenue_today' => Order::whereIn('shop_id', $query->pluck('shop_id'))
                ->whereDate('order_date', now())
                ->where('status', 'delivered')
                ->sum('total_amount'),
            'average_monthly_revenue' => $query->withCount('orders')
                ->get()
                ->map(function ($shop) {
                    return $shop->getMonthlyRevenue();
                })
                ->avg()
        ];

        return $this->successResponse($stats, 'Shop statistics retrieved successfully');
    }

    /**
     * Toggle online shop status
     */
    public function toggleOnlineShop(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.edit');

        $shop = $this->applyTenantScope(Shop::query())->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        $shop->update([
            'online_shop_enabled' => !$shop->online_shop_enabled
        ]);

        $action = $shop->online_shop_enabled ? 'enabled' : 'disabled';
        $this->logActivity("shop_online_{$action}", 'shops', $shop->shop_id);

        return $this->successResponse($shop, "Online shop {$action} successfully");
    }

    /**
     * Toggle delivery service
     */
    public function toggleDelivery(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('shops.edit');

        $shop = $this->applyTenantScope(Shop::query())->find($id);

        if (!$shop) {
            return $this->errorResponse('Shop not found', 404);
        }

        $shop->update([
            'delivery_enabled' => !$shop->delivery_enabled
        ]);

        $action = $shop->delivery_enabled ? 'enabled' : 'disabled';
        $this->logActivity("shop_delivery_{$action}", 'shops', $shop->shop_id);

        return $this->successResponse($shop, "Delivery service {$action} successfully");
    }
}
