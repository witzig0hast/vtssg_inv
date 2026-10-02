<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class Install extends Command
{
    protected $signature = 'inv:install';
    protected $description = 'Erstinstallation: Basisdaten und Administrator anlegen (nur wenn noch kein Benutzer existiert).';

    public function handle(): int
    {
        if (User::withTrashed()->exists()) {
            $this->line('Bereits installiert – übersprungen.');

            return self::SUCCESS;
        }
        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $this->info('Installation abgeschlossen.');

        return self::SUCCESS;
    }
}
