<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Services\QueryOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends BaseController
{
    /**
     * Get main dashboard overview
     */
    public function overview(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('dashboard.view');

        $overview = [
            'summary' => $this->getSummaryStats(),
            'recent_activity' => $this->getRecentActivity(),
            'alerts' => $this->getAlerts(),
            'quick_stats' => $this->getQuickStats()
        ];

        return $this->successResponse($overview, 'Dashboard overview retrieved successfully');
    }

    /**
     * Get sales analytics
     */
    public function salesAnalytics(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('dashboard.view');

        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays($period);

        $analytics = [
            'revenue_trend' => $this->getRevenueTrend($startDate),
            'order_trend' => $this->getOrderTrend($startDate),
            'top_products' => $this->getTopProducts($startDate),
            'sales_by_category' => $this->getSalesByCategory($startDate),
            'sales_by_shop' => $this->getSalesByShop($startDate),
            'payment_methods' => $this->getPaymentMethodStats($startDate),
            'conversion_metrics' => $this->getConversionMetrics($startDate)
        ];

        return $this->successResponse($analytics, 'Sales analytics retrieved successfully');
    }

    /**
     * Get inventory analytics
     */
    public function inventoryAnalytics(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('dashboard.view');

        $analytics = [
            'stock_overview' => $this->getStockOverview(),
            'low_stock_alerts' => $this->getLowStockAlerts(),
            'inventory_value' => $this->getInventoryValue(),
            'inventory_movements' => $this->getRecentInventoryMovements(),
            'top_moving_products' => $this->getTopMovingProducts(),
            'stock_by_category' => $this->getStockByCategory(),
            'warehouse_utilization' => $this->getWarehouseUtilization()
        ];

        return $this->successResponse($analytics, 'Inventory analytics retrieved successfully');
    }

    /**
     * Get financial analytics
     */
    public function financialAnalytics(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('dashboard.view');

        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays($period);

        $analytics = [
            'revenue_summary' => $this->getRevenueSummary($startDate),
            'expense_summary' => $this->getExpenseSummary($startDate),
            'profit_analysis' => $this->getProfitAnalysis($startDate),
            'cash_flow' => $this->getCashFlow($startDate),
            'expense_breakdown' => $this->getExpenseBreakdown($startDate),
            'financial_ratios' => $this->getFinancialRatios($startDate)
        ];

        return $this->successResponse($analytics, 'Financial analytics retrieved successfully');
    }

    /**
     * Get customer analytics
     */
    public function customerAnalytics(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('dashboard.view');

        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays($period);

        $analytics = [
            'customer_overview' => $this->getCustomerOverview(),
            'customer_acquisition' => $this->getCustomerAcquisition($startDate),
            'customer_retention' => $this->getCustomerRetention($startDate),
            'top_customers' => $this->getTopCustomers($startDate),
            'customer_segments' => $this->getCustomerSegments(),
            'loyalty_program' => $this->getLoyaltyProgramStats()
        ];

        return $this->successResponse($analytics, 'Customer analytics retrieved successfully');
    }

    /**
     * Get real-time metrics
     */
    public function realTimeMetrics(): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('dashboard.view');

        $metrics = [
            'active_users' => $this->getActiveUsers(),
            'pending_orders' => $this->getPendingOrders(),
            'recent_sales' => $this->getRecentSales(),
            'stock_alerts' => $this->getStockAlerts(),
            'system_status' => $this->getSystemStatus()
        ];

        return $this->successResponse($metrics, 'Real-time metrics retrieved successfully');
    }

    /**
     * Get summary statistics
     */
    private function getSummaryStats(): array
    {
        return QueryOptimizationService::getOptimizedDashboardStats($this->tenant->tenant_id);
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(): array
    {
        $recentOrders = $this->applyTenantScope(Order::query())
            ->with([
                'customer:id,user_id,name,email',
                'shop:id,shop_id,name,status'
            ])
            ->latest('order_date')
            ->limit(5)
            ->get();

        $recentProducts = $this->applyTenantScope(Product::query())
            ->with([
                'category:id,category_id,name',
                'shop:id,shop_id,name,status'
            ])
            ->latest('created_at')
            ->limit(5)
            ->get();

        return [
            'recent_orders' => $recentOrders,
            'recent_products' => $recentProducts
        ];
    }

    /**
     * Get system alerts
     */
    private function getAlerts(): array
    {
        $alerts = [];

        // Low stock alerts
        $lowStockCount = $this->applyTenantScope(Product::query())->lowStock()->count();
        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Stock Alert',
                'message' => "{$lowStockCount} products are running low on stock",
                'action' => 'View Products',
                'url' => '/products?low_stock=1'
            ];
        }

        // Pending orders
        $pendingOrdersCount = $this->applyTenantScope(Order::query())->pending()->count();
        if ($pendingOrdersCount > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Pending Orders',
                'message' => "{$pendingOrdersCount} orders are pending confirmation",
                'action' => 'View Orders',
                'url' => '/orders?status=pending'
            ];
        }

        // Overdue purchases
        $overduePurchases = $this->applyTenantScope(Purchase::query())->overdue()->count();
        if ($overduePurchases > 0) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Overdue Purchases',
                'message' => "{$overduePurchases} purchases are overdue",
                'action' => 'View Purchases',
                'url' => '/purchases?overdue=1'
            ];
        }

        return $alerts;
    }

    /**
     * Get quick stats for widgets
     */
    private function getQuickStats(): array
    {
        return [
            'inventory_value' => $this->applyTenantScope(Product::query())
                ->sum(DB::raw('stock_quantity * cost_price')),
            'pending_order_value' => $this->applyTenantScope(Order::query())
                ->whereIn('status', ['pending', 'confirmed', 'processing'])
                ->sum('total_amount'),
            'monthly_expenses' => $this->applyTenantScope(Expense::query())
                ->whereMonth('expense_date', now()->month)
                ->where('approval_status', 'approved')
                ->sum('amount'),
            'active_suppliers' => $this->applyTenantScope(\App\Models\Supplier::query())
                ->active()
                ->count()
        ];
    }

    /**
     * Get revenue trend
     */
    private function getRevenueTrend(Carbon $startDate): array
    {
        return $this->applyTenantScope(Order::query())
            ->where('status', 'delivered')
            ->where('delivered_at', '>=', $startDate)
            ->selectRaw('DATE(delivered_at) as date, SUM(total_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get order trend
     */
    private function getOrderTrend(Carbon $startDate): array
    {
        return $this->applyTenantScope(Order::query())
            ->where('order_date', '>=', $startDate)
            ->selectRaw('DATE(order_date) as date, COUNT(*) as orders, 
                        SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get top selling products
     */
    private function getTopProducts(Carbon $startDate): array
    {
        return $this->applyTenantScope(Product::query())
            ->select('products.*')
            ->join('order_items', 'products.product_id', '=', 'order_items.product_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.order_id')
            ->where('orders.status', 'delivered')
            ->where('orders.delivered_at', '>=', $startDate)
            ->selectRaw('products.*, SUM(order_items.quantity) as total_sold, SUM(order_items.total_price) as total_revenue')
            ->groupBy('products.product_id')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get sales by category
     */
    private function getSalesByCategory(Carbon $startDate): array
    {
        return $this->applyTenantScope(Order::query())
            ->join('order_items', 'orders.order_id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.product_id')
            ->join('categories', 'products.category_id', '=', 'categories.category_id')
            ->where('orders.status', 'delivered')
            ->where('orders.delivered_at', '>=', $startDate)
            ->selectRaw('categories.name, SUM(order_items.total_price) as revenue, COUNT(order_items.order_item_id) as items_sold')
            ->groupBy('categories.category_id', 'categories.name')
            ->orderBy('revenue', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get sales by shop
     */
    private function getSalesByShop(Carbon $startDate): array
    {
        return $this->applyTenantScope(Order::query())
            ->join('shops', 'orders.shop_id', '=', 'shops.shop_id')
            ->where('orders.status', 'delivered')
            ->where('orders.delivered_at', '>=', $startDate)
            ->selectRaw('shops.name, COUNT(*) as orders_count, SUM(orders.total_amount) as revenue')
            ->groupBy('shops.shop_id', 'shops.name')
            ->orderBy('revenue', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get payment method statistics
     */
    private function getPaymentMethodStats(Carbon $startDate): array
    {
        return $this->applyTenantScope(Order::query())
            ->where('payment_status', 'paid')
            ->where('order_date', '>=', $startDate)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->orderBy('total', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get conversion metrics
     */
    private function getConversionMetrics(Carbon $startDate): array
    {
        $totalOrders = $this->applyTenantScope(Order::query())
            ->where('order_date', '>=', $startDate)
            ->count();

        $deliveredOrders = $this->applyTenantScope(Order::query())
            ->where('status', 'delivered')
            ->where('order_date', '>=', $startDate)
            ->count();

        $cancelledOrders = $this->applyTenantScope(Order::query())
            ->where('status', 'cancelled')
            ->where('order_date', '>=', $startDate)
            ->count();

        return [
            'total_orders' => $totalOrders,
            'delivered_orders' => $deliveredOrders,
            'cancelled_orders' => $cancelledOrders,
            'completion_rate' => $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 2) : 0,
            'cancellation_rate' => $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100, 2) : 0
        ];
    }

    /**
     * Get stock overview
     */
    private function getStockOverview(): array
    {
        $productsQuery = $this->applyTenantScope(Product::query());

        return [
            'total_products' => $productsQuery->count(),
            'in_stock' => $productsQuery->inStock()->count(),
            'low_stock' => $productsQuery->lowStock()->count(),
            'out_of_stock' => $productsQuery->outOfStock()->count(),
            'needs_reorder' => $productsQuery->needReorder()->count()
        ];
    }

    /**
     * Get low stock alerts
     */
    private function getLowStockAlerts(): array
    {
        return $this->applyTenantScope(Product::query())
            ->lowStock()
            ->with([
                'category:id,category_id,name',
                'shop:id,shop_id,name,status'
            ])
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get inventory value breakdown
     */
    private function getInventoryValue(): array
    {
        $totalCostValue = $this->applyTenantScope(Product::query())
            ->sum(DB::raw('stock_quantity * cost_price'));

        $totalRetailValue = $this->applyTenantScope(Product::query())
            ->sum(DB::raw('stock_quantity * selling_price'));

        return [
            'total_cost_value' => $totalCostValue,
            'total_retail_value' => $totalRetailValue,
            'potential_profit' => $totalRetailValue - $totalCostValue,
            'markup_percentage' => $totalCostValue > 0 ? round((($totalRetailValue - $totalCostValue) / $totalCostValue) * 100, 2) : 0
        ];
    }

    /**
     * Get recent inventory movements
     */
    private function getRecentInventoryMovements(): array
    {
        return $this->applyTenantScope(InventoryMovement::query())
            ->with([
                'product:id,product_id,name,sku',
                'createdBy:id,user_id,name,email'
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get top moving products
     */
    private function getTopMovingProducts(): array
    {
        return $this->applyTenantScope(InventoryMovement::query())
            ->join('products', 'inventory_movements.product_id', '=', 'products.product_id')
            ->where('inventory_movements.created_at', '>=', now()->subDays(30))
            ->selectRaw('products.name, products.sku, SUM(ABS(inventory_movements.quantity)) as total_movement')
            ->groupBy('products.product_id', 'products.name', 'products.sku')
            ->orderBy('total_movement', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get stock by category
     */
    private function getStockByCategory(): array
    {
        return $this->applyTenantScope(Product::query())
            ->join('categories', 'products.category_id', '=', 'categories.category_id')
            ->selectRaw('categories.name, SUM(products.stock_quantity) as total_stock, 
                        SUM(products.stock_quantity * products.cost_price) as total_value')
            ->groupBy('categories.category_id', 'categories.name')
            ->orderBy('total_value', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get warehouse utilization
     */
    private function getWarehouseUtilization(): array
    {
        return $this->applyTenantScope(\App\Models\Warehouse::query())
            ->select(['warehouse_id', 'name', 'capacity', 'current_utilization'])
            ->where('capacity', '>', 0)
            ->get()
            ->map(function ($warehouse) {
                return [
                    'name' => $warehouse->name,
                    'utilization_percentage' => $warehouse->current_utilization,
                    'available_capacity' => $warehouse->getAvailableCapacity(),
                    'status' => $warehouse->current_utilization > 80 ? 'high' : 
                               ($warehouse->current_utilization > 60 ? 'medium' : 'low')
                ];
            })
            ->toArray();
    }

    // Additional helper methods for financial, customer analytics would be implemented here
    // ... (continuing with financial and customer analytics methods)

    /**
     * Get active users count
     */
    private function getActiveUsers(): int
    {
        return $this->applyTenantScope(\App\Models\UserSession::query())
            ->where('is_active', true)
            ->where('login_at', '>=', now()->subHours(24))
            ->distinct('user_id')
            ->count();
    }

    /**
     * Get pending orders count
     */
    private function getPendingOrders(): int
    {
        return $this->applyTenantScope(Order::query())
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
    }

    /**
     * Get recent sales (last 24 hours)
     */
    private function getRecentSales(): array
    {
        return [
            'count' => $this->applyTenantScope(Order::query())
                ->where('order_date', '>=', now()->subDay())
                ->count(),
            'value' => $this->applyTenantScope(Order::query())
                ->where('order_date', '>=', now()->subDay())
                ->sum('total_amount')
        ];
    }

    /**
     * Get stock alerts count
     */
    private function getStockAlerts(): array
    {
        return [
            'low_stock' => $this->applyTenantScope(Product::query())->lowStock()->count(),
            'out_of_stock' => $this->applyTenantScope(Product::query())->outOfStock()->count(),
            'needs_reorder' => $this->applyTenantScope(Product::query())->needReorder()->count()
        ];
    }

    /**
     * Get system status
     */
    private function getSystemStatus(): array
    {
        return [
            'tenant_status' => $this->tenant->status,
            'subscription_plan' => $this->tenant->subscription_plan,
            'trial_days_remaining' => $this->tenant->getRemainingTrialDays(),
            'storage_used_percentage' => 0, // Would need to implement actual storage calculation
            'api_requests_used' => 0 // Would need to implement actual API usage tracking
        ];
    }
}
