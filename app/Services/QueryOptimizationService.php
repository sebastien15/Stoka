<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class QueryOptimizationService
{
    /**
     * Cache frequently accessed data
     */
    public static function getCachedStats($tenantId, $cacheKey, $callback, $ttl = 300)
    {
        return Cache::remember("tenant_{$tenantId}_{$cacheKey}", $ttl, $callback);
    }

    /**
     * Optimize dashboard summary queries
     */
    public static function getOptimizedDashboardStats($tenantId)
    {
        return self::getCachedStats($tenantId, 'dashboard_stats', function () use ($tenantId) {
            $today = now()->startOfDay();
            $thisMonth = now()->startOfMonth();

            // Use single query with subqueries for better performance
            $stats = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->selectRaw('
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN DATE(order_date) = ? THEN 1 ELSE 0 END) as orders_today,
                    SUM(CASE WHEN DATE(order_date) >= ? THEN 1 ELSE 0 END) as orders_this_month,
                    SUM(CASE WHEN status = "delivered" THEN total_amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status = "delivered" AND DATE(delivered_at) = ? THEN total_amount ELSE 0 END) as revenue_today,
                    SUM(CASE WHEN status = "delivered" AND DATE(delivered_at) >= ? THEN total_amount ELSE 0 END) as revenue_this_month
                ', [$today, $thisMonth, $today, $thisMonth])
                ->first();

            // Get product stats
            $productStats = DB::table('products')
                ->where('tenant_id', $tenantId)
                ->selectRaw('
                    COUNT(*) as total_products,
                    SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_products,
                    SUM(CASE WHEN stock_quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock_products
                ')
                ->first();

            // Get customer stats
            $customerStats = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('user_type', 'customer')
                ->selectRaw('
                    COUNT(*) as total_customers,
                    SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as new_customers_today,
                    SUM(CASE WHEN DATE(created_at) >= ? THEN 1 ELSE 0 END) as new_customers_this_month
                ', [$today, $thisMonth])
                ->first();

            return array_merge(
                (array) $stats,
                (array) $productStats,
                (array) $customerStats
            );
        });
    }

    /**
     * Optimize product listing with proper indexing
     */
    public static function optimizeProductQuery(Builder $query, array $filters = [])
    {
        // Apply tenant scope first (most selective)
        $query->where('tenant_id', auth()->user()->tenant_id);

        // Apply filters in order of selectivity
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['shop_id'])) {
            $query->where('shop_id', $filters['shop_id']);
        }

        if (isset($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (isset($filters['low_stock']) && $filters['low_stock']) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        if (isset($filters['needs_reorder']) && $filters['needs_reorder']) {
            $query->whereRaw('stock_quantity <= reorder_point');
        }

        if (isset($filters['featured']) && $filters['featured']) {
            $query->where('is_featured', true);
        }

        if (isset($filters['digital'])) {
            $query->where('is_digital', (bool) $filters['digital']);
        }

        if (isset($filters['min_price'])) {
            $query->where('selling_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('selling_price', '<=', $filters['max_price']);
        }

        // Text search should be last
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Optimize order listing with proper indexing
     */
    public static function optimizeOrderQuery(Builder $query, array $filters = [])
    {
        // Apply tenant scope first
        $query->where('tenant_id', auth()->user()->tenant_id);

        // Apply filters in order of selectivity
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['shop_id'])) {
            $query->where('shop_id', $filters['shop_id']);
        }

        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (isset($filters['order_date_from'])) {
            $query->whereDate('order_date', '>=', $filters['order_date_from']);
        }

        if (isset($filters['order_date_to'])) {
            $query->whereDate('order_date', '<=', $filters['order_date_to']);
        }

        if (isset($filters['min_amount'])) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount'])) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }

        return $query;
    }

    /**
     * Get optimized analytics data
     */
    public static function getOptimizedAnalytics($tenantId, $period = 30)
    {
        return self::getCachedStats($tenantId, "analytics_{$period}", function () use ($tenantId, $period) {
            $startDate = now()->subDays($period);

            // Revenue trend with single query
            $revenueTrend = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('status', 'delivered')
                ->where('delivered_at', '>=', $startDate)
                ->selectRaw('DATE(delivered_at) as date, SUM(total_amount) as revenue')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Order trend with single query
            $orderTrend = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('order_date', '>=', $startDate)
                ->selectRaw('
                    DATE(order_date) as date, 
                    COUNT(*) as orders,
                    SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Top products with single query
            $topProducts = DB::table('products')
                ->join('order_items', 'products.product_id', '=', 'order_items.product_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.order_id')
                ->where('products.tenant_id', $tenantId)
                ->where('orders.status', 'delivered')
                ->where('orders.delivered_at', '>=', $startDate)
                ->selectRaw('
                    products.product_id,
                    products.name,
                    products.sku,
                    SUM(order_items.quantity) as total_sold,
                    SUM(order_items.total_price) as total_revenue
                ')
                ->groupBy('products.product_id', 'products.name', 'products.sku')
                ->orderBy('total_sold', 'desc')
                ->limit(10)
                ->get();

            return [
                'revenue_trend' => $revenueTrend,
                'order_trend' => $orderTrend,
                'top_products' => $topProducts
            ];
        });
    }

    /**
     * Clear cache for tenant
     */
    public static function clearTenantCache($tenantId)
    {
        $keys = [
            "tenant_{$tenantId}_dashboard_stats",
            "tenant_{$tenantId}_analytics_30",
            "tenant_{$tenantId}_analytics_7",
            "tenant_{$tenantId}_analytics_90"
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
