<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InspectionType;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        foreach ([
            ['Einsatzbereit', 'deployable', '#16a34a', true],
            ['In Klärung', 'pending', '#d97706', false],
            ['In Reparatur / defekt', 'undeployable', '#dc2626', false],
            ['Ausgemustert', 'archived', '#64748b', false],
            ['Verloren / gestohlen', 'archived', '#7f1d1d', false],
        ] as [$name, $kind, $color, $default]) {
            StatusLabel::firstOrCreate(['name' => $name], ['kind' => $kind, 'color' => $color, 'is_default' => $default]);
        }

        foreach ([
            ['DGUV_V3', 'DGUV V3 – Elektrische Betriebsmittel', 12, true, 'Wiederkehrende Prüfung ortsveränderlicher elektrischer Betriebsmittel.'],
            ['RIGGING', 'Rigging / Sachkundigenprüfung', 12, true, 'Prüfung von Anschlagmitteln, Traversen, Kettenzügen (DGUV Information 215-313).'],
            ['MOTOR', 'Kettenzug / Hebezeug (DGUV V54)', 12, true, 'Jährliche Prüfung durch Sachkundigen.'],
            ['LAMP', 'Lampen-/Betriebsstundenkontrolle', 6, false, 'Kontrolle der Lampen- und Betriebsstunden.'],
            ['FUNCTION', 'Funktionsprüfung', 6, false, 'Allgemeine Funktionskontrolle.'],
        ] as [$code, $name, $months, $blocks, $desc]) {
            InspectionType::firstOrCreate(['code' => $code], ['name' => $name, 'default_interval_months' => $months, 'blocks_checkout' => $blocks, 'description' => $desc]);
        }

        foreach (['Licht', 'Ton', 'Video', 'Rigging', 'Strom', 'Kabel', 'Bühne', 'Case / Transport', 'Zubehör'] as $c) {
            Category::firstOrCreate(['name' => $c, 'kind' => 'asset']);
        }
        foreach (['Gaffa & Klebebänder', 'Batterien', 'Leuchtmittel', 'Nebelfluid', 'Folien / Gels', 'Kleinmaterial'] as $c) {
            Category::firstOrCreate(['name' => $c, 'kind' => 'consumable']);
        }

        Location::firstOrCreate(['name' => 'Hauptlager'], ['type' => 'warehouse']);
        Location::firstOrCreate(['name' => 'Werkstatt'], ['type' => 'workshop']);

        // Initialer Administrator – Zugangsdaten über Umgebungsvariablen, sonst Zufallspasswort (wird einmalig ausgegeben).
        if (! User::query()->exists()) {
            $email = config('inventory.admin_email');
            $password = config('inventory.admin_password') ?: Str::password(20, symbols: false);
            $admin = User::create(['name' => 'Administrator', 'email' => $email, 'password' => $password, 'must_change_password' => true, 'is_active' => true]);
            $admin->assignRole('Administrator');
            if (! config('inventory.admin_password')) {
                $this->command?->warn("Initialer Admin: $email / $password  (bitte sofort ändern)");
            }
        }
    }
}
