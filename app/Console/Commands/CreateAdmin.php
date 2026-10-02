<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'inv:create-admin {email} {--name=Administrator} {--password=}';
    protected $description = 'Legt einen Administrator an (oder setzt das Passwort zurück).';

    public function handle(): int
    {
        $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        $password = $this->option('password') ?: Str::password(20, symbols: false);
        $user = User::withTrashed()->firstOrNew(['email' => Str::lower($this->argument('email'))]);
        $user->fill(['name' => $user->name ?: $this->option('name'), 'password' => $password, 'is_active' => true, 'must_change_password' => true]);
        $user->deleted_at = null;
        $user->save();
        $user->syncRoles(['Administrator']);
        $this->info("Administrator {$user->email} bereit.");
        if (! $this->option('password')) {
            $this->warn("Einmal-Passwort: $password");
        }

        return self::SUCCESS;
    }
}
