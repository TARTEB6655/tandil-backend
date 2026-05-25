<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {email : New admin email (must not exist yet)}
                            {--password= : Password (min 8 chars; prompted if omitted)}
                            {--name= : Display name}
                            {--phone= : Unique phone (auto-assigned if omitted)}';

    protected $description = 'Create an additional admin user without changing existing admins';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email is required.');

            return 1;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists. Use a different email.");
            $this->line('Existing admins are not modified. To change a password use: php artisan admin:reset {email}');

            return 1;
        }

        $password = $this->option('password') ?? $this->secret('Enter password for new admin (min 8 characters)');
        if (strlen((string) $password) < 8) {
            $this->error('Password must be at least 8 characters long.');

            return 1;
        }

        $name = trim((string) ($this->option('name') ?: 'Administrator'));
        if ($name === '') {
            $name = 'Administrator';
        }

        $phone = $this->resolvePhone($this->option('phone'));
        if ($phone === null) {
            return 1;
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('admin');
        }

        $this->info('New admin user created (existing admins unchanged).');
        $this->line('');
        $this->line('  Name:     '.$user->name);
        $this->line('  Email:    '.$user->email);
        $this->line('  Phone:    '.$user->phone);
        $this->line('  Password: '.($this->option('password') ? '***' : '[as entered]'));
        $this->line('  Role:     '.$user->role);
        $this->line('  Status:   '.$user->status);

        return 0;
    }

    private function resolvePhone(?string $phone): ?string
    {
        if ($phone !== null && $phone !== '') {
            $phone = trim($phone);
            if (User::where('phone', $phone)->exists()) {
                $this->error("Phone {$phone} is already in use. Choose another --phone value.");

                return null;
            }

            return $phone;
        }

        for ($n = 100; $n <= 99999; $n++) {
            $candidate = '70000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            if (! User::where('phone', $candidate)->exists()) {
                return $candidate;
            }
        }

        $this->error('Could not find a free phone number. Pass --phone= manually.');

        return null;
    }
}
