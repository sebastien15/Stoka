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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\AuditController;

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
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Dashboard routes
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    
    // Product routes
    Route::apiResource('products', ProductController::class);
    Route::post('products/{id}/stock', [ProductController::class, 'updateStock']);
    Route::get('products/{id}/variants', [ProductController::class, 'variants']);
    Route::get('products/low-stock', [ProductController::class, 'lowStock']);
    Route::get('products/needs-reorder', [ProductController::class, 'needsReorder']);
    Route::get('products/stats', [ProductController::class, 'stats']);
    Route::post('products/bulk-action', [ProductController::class, 'bulkAction']);
    
    // Category routes
    Route::apiResource('categories', CategoryController::class);
    Route::get('categories/hierarchy', [CategoryController::class, 'hierarchy']);
    Route::get('categories/roots', [CategoryController::class, 'roots']);
    Route::get('categories/{id}/children', [CategoryController::class, 'children']);
    Route::post('categories/{id}/activate', [CategoryController::class, 'activate']);
    Route::post('categories/{id}/deactivate', [CategoryController::class, 'deactivate']);
    Route::get('categories/stats', [CategoryController::class, 'stats']);
    Route::post('categories/bulk-action', [CategoryController::class, 'bulkAction']);
    
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
    Route::apiResource('users', UserController::class);
    
    // Tenant routes
    Route::apiResource('tenants', TenantController::class);
    
    // Expense routes
    Route::apiResource('expenses', ExpenseController::class);
    
    // Notice routes
    Route::apiResource('notices', NoticeController::class);
    
    // Audit routes
    Route::get('audit-logs', [AuditController::class, 'index']);
    Route::get('audit-logs/{id}', [AuditController::class, 'show']);
    
    // User profile
    Route::get('user', function (Request $request) {
        return $request->user();
    });
});
