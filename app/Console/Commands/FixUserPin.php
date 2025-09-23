<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FixUserPin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:fix-pin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix user PIN by properly hashing it';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find users with non-null PIN
        $users = User::whereNotNull('pin')->get();
        
        if ($users->isEmpty()) {
            $this->error('No users with PIN found');
            return;
        }

        foreach ($users as $user) {
            $this->info("Found user: {$user->full_name} ({$user->email})");
            
            // Check if PIN is already hashed (starts with $2y$)
            $currentPin = $user->getRawOriginal('pin');
            
            if (!str_starts_with($currentPin, '$2y$')) {
                $this->info("PIN is not hashed, fixing...");
                
                // Assume the current PIN is '1234' and hash it
                $user->pin = Hash::make('1234');
                $user->save();
                
                $this->info("✅ Updated PIN for {$user->full_name}");
            } else {
                $this->info("✅ PIN is already properly hashed for {$user->full_name}");
            }
        }
        
        $this->info('Done! You can now test with PIN: 1234');
    }
}