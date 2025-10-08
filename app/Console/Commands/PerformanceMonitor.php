<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:monitor {--threshold=50 : Query time threshold in milliseconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and report slow database queries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threshold = $this->option('threshold');
        
        $this->info("Monitoring queries slower than {$threshold}ms...");
        
        // Enable query logging
        DB::listen(function ($query) use ($threshold) {
            if ($query->time > $threshold) {
                $this->warn("SLOW QUERY DETECTED: {$query->time}ms");
                $this->line("SQL: {$query->sql}");
                $this->line("Bindings: " . json_encode($query->bindings));
                $this->line("---");
                
                // Log to file
                Log::warning("Slow Query Detected", [
                    'time' => $query->time,
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'threshold' => $threshold
                ]);
            }
        });
        
        $this->info("Performance monitoring started. Press Ctrl+C to stop.");
        
        // Keep the command running
        while (true) {
            sleep(1);
        }
    }
}
