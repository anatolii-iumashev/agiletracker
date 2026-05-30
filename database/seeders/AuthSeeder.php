<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Roles ────────────────────────────────────────────────────────
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'user']);

        // ─── Users ────────────────────────────────────────────────────────
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );
        $admin->assignRole('admin');

        $alice = User::updateOrCreate(
            ['email' => 'alice@example.com'],
            ['name' => 'Alice Johnson', 'password' => Hash::make('password')]
        );
        $alice->assignRole('manager');

        $bob = User::updateOrCreate(
            ['email' => 'bob@example.com'],
            ['name' => 'Bob Williams', 'password' => Hash::make('password')]
        );
        $bob->assignRole('user');

        $carol = User::updateOrCreate(
            ['email' => 'carol@example.com'],
            ['name' => 'Carol Davis', 'password' => Hash::make('password')]
        );
        $carol->assignRole('user');
    }
}
