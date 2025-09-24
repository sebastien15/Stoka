<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'superadmin@example.com';
        $tenant = Tenant::first();

        if (!$tenant) {
            $this->command?->warn('No tenants found. Skipping Super Admin creation.');
            return;
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            $this->command?->info('Super Admin already exists: '.$email);
            return;
        }

        $user = User::create([
            'tenant_id' => $tenant->tenant_id,
            'full_name' => 'Super Admin',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'is_active' => true,
            'access_level' => 'full',
            'permissions' => [],
        ]);

        $user->pin = Hash::make('9999');
        $user->save();

        $this->command?->info('Created Super Admin: superadmin@example.com / password123 / PIN 9999');
    }
}
