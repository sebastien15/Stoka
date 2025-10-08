<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceController extends BaseController
{
    /**
     * Get database performance metrics
     */
    public function metrics(): JsonResponse
    {
        $this->requireTenant();

        $metrics = [
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queries' => $this->getQueryMetrics(),
            'recommendations' => $this->getPerformanceRecommendations()
        ];

        return $this->successResponse($metrics, 'Performance metrics retrieved successfully');
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        $connection = DB::connection();
        
        return [
            'connection' => $connection->getDriverName(),
            'database' => $connection->getDatabaseName(),
            'version' => $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            'max_connections' => $connection->select('SHOW VARIABLES LIKE "max_connections"')[0]->Value ?? 'Unknown',
            'slow_query_log' => $connection->select('SHOW VARIABLES LIKE "slow_query_log"')[0]->Value ?? 'Unknown',
            'slow_query_time' => $connection->select('SHOW VARIABLES LIKE "long_query_time"')[0]->Value ?? 'Unknown'
        ];
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics(): array
    {
        $driver = config('cache.default');
        
        return [
            'driver' => $driver,
            'status' => Cache::getStore()->getStore() ? 'Connected' : 'Disconnected',
            'memory_usage' => $this->getMemoryUsage()
        ];
    }

    /**
     * Get query performance metrics
     */
    private function getQueryMetrics(): array
    {
        $connection = DB::connection();
        
        // Get slow query log if available
        $slowQueries = [];
        try {
            $slowQueries = $connection->select('
                SELECT 
                    sql_text,
                    exec_count,
                    avg_timer_wait/1000000000 as avg_time_seconds,
                    max_timer_wait/1000000000 as max_time_seconds
                FROM performance_schema.events_statements_summary_by_digest 
                WHERE avg_timer_wait > 5000000000 
                ORDER BY avg_timer_wait DESC 
                LIMIT 10
            ');
        } catch (\Exception $e) {
            // Performance schema not available
        }

        return [
            'slow_queries' => $slowQueries,
            'total_queries' => $this->getTotalQueries(),
            'average_query_time' => $this->getAverageQueryTime()
        ];
    }

    /**
     * Get performance recommendations
     */
    private function getPerformanceRecommendations(): array
    {
        $recommendations = [];

        // Check for missing indexes
        $missingIndexes = $this->checkMissingIndexes();
        if (!empty($missingIndexes)) {
            $recommendations[] = [
                'type' => 'index',
                'priority' => 'high',
                'message' => 'Missing indexes detected',
                'details' => $missingIndexes
            ];
        }

        // Check for N+1 queries
        $nPlusOneQueries = $this->checkNPlusOneQueries();
        if (!empty($nPlusOneQueries)) {
            $recommendations[] = [
                'type' => 'n_plus_one',
                'priority' => 'medium',
                'message' => 'Potential N+1 queries detected',
                'details' => $nPlusOneQueries
            ];
        }

        // Check cache usage
        $cacheRecommendations = $this->checkCacheUsage();
        if (!empty($cacheRecommendations)) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'medium',
                'message' => 'Cache optimization opportunities',
                'details' => $cacheRecommendations
            ];
        }

        return $recommendations;
    }

    /**
     * Check for missing indexes
     */
    private function checkMissingIndexes(): array
    {
        $issues = [];

        // Check products table
        $productQueries = [
            'SELECT * FROM products WHERE tenant_id = ? AND status = ?',
            'SELECT * FROM products WHERE tenant_id = ? AND category_id = ?',
            'SELECT * FROM products WHERE tenant_id = ? AND stock_quantity <= min_stock_level'
        ];

        foreach ($productQueries as $query) {
            if (!$this->hasIndexForQuery($query)) {
                $issues[] = "Missing index for query: {$query}";
            }
        }

        return $issues;
    }

    /**
     * Check for N+1 queries
     */
    private function checkNPlusOneQueries(): array
    {
        // This would need to be implemented with query analysis
        return [];
    }

    /**
     * Check cache usage
     */
    private function checkCacheUsage(): array
    {
        $recommendations = [];

        // Check if frequently accessed data is cached
        $frequentlyAccessed = [
            'dashboard_stats',
            'product_categories',
            'supplier_list',
            'warehouse_list'
        ];

        foreach ($frequentlyAccessed as $key) {
            if (!Cache::has("tenant_{$this->tenant->tenant_id}_{$key}")) {
                $recommendations[] = "Consider caching: {$key}";
            }
        }

        return $recommendations;
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage(): string
    {
        $bytes = memory_get_usage(true);
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get total queries executed
     */
    private function getTotalQueries(): int
    {
        try {
            $result = DB::select('SHOW STATUS LIKE "Queries"');
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get average query time
     */
    private function getAverageQueryTime(): float
    {
        try {
            $result = DB::select('SHOW STATUS LIKE "Uptime"');
            $uptime = $result[0]->Value ?? 1;
            $queries = $this->getTotalQueries();
            
            return $queries > 0 ? $uptime / $queries : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check if query has appropriate index
     */
    private function hasIndexForQuery(string $query): bool
    {
        // This is a simplified check - in reality, you'd analyze the query plan
        return true; // Placeholder
    }
}
