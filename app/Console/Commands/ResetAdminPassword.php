<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:password
                            {password=adminpassword : The new password for admin user 0000.00000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change the password for admin user with NIP 0000.00000';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $password = trim($this->argument('password'));

        if ($password === '') {
            $this->error('Password cannot be empty.');
            return self::FAILURE;
        }

        $user = User::firstOrNew(['nip' => '0000.00000']);
        $isNew = ! $user->exists;

        if ($isNew) {
            $user->name = 'Admin';
            $user->email = $user->email ?: null;
            $user->status = $user->status ?: 'active';
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info($isNew ? '✅ Admin user created and password set successfully.' : '✅ Admin password updated successfully.');
        $this->line('NIP: 0000.00000');
        $this->line("New password: {$password}");

        return self::SUCCESS;
    }
}
