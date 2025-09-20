<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseController
{
    /**
     * Display a listing of orders
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.view');

        $query = $this->applyTenantScope(Order::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'order_number',
            'customer_notes',
            'internal_notes'
        ]);

        // Customer filter
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        // Shop filter
        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->get('shop_id'));
        }

        // Payment status filter
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        // Payment method filter
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        // Date range filters
        if ($request->has('order_date_from')) {
            $query->whereDate('order_date', '>=', $request->get('order_date_from'));
        }
        if ($request->has('order_date_to')) {
            $query->whereDate('order_date', '<=', $request->get('order_date_to'));
        }

        // Amount range filters
        if ($request->has('min_amount')) {
            $query->where('total_amount', '>=', $request->get('min_amount'));
        }
        if ($request->has('max_amount')) {
            $query->where('total_amount', '<=', $request->get('max_amount'));
        }

        // Load relationships
        $query->with(['customer', 'shop', 'warehouse', 'items.product', 'items.variant']);

        return $this->paginatedResponse($query, $request, 'Orders retrieved successfully');
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.create');

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:users,user_id',
            'shop_id' => 'required|exists:shops,shop_id',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',
            'payment_method' => 'nullable|in:cash,card,mobile_money,bank_transfer,credit',
            'shipping_address' => 'nullable|string',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_postal_code' => 'nullable|string|max:20',
            'shipping_method' => 'nullable|string|max:50',
            'customer_notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,product_id',
            'items.*.variant_id' => 'nullable|exists:product_variants,variant_id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            // Validate customer belongs to tenant
            $customer = $this->applyTenantScope(\App\Models\User::query())->find($request->customer_id);
            if (!$customer) {
                return $this->errorResponse('Customer not found or does not belong to tenant', 400);
            }

            // Validate shop belongs to tenant
            $shop = $this->tenant->shops()->find($request->shop_id);
            if (!$shop) {
                return $this->errorResponse('Shop not found or does not belong to tenant', 400);
            }

            // Validate warehouse if provided
            if ($request->warehouse_id) {
                $warehouse = $this->tenant->warehouses()->find($request->warehouse_id);
                if (!$warehouse) {
                    return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);
                }
            }

            // Generate order number
            $orderNumber = $this->generateOrderNumber();

            // Create order
            $orderData = $validator->validated();
            $orderData['tenant_id'] = $this->tenant->tenant_id;
            $orderData['order_number'] = $orderNumber;
            $orderData['order_date'] = now();
            $orderData['status'] = 'pending';
            $orderData['payment_status'] = 'pending';

            // Calculate totals (will be updated after adding items)
            $orderData['subtotal'] = 0;
            $orderData['tax_amount'] = 0;
            $orderData['discount_amount'] = 0;
            $orderData['shipping_amount'] = 0;
            $orderData['total_amount'] = 0;

            $order = Order::create($orderData);

            // Add order items
            $subtotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;

            foreach ($request->items as $itemData) {
                $product = $this->tenant->products()->find($itemData['product_id']);
                if (!$product) {
                    throw new \Exception("Product {$itemData['product_id']} not found or does not belong to tenant");
                }

                $variant = null;
                if (!empty($itemData['variant_id'])) {
                    $variant = $product->variants()->find($itemData['variant_id']);
                    if (!$variant) {
                        throw new \Exception("Product variant {$itemData['variant_id']} not found");
                    }
                }

                // Get unit price (from request or product/variant)
                $unitPrice = $itemData['unit_price'] ?? ($variant ? $variant->getActualPrice() : $product->getActualPrice());
                $discountAmount = $itemData['discount_amount'] ?? 0;
                $netPrice = $unitPrice - $discountAmount;
                $totalPrice = $netPrice * $itemData['quantity'];

                // Calculate tax
                $taxRate = $product->tax_rate ?? 0;
                $taxAmount = ($totalPrice * $taxRate) / 100;

                OrderItem::create([
                    'tenant_id' => $this->tenant->tenant_id,
                    'order_id' => $order->order_id,
                    'product_id' => $itemData['product_id'],
                    'variant_id' => $itemData['variant_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount
                ]);

                $subtotal += $totalPrice;
                $totalDiscount += ($discountAmount * $itemData['quantity']);
                $totalTax += $taxAmount;
            }

            // Update order totals
            $order->update([
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $totalTax,
                'total_amount' => $subtotal + $totalTax + ($orderData['shipping_amount'] ?? 0)
            ]);

            $this->logActivity('order_created', 'orders', $order->order_id);

            DB::commit();

            $order->load(['customer', 'shop', 'warehouse', 'items.product', 'items.variant']);

            return $this->successResponse($order, 'Order created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.view');

        $order = $this->applyTenantScope(Order::query())
            ->with(['customer.customerProfile', 'shop', 'warehouse', 'items.product', 'items.variant'])
            ->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        // Add additional information
        $order->stats = [
            'days_since_order' => $order->order_date->diffInDays(now()),
            'items_count' => $order->getTotalItemCount(),
            'unique_items_count' => $order->getUniqueItemCount(),
            'can_be_cancelled' => $order->canBeCancelled(),
            'can_be_shipped' => $order->canBeShipped(),
            'can_be_delivered' => $order->canBeDelivered()
        ];

        return $this->successResponse($order, 'Order retrieved successfully');
    }

    /**
     * Update the specified order
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.edit');

        $order = $this->applyTenantScope(Order::query())->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        // Only allow updates for pending/confirmed orders
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return $this->errorResponse('Order cannot be modified in current status', 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'sometimes|in:cash,card,mobile_money,bank_transfer,credit',
            'shipping_address' => 'nullable|string',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_postal_code' => 'nullable|string|max:20',
            'shipping_method' => 'nullable|string|max:50',
            'shipping_amount' => 'nullable|numeric|min:0',
            'customer_notes' => 'nullable|string',
            'internal_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $order->toArray();
            $order->update($validator->validated());

            // Recalculate total if shipping amount changed
            if ($request->has('shipping_amount')) {
                $order->update([
                    'total_amount' => $order->subtotal + $order->tax_amount + $order->shipping_amount
                ]);
            }

            $this->logActivity('order_updated', 'orders', $order->order_id, [
                'old_values' => $oldValues,
                'new_values' => $order->toArray()
            ]);

            DB::commit();

            $order->load(['customer', 'shop', 'warehouse', 'items.product', 'items.variant']);

            return $this->successResponse($order, 'Order updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified order
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.delete');

        $order = $this->applyTenantScope(Order::query())->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        // Only allow deletion of pending orders
        if ($order->status !== 'pending') {
            return $this->errorResponse('Only pending orders can be deleted', 400);
        }

        try {
            DB::beginTransaction();

            $orderData = $order->toArray();
            
            // Delete order items first
            $order->items()->delete();
            $order->delete();

            $this->logActivity('order_deleted', 'orders', $id, ['deleted_order' => $orderData]);

            DB::commit();

            return $this->successResponse(null, 'Order deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Confirm order
     */
    public function confirm(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.manage');

        $order = $this->applyTenantScope(Order::query())->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        if (!$order->isPending()) {
            return $this->errorResponse('Only pending orders can be confirmed', 400);
        }

        try {
            DB::beginTransaction();

            $order->confirm();
            $this->logActivity('order_confirmed', 'orders', $order->order_id);

            DB::commit();

            return $this->successResponse($order, 'Order confirmed successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to confirm order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Ship order
     */
    public function ship(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.manage');

        $order = $this->applyTenantScope(Order::query())->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        if (!$order->canBeShipped()) {
            return $this->errorResponse('Order cannot be shipped in current status', 400);
        }

        $validator = Validator::make($request->all(), [
            'tracking_number' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $order->ship($request->tracking_number);
            $this->logActivity('order_shipped', 'orders', $order->order_id, [
                'tracking_number' => $request->tracking_number
            ]);

            DB::commit();

            return $this->successResponse($order, 'Order shipped successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to ship order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Deliver order
     */
    public function deliver(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.manage');

        $order = $this->applyTenantScope(Order::query())->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        if (!$order->canBeDelivered()) {
            return $this->errorResponse('Order cannot be delivered in current status', 400);
        }

        try {
            DB::beginTransaction();

            $order->deliver();
            $this->logActivity('order_delivered', 'orders', $order->order_id);

            DB::commit();

            return $this->successResponse($order, 'Order delivered successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to deliver order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.manage');

        $order = $this->applyTenantScope(Order::query())->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        if (!$order->canBeCancelled()) {
            return $this->errorResponse('Order cannot be cancelled in current status', 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $order->cancel($request->reason);
            $this->logActivity('order_cancelled', 'orders', $order->order_id, [
                'reason' => $request->reason
            ]);

            DB::commit();

            return $this->successResponse($order, 'Order cancelled successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to cancel order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark order as paid
     */
    public function markPaid(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.manage_payment');

        $order = $this->applyTenantScope(Order::query())->find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:cash,card,mobile_money,bank_transfer,credit'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $order->markAsPaid($request->payment_method);
            $this->logActivity('order_payment_received', 'orders', $order->order_id, [
                'payment_method' => $request->payment_method
            ]);

            DB::commit();

            return $this->successResponse($order, 'Order marked as paid successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to mark order as paid: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get order statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('orders.view');

        $query = $this->applyTenantScope(Order::query());

        // Date range filter for stats
        if ($request->has('date_from')) {
            $query->whereDate('order_date', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('order_date', '<=', $request->get('date_to'));
        }

        $stats = [
            'total_orders' => $query->count(),
            'pending_orders' => $query->pending()->count(),
            'confirmed_orders' => $query->confirmed()->count(),
            'shipped_orders' => $query->shipped()->count(),
            'delivered_orders' => $query->delivered()->count(),
            'cancelled_orders' => $query->cancelled()->count(),
            'total_revenue' => $query->delivered()->sum('total_amount'),
            'pending_value' => $query->whereIn('status', ['pending', 'confirmed', 'processing'])->sum('total_amount'),
            'average_order_value' => $query->where('total_amount', '>', 0)->avg('total_amount'),
            'orders_by_payment_method' => $query->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
                ->whereNotNull('payment_method')
                ->groupBy('payment_method')
                ->get(),
            'recent_orders' => $query->recent(7)->count()
        ];

        return $this->successResponse($stats, 'Order statistics retrieved successfully');
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $count = $this->applyTenantScope(Order::query())
            ->whereDate('created_at', now())
            ->count() + 1;
            
        return "ORD-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
