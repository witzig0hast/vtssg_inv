<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Basisdaten (Rollen, Status, Prüfarten, Kategorien) ohne den Standard-Admin
        $this->seed(DatabaseSeeder::class);
        User::query()->forceDelete();
    }

    protected function userWithRole(string $role, array $attrs = []): User
    {
        $u = User::create($attrs + ['name' => $role, 'email' => strtolower(str_replace(' ', '', $role)).'@test.local', 'password' => 'Str0ng!Passw0rd#x', 'is_active' => true]);
        $u->assignRole($role);

        return $u;
    }
}
