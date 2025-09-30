<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class GrantWarehousePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:grant-warehouses {--tenant_id=} {--user_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant warehouses.* permissions to tenant_admin users (optionally scoped by tenant_id or specific user_id)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant_id');
        $userId = $this->option('user_id');

        $permissionsToAdd = [
            'warehouses.view',
            'warehouses.create',
            'warehouses.edit',
            'warehouses.delete',
        ];

        $query = User::query();

        if ($userId) {
            $query->where('user_id', (int) $userId);
        } else {
            $query->where('role', 'tenant_admin');
            if ($tenantId) {
                $query->where('tenant_id', (int) $tenantId);
            }
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No matching users found.');
            return Command::SUCCESS;
        }

        $updated = 0;
        foreach ($users as $user) {
            $current = $user->getPermissions();
            $merged = array_values(array_unique(array_merge($current, $permissionsToAdd)));
            $user->permissions = $merged;
            $user->save();
            $updated++;
            $this->info("Updated user {$user->user_id} ({$user->email}) permissions.");
        }

        $this->info("Done. Updated permissions for {$updated} user(s).");
        return Command::SUCCESS;
    }
}



