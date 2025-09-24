<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserPinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Clear all existing PINs first
        $this->command->info('Clearing all existing PINs...');
        User::query()->update(['pin' => null]);

        // 2. Define role-based PINs for easy testing (include super_admin)
        $rolePins = [
            'super_admin' => '9999',
            'tenant_admin' => '1234',
            'admin' => '5678',
            'warehouse_manager' => '9012',
            'shop_manager' => '3456',
            'employee' => '7890',
            'customer' => '1111'
        ];

        // 3. Assign PIN only to the FIRST user of each role
        foreach ($rolePins as $role => $pin) {
            $user = User::where('role', $role)
                ->where('is_active', true)
                ->orderBy('user_id')
                ->first();

            if ($user) {
                $user->update([
                    'pin' => Hash::make($pin)
                ]);
                
                $this->command->info("✅ Set {$role} -> {$user->full_name} ({$user->email}) with PIN: {$pin}");
            } else {
                $this->command->warn("⚠️  No active user found for role: {$role}");
            }
        }

        $this->command->info('PIN seeding completed!');
        $this->command->info('Test PINs by role:');
        $this->command->info('- Super Admin: 9999');
        $this->command->info('- Tenant Admin: 1234');
        $this->command->info('- Admin: 5678');
        $this->command->info('- Warehouse Manager: 9012');
        $this->command->info('- Shop Manager: 3456');
        $this->command->info('- Employee: 7890');
        $this->command->info('- Customer: 1111');
    }
}