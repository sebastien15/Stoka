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
        Schema::table('products', function (Blueprint $table) {
            // Add composite indexes for common queries (only if they don't exist)
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'products_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'category_id'], 'products_tenant_id_category_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'supplier_id'], 'products_tenant_id_supplier_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'shop_id'], 'products_tenant_id_shop_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'warehouse_id'], 'products_tenant_id_warehouse_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'is_featured'], 'products_tenant_id_is_featured_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'is_digital'], 'products_tenant_id_is_digital_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'stock_quantity'], 'products_tenant_id_stock_quantity_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'selling_price'], 'products_tenant_id_selling_price_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'created_at'], 'products_tenant_id_created_at_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'updated_at'], 'products_tenant_id_updated_at_index');
            
            // Add indexes for stock management
            $this->addIndexIfColumnExists($table, ['tenant_id', 'stock_quantity', 'min_stock_level'], 'products_tenant_id_stock_quantity_min_stock_level_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'stock_quantity', 'reorder_point'], 'products_tenant_id_stock_quantity_reorder_point_index');
            
            // Add index for SKU searches
            $this->addIndexIfColumnExists($table, ['tenant_id', 'sku'], 'products_tenant_id_sku_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'barcode'], 'products_tenant_id_barcode_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Add composite indexes for common order queries
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'orders_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'payment_status'], 'orders_tenant_id_payment_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'customer_id', 'status'], 'orders_tenant_id_customer_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'shop_id', 'status'], 'orders_tenant_id_shop_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'order_date'], 'orders_tenant_id_order_date_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'delivered_at'], 'orders_tenant_id_delivered_at_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'total_amount'], 'orders_tenant_id_total_amount_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'payment_method'], 'orders_tenant_id_payment_method_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Add indexes for order items
            $this->addIndexIfColumnExists($table, ['tenant_id', 'order_id'], 'order_items_tenant_id_order_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'product_id'], 'order_items_tenant_id_product_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'order_id', 'product_id'], 'order_items_tenant_id_order_id_product_id_index');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            // Add indexes for inventory tracking
            $this->addIndexIfColumnExists($table, ['tenant_id', 'product_id'], 'inventory_movements_tenant_id_product_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'movement_type'], 'inventory_movements_tenant_id_movement_type_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'created_at'], 'inventory_movements_tenant_id_created_at_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'product_id', 'created_at'], 'inventory_movements_tenant_id_product_id_created_at_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            // Add indexes for category queries (only if columns exist)
            $this->addIndexIfColumnExists($table, ['tenant_id', 'parent_id'], 'categories_tenant_id_parent_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'categories_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'name'], 'categories_tenant_id_name_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            // Add indexes for supplier queries
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'suppliers_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'name'], 'suppliers_tenant_id_name_index');
        });

        Schema::table('shops', function (Blueprint $table) {
            // Add indexes for shop queries
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'shops_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'name'], 'shops_tenant_id_name_index');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            // Add indexes for warehouse queries
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'warehouses_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'name'], 'warehouses_tenant_id_name_index');
        });

        Schema::table('users', function (Blueprint $table) {
            // Add indexes for user queries
            $this->addIndexIfColumnExists($table, ['tenant_id', 'user_type'], 'users_tenant_id_user_type_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'users_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'created_at'], 'users_tenant_id_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'products_tenant_id_status_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_category_id_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_supplier_id_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_shop_id_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_warehouse_id_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_is_featured_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_is_digital_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_stock_quantity_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_selling_price_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_created_at_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_updated_at_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_stock_quantity_min_stock_level_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_stock_quantity_reorder_point_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_sku_index');
            $this->dropIndexIfExists($table, 'products_tenant_id_barcode_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'orders_tenant_id_status_index');
            $this->dropIndexIfExists($table, 'orders_tenant_id_payment_status_index');
            $this->dropIndexIfExists($table, 'orders_tenant_id_customer_id_status_index');
            $this->dropIndexIfExists($table, 'orders_tenant_id_shop_id_status_index');
            $this->dropIndexIfExists($table, 'orders_tenant_id_order_date_index');
            $this->dropIndexIfExists($table, 'orders_tenant_id_delivered_at_index');
            $this->dropIndexIfExists($table, 'orders_tenant_id_total_amount_index');
            $this->dropIndexIfExists($table, 'orders_tenant_id_payment_method_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'order_items_tenant_id_order_id_index');
            $this->dropIndexIfExists($table, 'order_items_tenant_id_product_id_index');
            $this->dropIndexIfExists($table, 'order_items_tenant_id_order_id_product_id_index');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'inventory_movements_tenant_id_product_id_index');
            $this->dropIndexIfExists($table, 'inventory_movements_tenant_id_movement_type_index');
            $this->dropIndexIfExists($table, 'inventory_movements_tenant_id_created_at_index');
            $this->dropIndexIfExists($table, 'inventory_movements_tenant_id_product_id_created_at_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'categories_tenant_id_parent_id_index');
            $this->dropIndexIfExists($table, 'categories_tenant_id_status_index');
            $this->dropIndexIfExists($table, 'categories_tenant_id_name_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'suppliers_tenant_id_status_index');
            $this->dropIndexIfExists($table, 'suppliers_tenant_id_name_index');
        });

        Schema::table('shops', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'shops_tenant_id_status_index');
            $this->dropIndexIfExists($table, 'shops_tenant_id_name_index');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'warehouses_tenant_id_status_index');
            $this->dropIndexIfExists($table, 'warehouses_tenant_id_name_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'users_tenant_id_user_type_index');
            $this->dropIndexIfExists($table, 'users_tenant_id_status_index');
            $this->dropIndexIfExists($table, 'users_tenant_id_created_at_index');
        });
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(Blueprint $table, array $columns, string $indexName): void
    {
        $tableName = $table->getTable();
        
        // Check if index already exists
        $indexes = DB::select("SHOW INDEX FROM {$tableName}");
        $existingIndexes = array_column($indexes, 'Key_name');
        
        if (!in_array($indexName, $existingIndexes)) {
            $table->index($columns, $indexName);
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        $tableName = $table->getTable();
        
        // Check if index exists
        $indexes = DB::select("SHOW INDEX FROM {$tableName}");
        $existingIndexes = array_column($indexes, 'Key_name');
        
        if (in_array($indexName, $existingIndexes)) {
            $table->dropIndex($indexName);
        }
    }

    /**
     * Add index if columns exist and index doesn't exist
     */
    private function addIndexIfColumnExists(Blueprint $table, array $columns, string $indexName): void
    {
        $tableName = $table->getTable();
        
        // Check if all columns exist
        $tableColumns = DB::select("SHOW COLUMNS FROM {$tableName}");
        $existingColumns = array_column($tableColumns, 'Field');
        
        $allColumnsExist = true;
        foreach ($columns as $column) {
            if (!in_array($column, $existingColumns)) {
                $allColumnsExist = false;
                break;
            }
        }
        
        if (!$allColumnsExist) {
            return; // Skip if any column doesn't exist
        }
        
        // Check if index already exists
        $indexes = DB::select("SHOW INDEX FROM {$tableName}");
        $existingIndexes = array_column($indexes, 'Key_name');
        
        if (!in_array($indexName, $existingIndexes)) {
            $table->index($columns, $indexName);
        }
    }
};
