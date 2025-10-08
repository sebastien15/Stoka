<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to purchases table
        Schema::table('purchases', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'purchases_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'supplier_id', 'status'], 'purchases_tenant_id_supplier_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'warehouse_id'], 'purchases_tenant_id_warehouse_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'shop_id'], 'purchases_tenant_id_shop_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'order_date'], 'purchases_tenant_id_order_date_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'expected_delivery_date'], 'purchases_tenant_id_expected_delivery_date_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'actual_delivery_date'], 'purchases_tenant_id_actual_delivery_date_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'payment_status'], 'purchases_tenant_id_payment_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'created_by'], 'purchases_tenant_id_created_by_index');
        });

        // Add indexes to purchase_items table
        Schema::table('purchase_items', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id', 'purchase_id'], 'purchase_items_tenant_id_purchase_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'product_id'], 'purchase_items_tenant_id_product_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'purchase_id', 'product_id'], 'purchase_items_tenant_id_purchase_id_product_id_index');
        });

        // Add indexes to customer_profiles table
        Schema::table('customer_profiles', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['customer_id'], 'customer_profiles_customer_id_index');
            $this->addIndexIfColumnExists($table, ['loyalty_points'], 'customer_profiles_loyalty_points_index');
            $this->addIndexIfColumnExists($table, ['created_at'], 'customer_profiles_created_at_index');
        });

        // Add indexes to expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'expenses_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'category'], 'expenses_tenant_id_category_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'expense_date'], 'expenses_tenant_id_expense_date_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'warehouse_id'], 'expenses_tenant_id_warehouse_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'shop_id'], 'expenses_tenant_id_shop_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'created_by'], 'expenses_tenant_id_created_by_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'approved_by'], 'expenses_tenant_id_approved_by_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'amount'], 'expenses_tenant_id_amount_index');
        });

        // Add indexes to audit_logs table
        Schema::table('audit_logs', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id'], 'audit_logs_tenant_id_index');
            $this->addIndexIfColumnExists($table, ['user_id'], 'audit_logs_user_id_index');
            $this->addIndexIfColumnExists($table, ['action'], 'audit_logs_action_index');
            $this->addIndexIfColumnExists($table, ['table_name'], 'audit_logs_table_name_index');
            $this->addIndexIfColumnExists($table, ['created_at'], 'audit_logs_created_at_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'action'], 'audit_logs_tenant_id_action_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'table_name'], 'audit_logs_tenant_id_table_name_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'created_at'], 'audit_logs_tenant_id_created_at_index');
        });

        // Add indexes to user_sessions table
        Schema::table('user_sessions', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id'], 'user_sessions_tenant_id_index');
            $this->addIndexIfColumnExists($table, ['user_id'], 'user_sessions_user_id_index');
            $this->addIndexIfColumnExists($table, ['is_active'], 'user_sessions_is_active_index');
            $this->addIndexIfColumnExists($table, ['login_at'], 'user_sessions_login_at_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'user_id'], 'user_sessions_tenant_id_user_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'is_active'], 'user_sessions_tenant_id_is_active_index');
        });

        // Add indexes to product_variants table
        Schema::table('product_variants', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id'], 'product_variants_tenant_id_index');
            $this->addIndexIfColumnExists($table, ['product_id'], 'product_variants_product_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'product_id'], 'product_variants_tenant_id_product_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'product_variants_tenant_id_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'sku'], 'product_variants_tenant_id_sku_index');
        });

        // Add indexes to notice_events table
        Schema::table('notice_events', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id'], 'notice_events_tenant_id_index');
            $this->addIndexIfColumnExists($table, ['user_id'], 'notice_events_user_id_index');
            $this->addIndexIfColumnExists($table, ['event_type'], 'notice_events_event_type_index');
            $this->addIndexIfColumnExists($table, ['is_read'], 'notice_events_is_read_index');
            $this->addIndexIfColumnExists($table, ['created_at'], 'notice_events_created_at_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'user_id'], 'notice_events_tenant_id_user_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'is_read'], 'notice_events_tenant_id_is_read_index');
        });

        // Add indexes to roles table
        Schema::table('roles', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id'], 'roles_tenant_id_index');
            $this->addIndexIfColumnExists($table, ['name'], 'roles_name_index');
            $this->addIndexIfColumnExists($table, ['status'], 'roles_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'name'], 'roles_tenant_id_name_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'roles_tenant_id_status_index');
        });

        // Add indexes to role_permissions table
        Schema::table('role_permissions', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['role_id'], 'role_permissions_role_id_index');
            $this->addIndexIfColumnExists($table, ['permission'], 'role_permissions_permission_index');
            $this->addIndexIfColumnExists($table, ['role_id', 'permission'], 'role_permissions_role_id_permission_index');
        });

        // Add indexes to subscription_plans table
        Schema::table('subscription_plans', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['name'], 'subscription_plans_name_index');
            $this->addIndexIfColumnExists($table, ['status'], 'subscription_plans_status_index');
            $this->addIndexIfColumnExists($table, ['price'], 'subscription_plans_price_index');
        });

        // Add indexes to system_configurations table
        Schema::table('system_configurations', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['config_key'], 'system_configurations_config_key_index');
            $this->addIndexIfColumnExists($table, ['config_group'], 'system_configurations_config_group_index');
            $this->addIndexIfColumnExists($table, ['is_active'], 'system_configurations_is_active_index');
        });

        // Add indexes to system_features table
        Schema::table('system_features', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['feature_name'], 'system_features_feature_name_index');
            $this->addIndexIfColumnExists($table, ['is_active'], 'system_features_is_active_index');
        });

        // Add indexes to tenant_billing_histories table
        Schema::table('tenant_billing_histories', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id'], 'tenant_billing_histories_tenant_id_index');
            $this->addIndexIfColumnExists($table, ['billing_date'], 'tenant_billing_histories_billing_date_index');
            $this->addIndexIfColumnExists($table, ['status'], 'tenant_billing_histories_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'billing_date'], 'tenant_billing_histories_tenant_id_billing_date_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'tenant_billing_histories_tenant_id_status_index');
        });

        // Add indexes to tenant_features table
        Schema::table('tenant_features', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id'], 'tenant_features_tenant_id_index');
            $this->addIndexIfColumnExists($table, ['feature_id'], 'tenant_features_feature_id_index');
            $this->addIndexIfColumnExists($table, ['is_enabled'], 'tenant_features_is_enabled_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'feature_id'], 'tenant_features_tenant_id_feature_id_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'is_enabled'], 'tenant_features_tenant_id_is_enabled_index');
        });

        // Add indexes to brands table
        Schema::table('brands', function (Blueprint $table) {
            $this->addIndexIfColumnExists($table, ['tenant_id'], 'brands_tenant_id_index');
            $this->addIndexIfColumnExists($table, ['name'], 'brands_name_index');
            $this->addIndexIfColumnExists($table, ['status'], 'brands_status_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'name'], 'brands_tenant_id_name_index');
            $this->addIndexIfColumnExists($table, ['tenant_id', 'status'], 'brands_tenant_id_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes from all tables
        $this->dropIndexesFromTable('purchases', [
            'purchases_tenant_id_status_index',
            'purchases_tenant_id_supplier_id_status_index',
            'purchases_tenant_id_warehouse_id_index',
            'purchases_tenant_id_shop_id_index',
            'purchases_tenant_id_order_date_index',
            'purchases_tenant_id_expected_delivery_date_index',
            'purchases_tenant_id_actual_delivery_date_index',
            'purchases_tenant_id_payment_status_index',
            'purchases_tenant_id_created_by_index'
        ]);

        $this->dropIndexesFromTable('purchase_items', [
            'purchase_items_tenant_id_purchase_id_index',
            'purchase_items_tenant_id_product_id_index',
            'purchase_items_tenant_id_purchase_id_product_id_index'
        ]);

        $this->dropIndexesFromTable('customer_profiles', [
            'customer_profiles_customer_id_index',
            'customer_profiles_loyalty_points_index',
            'customer_profiles_created_at_index'
        ]);

        $this->dropIndexesFromTable('expenses', [
            'expenses_tenant_id_status_index',
            'expenses_tenant_id_category_index',
            'expenses_tenant_id_expense_date_index',
            'expenses_tenant_id_warehouse_id_index',
            'expenses_tenant_id_shop_id_index',
            'expenses_tenant_id_created_by_index',
            'expenses_tenant_id_approved_by_index',
            'expenses_tenant_id_amount_index'
        ]);

        $this->dropIndexesFromTable('audit_logs', [
            'audit_logs_tenant_id_index',
            'audit_logs_user_id_index',
            'audit_logs_action_index',
            'audit_logs_table_name_index',
            'audit_logs_created_at_index',
            'audit_logs_tenant_id_action_index',
            'audit_logs_tenant_id_table_name_index',
            'audit_logs_tenant_id_created_at_index'
        ]);

        $this->dropIndexesFromTable('user_sessions', [
            'user_sessions_tenant_id_index',
            'user_sessions_user_id_index',
            'user_sessions_is_active_index',
            'user_sessions_login_at_index',
            'user_sessions_tenant_id_user_id_index',
            'user_sessions_tenant_id_is_active_index'
        ]);

        $this->dropIndexesFromTable('product_variants', [
            'product_variants_tenant_id_index',
            'product_variants_product_id_index',
            'product_variants_tenant_id_product_id_index',
            'product_variants_tenant_id_status_index',
            'product_variants_tenant_id_sku_index'
        ]);

        $this->dropIndexesFromTable('notice_events', [
            'notice_events_tenant_id_index',
            'notice_events_user_id_index',
            'notice_events_event_type_index',
            'notice_events_is_read_index',
            'notice_events_created_at_index',
            'notice_events_tenant_id_user_id_index',
            'notice_events_tenant_id_is_read_index'
        ]);

        $this->dropIndexesFromTable('roles', [
            'roles_tenant_id_index',
            'roles_name_index',
            'roles_status_index',
            'roles_tenant_id_name_index',
            'roles_tenant_id_status_index'
        ]);

        $this->dropIndexesFromTable('role_permissions', [
            'role_permissions_role_id_index',
            'role_permissions_permission_index',
            'role_permissions_role_id_permission_index'
        ]);

        $this->dropIndexesFromTable('subscription_plans', [
            'subscription_plans_name_index',
            'subscription_plans_status_index',
            'subscription_plans_price_index'
        ]);

        $this->dropIndexesFromTable('system_configurations', [
            'system_configurations_config_key_index',
            'system_configurations_config_group_index',
            'system_configurations_is_active_index'
        ]);

        $this->dropIndexesFromTable('system_features', [
            'system_features_feature_name_index',
            'system_features_is_active_index'
        ]);

        $this->dropIndexesFromTable('tenant_billing_histories', [
            'tenant_billing_histories_tenant_id_index',
            'tenant_billing_histories_billing_date_index',
            'tenant_billing_histories_status_index',
            'tenant_billing_histories_tenant_id_billing_date_index',
            'tenant_billing_histories_tenant_id_status_index'
        ]);

        $this->dropIndexesFromTable('tenant_features', [
            'tenant_features_tenant_id_index',
            'tenant_features_feature_id_index',
            'tenant_features_is_enabled_index',
            'tenant_features_tenant_id_feature_id_index',
            'tenant_features_tenant_id_is_enabled_index'
        ]);

        $this->dropIndexesFromTable('brands', [
            'brands_tenant_id_index',
            'brands_name_index',
            'brands_status_index',
            'brands_tenant_id_name_index',
            'brands_tenant_id_status_index'
        ]);
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

    /**
     * Drop indexes from table
     */
    private function dropIndexesFromTable(string $tableName, array $indexNames): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($indexNames) {
            foreach ($indexNames as $indexName) {
                $this->dropIndexIfExists($table, $indexName);
            }
        });
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
};
