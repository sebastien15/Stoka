<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PerformanceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('login-pin', [AuthController::class, 'loginWithPin']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);
});
// Protected routes
Route::middleware([])->group(function () {
    
    // Dashboard routes
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    
    // Product routes (define specific first to avoid show() catching strings like 'stats')
    Route::get('products/low-stock', [ProductController::class, 'lowStock']);
    Route::get('products/needs-reorder', [ProductController::class, 'needsReorder']);
    Route::get('products/stats', [ProductController::class, 'stats']);
    Route::post('products/bulk-action', [ProductController::class, 'bulkAction']);
    Route::post('products/{id}/stock', [ProductController::class, 'updateStock'])->whereNumber('id');
    Route::get('products/{id}/variants', [ProductController::class, 'variants'])->whereNumber('id');
    Route::apiResource('products', ProductController::class)->parameters([
        'products' => 'id'
    ])->where(['id' => '[0-9]+']);
    
    // Category routes (define specific routes BEFORE apiResource to avoid shadowing)
    Route::get('categories/hierarchy', [CategoryController::class, 'hierarchy']);
    Route::get('categories/roots', [CategoryController::class, 'roots']);
    Route::get('categories/stats', [CategoryController::class, 'stats']);
    Route::get('categories/{id}/children', [CategoryController::class, 'children'])->whereNumber('id');
    Route::post('categories/{id}/activate', [CategoryController::class, 'activate'])->whereNumber('id');
    Route::post('categories/{id}/deactivate', [CategoryController::class, 'deactivate'])->whereNumber('id');
    Route::post('categories/bulk-action', [CategoryController::class, 'bulkAction']);
    Route::apiResource('categories', CategoryController::class)->parameters([
        'categories' => 'id'
    ])->where(['id' => '[0-9]+']);
    
    // Supplier routes
    Route::apiResource('suppliers', SupplierController::class);
    Route::get('suppliers/{id}/products', [SupplierController::class, 'products']);
    Route::get('suppliers/{id}/purchases', [SupplierController::class, 'purchases']);
    Route::post('suppliers/{id}/rating', [SupplierController::class, 'updateRating']);
    Route::get('suppliers/stats', [SupplierController::class, 'stats']);
    
    // Brand routes
    Route::apiResource('brands', BrandController::class);
    
    // Order routes
    Route::apiResource('orders', OrderController::class);
    
    // Purchase routes
    Route::apiResource('purchases', PurchaseController::class);
    
    // Inventory routes
    Route::apiResource('inventory', InventoryController::class);
    
    // Warehouse routes
    Route::apiResource('warehouses', WarehouseController::class);
    
    // Shop routes
    Route::apiResource('shops', ShopController::class);
    
    // User routes
    Route::get('permissions-catalog', [UserController::class, 'permissionsCatalog']);
    Route::get('users/{id}/permissions', [UserController::class, 'permissions'])->whereNumber('id');
    Route::post('users/{id}/permissions', [UserController::class, 'updatePermissions'])->whereNumber('id');
    Route::apiResource('users', UserController::class);

    // Customer routes
    Route::get('customers/stats', [CustomerController::class, 'stats']);
    Route::get('customers/{id}/orders', [CustomerController::class, 'orders'])->whereNumber('id');
    Route::post('customers/{id}/activate', [CustomerController::class, 'activate'])->whereNumber('id');
    Route::post('customers/{id}/deactivate', [CustomerController::class, 'deactivate'])->whereNumber('id');
    Route::post('customers/{id}/loyalty-points', [CustomerController::class, 'updateLoyaltyPoints'])->whereNumber('id');
    Route::apiResource('customers', CustomerController::class);

    // Role routes
    Route::get('roles/permissions-catalog', [RoleController::class, 'permissionsCatalog']);
    Route::get('roles/{id}/permissions', [RoleController::class, 'permissions'])->whereNumber('id');
    Route::post('roles/{id}/permissions', [RoleController::class, 'updatePermissions'])->whereNumber('id');
    Route::apiResource('roles', RoleController::class);
    
    // Tenant routes
    Route::apiResource('tenants', TenantController::class);
    Route::get('tenants/{id}/stats', [TenantController::class, 'tenantStats']);
    
    // Expense routes
    Route::apiResource('expenses', ExpenseController::class);
    
    // Notice routes
    Route::apiResource('notices', NoticeController::class);
    
    // Audit routes
    Route::get('audit-logs', [AuditController::class, 'index']);
    Route::get('audit-logs/{id}', [AuditController::class, 'show']);
    
    // Performance monitoring routes
    Route::get('performance/metrics', [PerformanceController::class, 'metrics']);
    
    // User profile
    Route::get('user', function (Request $request) {
        return $request->user();
    });
});
