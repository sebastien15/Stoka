<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PurchaseController extends BaseController
{
    /**
     * Display a listing of purchases
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.view');

        $query = $this->applyTenantScope(Purchase::query());

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'purchase_number',
            'notes'
        ]);

        // Supplier filter
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->get('supplier_id'));
        }

        // Warehouse filter
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        // Shop filter
        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->get('shop_id'));
        }

        // Payment status filter
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
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

        // Optimize with eager loading and specific columns
        $query->with([
            'supplier:id,supplier_id,name,contact_person,email,phone_number,rating',
            'warehouse:id,warehouse_id,name,status,address',
            'shop:id,shop_id,name,status,address',
            'createdBy:id,user_id,name,email',
            'items:id,purchase_id,product_id,variant_id,quantity_ordered,quantity_received,unit_cost,total_cost',
            'items.product:id,product_id,name,sku,status',
            'items.variant:id,variant_id,product_id,name,sku'
        ]);

        return $this->paginatedResponse($query, $request, 'Purchases retrieved successfully');
    }

    /**
     * Store a newly created purchase
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.create');

        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,supplier_id',
            'warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'shop_id' => 'nullable|exists:shops,shop_id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:order_date',
            'payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,product_id',
            'items.*.variant_id' => 'nullable|exists:product_variants,variant_id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            // Validate supplier belongs to tenant
            $supplier = $this->tenant->suppliers()->find($request->supplier_id);
            if (!$supplier) {
                return $this->errorResponse('Supplier not found or does not belong to tenant', 400);
            }

            // Validate warehouse belongs to tenant
            $warehouse = $this->tenant->warehouses()->find($request->warehouse_id);
            if (!$warehouse) {
                return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);
            }

            // Validate shop if provided
            if ($request->shop_id) {
                $shop = $this->tenant->shops()->find($request->shop_id);
                if (!$shop) {
                    return $this->errorResponse('Shop not found or does not belong to tenant', 400);
                }
            }

            // Generate purchase number
            $purchaseNumber = $this->generatePurchaseNumber();

            // Create purchase
            $purchaseData = $validator->validated();
            $purchaseData['tenant_id'] = $this->tenant->tenant_id;
            $purchaseData['purchase_number'] = $purchaseNumber;
            $purchaseData['status'] = 'draft';
            $purchaseData['payment_status'] = 'pending';
            $purchaseData['created_by'] = auth()->id();

            // Calculate total amount
            $totalAmount = 0;

            $purchase = Purchase::create($purchaseData);

            // Add purchase items
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

                $totalCost = $itemData['quantity_ordered'] * $itemData['unit_cost'];

                PurchaseItem::create([
                    'tenant_id' => $this->tenant->tenant_id,
                    'purchase_id' => $purchase->purchase_id,
                    'product_id' => $itemData['product_id'],
                    'variant_id' => $itemData['variant_id'] ?? null,
                    'quantity_ordered' => $itemData['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost' => $itemData['unit_cost'],
                    'total_cost' => $totalCost
                ]);

                $totalAmount += $totalCost;
            }

            // Update purchase total amount
            $purchase->update(['total_amount' => $totalAmount]);

            $this->logActivity('purchase_created', 'purchases', $purchase->purchase_id);

            DB::commit();

            $purchase->load([
                'supplier:id,supplier_id,name,contact_person,email,phone_number,rating',
                'warehouse:id,warehouse_id,name,status,address',
                'shop:id,shop_id,name,status,address',
                'createdBy:id,user_id,name,email',
                'items:id,purchase_id,product_id,variant_id,quantity_ordered,quantity_received,unit_cost,total_cost',
                'items.product:id,product_id,name,sku,status',
                'items.variant:id,variant_id,product_id,name,sku'
            ]);

            return $this->successResponse($purchase, 'Purchase created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create purchase: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified purchase
     */
    public function show(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.view');

        $purchase = $this->applyTenantScope(Purchase::query())
            ->with([
                'supplier:id,supplier_id,name,contact_person,email,phone_number,rating,address',
                'warehouse:id,warehouse_id,name,status,address',
                'shop:id,shop_id,name,status,address',
                'createdBy:id,user_id,name,email',
                'items:id,purchase_id,product_id,variant_id,quantity_ordered,quantity_received,unit_cost,total_cost',
                'items.product:id,product_id,name,sku,status,selling_price,stock_quantity',
                'items.variant:id,variant_id,product_id,name,sku,price'
            ])
            ->find($id);

        if (!$purchase) {
            return $this->errorResponse('Purchase not found', 404);
        }

        // Add additional information
        $purchase->stats = [
            'items_count' => $purchase->getTotalItemCount(),
            'unique_items_count' => $purchase->getUniqueItemCount(),
            'total_received_count' => $purchase->getTotalReceivedCount(),
            'receival_percentage' => $purchase->getReceivalPercentage(),
            'days_until_delivery' => $purchase->getDaysUntilDelivery(),
            'days_overdue' => $purchase->getDaysOverdue(),
            'can_be_confirmed' => $purchase->canBeConfirmed(),
            'can_be_cancelled' => $purchase->canBeCancelled(),
            'can_receive_items' => $purchase->canReceiveItems(),
            'is_overdue' => $purchase->isOverdue()
        ];

        return $this->successResponse($purchase, 'Purchase retrieved successfully');
    }

    /**
     * Update the specified purchase
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.edit');

        $purchase = $this->applyTenantScope(Purchase::query())->find($id);

        if (!$purchase) {
            return $this->errorResponse('Purchase not found', 404);
        }

        // Only allow updates for draft/pending purchases
        if (!in_array($purchase->status, ['draft', 'pending'])) {
            return $this->errorResponse('Purchase cannot be modified in current status', 400);
        }

        $validator = Validator::make($request->all(), [
            'expected_delivery_date' => 'nullable|date|after:order_date',
            'payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $purchase->toArray();
            $purchase->update($validator->validated());

            $this->logActivity('purchase_updated', 'purchases', $purchase->purchase_id, [
                'old_values' => $oldValues,
                'new_values' => $purchase->toArray()
            ]);

            DB::commit();

            $purchase->load([
                'supplier:id,supplier_id,name,contact_person,email,phone_number,rating',
                'warehouse:id,warehouse_id,name,status,address',
                'shop:id,shop_id,name,status,address',
                'createdBy:id,user_id,name,email',
                'items:id,purchase_id,product_id,variant_id,quantity_ordered,quantity_received,unit_cost,total_cost',
                'items.product:id,product_id,name,sku,status',
                'items.variant:id,variant_id,product_id,name,sku'
            ]);

            return $this->successResponse($purchase, 'Purchase updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update purchase: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified purchase
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.delete');

        $purchase = $this->applyTenantScope(Purchase::query())->find($id);

        if (!$purchase) {
            return $this->errorResponse('Purchase not found', 404);
        }

        // Only allow deletion of draft purchases
        if ($purchase->status !== 'draft') {
            return $this->errorResponse('Only draft purchases can be deleted', 400);
        }

        try {
            DB::beginTransaction();

            $purchaseData = $purchase->toArray();
            
            // Delete purchase items first
            $purchase->items()->delete();
            $purchase->delete();

            $this->logActivity('purchase_deleted', 'purchases', $id, ['deleted_purchase' => $purchaseData]);

            DB::commit();

            return $this->successResponse(null, 'Purchase deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete purchase: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Confirm purchase
     */
    public function confirm(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.manage');

        $purchase = $this->applyTenantScope(Purchase::query())->find($id);

        if (!$purchase) {
            return $this->errorResponse('Purchase not found', 404);
        }

        if (!$purchase->canBeConfirmed()) {
            return $this->errorResponse('Purchase cannot be confirmed in current status', 400);
        }

        try {
            DB::beginTransaction();

            $purchase->confirm();
            $this->logActivity('purchase_confirmed', 'purchases', $purchase->purchase_id);

            DB::commit();

            return $this->successResponse($purchase, 'Purchase confirmed successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to confirm purchase: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel purchase
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.manage');

        $purchase = $this->applyTenantScope(Purchase::query())->find($id);

        if (!$purchase) {
            return $this->errorResponse('Purchase not found', 404);
        }

        if (!$purchase->canBeCancelled()) {
            return $this->errorResponse('Purchase cannot be cancelled in current status', 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $purchase->cancel($request->reason);
            $this->logActivity('purchase_cancelled', 'purchases', $purchase->purchase_id, [
                'reason' => $request->reason
            ]);

            DB::commit();

            return $this->successResponse($purchase, 'Purchase cancelled successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to cancel purchase: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Receive purchase items
     */
    public function receiveItems(Request $request, int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.receive');

        $purchase = $this->applyTenantScope(Purchase::query())->find($id);

        if (!$purchase) {
            return $this->errorResponse('Purchase not found', 404);
        }

        if (!$purchase->canReceiveItems()) {
            return $this->errorResponse('Purchase items cannot be received in current status', 400);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.purchase_item_id' => 'required|exists:purchase_items,purchase_item_id',
            'items.*.quantity_received' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $receivedItems = [];

            foreach ($request->items as $itemData) {
                $success = $purchase->receiveItem($itemData['purchase_item_id'], $itemData['quantity_received']);
                
                if ($success) {
                    $purchaseItem = $purchase->items()->find($itemData['purchase_item_id']);
                    $receivedItems[] = [
                        'product' => $purchaseItem->product->name,
                        'quantity_received' => $itemData['quantity_received'],
                        'variant' => $purchaseItem->variant?->variant_name
                    ];
                } else {
                    throw new \Exception("Failed to receive item {$itemData['purchase_item_id']}");
                }
            }

            $this->logActivity('purchase_items_received', 'purchases', $purchase->purchase_id, [
                'received_items' => $receivedItems
            ]);

            DB::commit();

            $purchase->load([
                'supplier:id,supplier_id,name,contact_person,email,phone_number,rating',
                'warehouse:id,warehouse_id,name,status,address',
                'shop:id,shop_id,name,status,address',
                'items:id,purchase_id,product_id,variant_id,quantity_ordered,quantity_received,unit_cost,total_cost',
                'items.product:id,product_id,name,sku,status',
                'items.variant:id,variant_id,product_id,name,sku'
            ]);

            return $this->successResponse([
                'purchase' => $purchase,
                'received_items' => $receivedItems
            ], 'Purchase items received successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to receive items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Receive all items
     */
    public function receiveAllItems(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.receive');

        $purchase = $this->applyTenantScope(Purchase::query())->find($id);

        if (!$purchase) {
            return $this->errorResponse('Purchase not found', 404);
        }

        if (!$purchase->canReceiveItems()) {
            return $this->errorResponse('Purchase items cannot be received in current status', 400);
        }

        try {
            DB::beginTransaction();

            $purchase->receiveAllItems();
            
            $this->logActivity('purchase_all_items_received', 'purchases', $purchase->purchase_id);

            DB::commit();

            return $this->successResponse($purchase, 'All purchase items received successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to receive all items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark purchase as paid
     */
    public function markPaid(int $id): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.manage_payment');

        $purchase = $this->applyTenantScope(Purchase::query())->find($id);

        if (!$purchase) {
            return $this->errorResponse('Purchase not found', 404);
        }

        try {
            DB::beginTransaction();

            $purchase->markAsPaid();
            $this->logActivity('purchase_payment_made', 'purchases', $purchase->purchase_id);

            DB::commit();

            return $this->successResponse($purchase, 'Purchase marked as paid successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to mark purchase as paid: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get purchase statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $this->requireTenant();
        $this->requirePermission('purchases.view');

        $query = $this->applyTenantScope(Purchase::query());

        // Date range filter for stats
        if ($request->has('date_from')) {
            $query->whereDate('order_date', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('order_date', '<=', $request->get('date_to'));
        }

        $stats = [
            'total_purchases' => $query->count(),
            'draft_purchases' => $query->draft()->count(),
            'pending_purchases' => $query->pending()->count(),
            'confirmed_purchases' => $query->confirmed()->count(),
            'completed_purchases' => $query->completed()->count(),
            'cancelled_purchases' => $query->cancelled()->count(),
            'total_amount' => $query->sum('total_amount'),
            'pending_amount' => $query->whereIn('status', ['pending', 'confirmed', 'partially_received'])->sum('total_amount'),
            'paid_amount' => $query->paid()->sum('total_amount'),
            'overdue_purchases' => $query->overdue()->count(),
            'purchases_by_supplier' => $query->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.supplier_id')
                ->selectRaw('suppliers.supplier_id, suppliers.name, COUNT(*) as count, SUM(purchases.total_amount) as total')
                ->groupBy('suppliers.supplier_id', 'suppliers.name')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get()
        ];

        return $this->successResponse($stats, 'Purchase statistics retrieved successfully');
    }

    /**
     * Generate unique purchase number
     */
    private function generatePurchaseNumber(): string
    {
        $date = now()->format('Ymd');
        $count = $this->applyTenantScope(Purchase::query())
            ->whereDate('created_at', now())
            ->count() + 1;
            
        return "PO-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
