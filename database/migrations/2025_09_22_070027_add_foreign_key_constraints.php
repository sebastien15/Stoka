<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add foreign key constraints after all tables are created (guarded)
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (Schema::hasColumn('warehouses', 'manager_id')) {
                    $table->foreign('manager_id')->references('user_id')->on('users')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                if (Schema::hasColumn('shops', 'manager_id')) {
                    $table->foreign('manager_id')->references('user_id')->on('users')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'warehouse_id')) {
                    $table->foreign('warehouse_id')->references('warehouse_id')->on('warehouses')->onDelete('set null');
                }
                if (Schema::hasColumn('users', 'shop_id')) {
                    $table->foreign('shop_id')->references('shop_id')->on('shops')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'category_id')) {
                    $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('restrict');
                }
                if (Schema::hasColumn('products', 'brand_id')) {
                    $table->foreign('brand_id')->references('brand_id')->on('brands')->onDelete('set null');
                }
                if (Schema::hasColumn('products', 'supplier_id')) {
                    $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->onDelete('set null');
                }
                if (Schema::hasColumn('products', 'shop_id')) {
                    $table->foreign('shop_id')->references('shop_id')->on('shops')->onDelete('restrict');
                }
                if (Schema::hasColumn('products', 'warehouse_id')) {
                    $table->foreign('warehouse_id')->references('warehouse_id')->on('warehouses')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
                if (Schema::hasColumn('orders', 'customer_id')) {
                    $table->foreign('customer_id')->references('user_id')->on('users')->onDelete('restrict');
                }
                if (Schema::hasColumn('orders', 'shop_id')) {
                    $table->foreign('shop_id')->references('shop_id')->on('shops')->onDelete('restrict');
                }
                if (Schema::hasColumn('orders', 'warehouse_id')) {
                    $table->foreign('warehouse_id')->references('warehouse_id')->on('warehouses')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'order_id')) {
                    $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('cascade');
                }
                if (Schema::hasColumn('order_items', 'product_id')) {
                    $table->foreign('product_id')->references('product_id')->on('products')->onDelete('restrict');
                }
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (Schema::hasColumn('purchases', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
                if (Schema::hasColumn('purchases', 'supplier_id')) {
                    $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->onDelete('restrict');
                }
                if (Schema::hasColumn('purchases', 'warehouse_id')) {
                    $table->foreign('warehouse_id')->references('warehouse_id')->on('warehouses')->onDelete('set null');
                }
                if (Schema::hasColumn('purchases', 'shop_id')) {
                    $table->foreign('shop_id')->references('shop_id')->on('shops')->onDelete('set null');
                }
                if (Schema::hasColumn('purchases', 'created_by')) {
                    $table->foreign('created_by')->references('user_id')->on('users')->onDelete('restrict');
                }
            });
        }

        if (Schema::hasTable('purchase_items')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_items', 'purchase_id')) {
                    $table->foreign('purchase_id')->references('purchase_id')->on('purchases')->onDelete('cascade');
                }
                if (Schema::hasColumn('purchase_items', 'product_id')) {
                    $table->foreign('product_id')->references('product_id')->on('products')->onDelete('restrict');
                }
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('audit_logs', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
                if (Schema::hasColumn('audit_logs', 'user_id')) {
                    $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('customer_profiles')) {
            Schema::table('customer_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('customer_profiles', 'customer_id')) {
                    $table->foreign('customer_id')->references('user_id')->on('users')->onDelete('cascade');
                }
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (Schema::hasColumn('expenses', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
                if (Schema::hasColumn('expenses', 'warehouse_id')) {
                    $table->foreign('warehouse_id')->references('warehouse_id')->on('warehouses')->onDelete('set null');
                }
                if (Schema::hasColumn('expenses', 'shop_id')) {
                    $table->foreign('shop_id')->references('shop_id')->on('shops')->onDelete('set null');
                }
                if (Schema::hasColumn('expenses', 'created_by')) {
                    $table->foreign('created_by')->references('user_id')->on('users')->onDelete('restrict');
                }
                if (Schema::hasColumn('expenses', 'approved_by')) {
                    $table->foreign('approved_by')->references('user_id')->on('users')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_movements', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
                if (Schema::hasColumn('inventory_movements', 'product_id')) {
                    $table->foreign('product_id')->references('product_id')->on('products')->onDelete('restrict');
                }
                if (Schema::hasColumn('inventory_movements', 'product_variant_id')) {
                    $table->foreign('product_variant_id')->references('variant_id')->on('product_variants')->onDelete('set null');
                }
                if (Schema::hasColumn('inventory_movements', 'warehouse_id')) {
                    $table->foreign('warehouse_id')->references('warehouse_id')->on('warehouses')->onDelete('set null');
                }
                if (Schema::hasColumn('inventory_movements', 'shop_id')) {
                    $table->foreign('shop_id')->references('shop_id')->on('shops')->onDelete('set null');
                }
                if (Schema::hasColumn('inventory_movements', 'created_by')) {
                    $table->foreign('created_by')->references('user_id')->on('users')->onDelete('restrict');
                }
            });
        }

        if (Schema::hasTable('notice_events')) {
            Schema::table('notice_events', function (Blueprint $table) {
                if (Schema::hasColumn('notice_events', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
                if (Schema::hasColumn('notice_events', 'created_by')) {
                    $table->foreign('created_by')->references('user_id')->on('users')->onDelete('restrict');
                }
            });
        }

        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                if (Schema::hasColumn('product_variants', 'product_id')) {
                    $table->foreign('product_id')->references('product_id')->on('products')->onDelete('cascade');
                }
            });
        }

        if (Schema::hasTable('system_configurations')) {
            Schema::table('system_configurations', function (Blueprint $table) {
                if (Schema::hasColumn('system_configurations', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
            });
        }

        if (Schema::hasTable('tenant_billing_histories')) {
            Schema::table('tenant_billing_histories', function (Blueprint $table) {
                if (Schema::hasColumn('tenant_billing_histories', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
            });
        }

        if (Schema::hasTable('tenant_features')) {
            Schema::table('tenant_features', function (Blueprint $table) {
                if (Schema::hasColumn('tenant_features', 'tenant_id')) {
                    $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
                }
                if (Schema::hasColumn('tenant_features', 'feature_id')) {
                    $table->foreign('feature_id')->references('feature_id')->on('system_features')->onDelete('cascade');
                }
                if (Schema::hasColumn('tenant_features', 'enabled_by')) {
                    $table->foreign('enabled_by')->references('user_id')->on('users')->onDelete('restrict');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['shop_id']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['shop_id']);
            $table->dropForeign(['warehouse_id']);
        });
    }
};
