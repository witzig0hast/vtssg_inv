<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Customer;
use App\Models\InspectionType;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Beispieldaten für Entwicklung/Demo: `php artisan db:seed --class=DemoSeeder` */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $ok = StatusLabel::where('is_default', true)->first();
        $loc = Location::firstOrCreate(['name' => 'Hauptlager']);
        $shelf = Location::firstOrCreate(['name' => 'Regal A1'], ['type' => 'shelf', 'parent_id' => $loc->id]);
        Location::firstOrCreate(['name' => 'Sprinter VT-01'], ['type' => 'vehicle']);
        Location::firstOrCreate(['name' => 'Stadthalle Musterstadt'], ['type' => 'venue']);
        $sup = Supplier::firstOrCreate(['name' => 'Thomann Pro Audio'], ['email' => 'pro@example.com']);
        $cust = Customer::firstOrCreate(['number' => 'K-1001'], ['name' => 'Eventagentur Beispiel GmbH', 'contact' => 'Max Mustermann', 'email' => 'max@example.com']);

        $cat = fn ($n) => Category::where('name', $n)->first()->id;
        $insp = fn (...$c) => InspectionType::whereIn('code', $c)->pluck('id')->all();

        $defs = [
            ['Robe', 'MegaPointe', 'Licht', 'serial', 1450000, 80, 12, 750, ['DGUV_V3', 'LAMP']],
            ['Chauvet', 'COLORado Panel Q40', 'Licht', 'serial', 180000, 25, 8, null, ['DGUV_V3']],
            ['Yamaha', 'CL5 Digitalpult', 'Ton', 'serial', 2900000, 31, 28, null, ['DGUV_V3', 'FUNCTION']],
            ['Shure', 'SM58', 'Ton', 'serial', 9900, 0.3, null, null, ['FUNCTION']],
            ['d&b', 'Y10P Lautsprecher', 'Ton', 'serial', 320000, 28, 12, null, ['DGUV_V3']],
            ['Liftket', 'Kettenzug 500 kg', 'Rigging', 'serial', 140000, 22, 6, null, ['MOTOR', 'DGUV_V3']],
            ['Prolyte', 'Traverse H30V 2 m', 'Rigging', 'serial', 11000, 8, null, null, ['RIGGING']],
            ['Neutrik', 'XLR-Kabel 10 m', 'Kabel', 'quantity', 900, 0.4, null, null, ['DGUV_V3']],
            ['Lindner', 'Stromverteiler 32A', 'Strom', 'serial', 45000, 9, 3, null, ['DGUV_V3']],
        ];
        $models = [];
        foreach ($defs as [$mf, $name, $c, $tracking, $price, $kg, $rent, $hours, $types]) {
            $m = AssetModel::firstOrCreate(['name' => $name], [
                'manufacturer_id' => Manufacturer::firstOrCreate(['name' => $mf])->id, 'category_id' => $cat($c), 'tracking' => $tracking,
                'rental_price_cents' => (int) (($rent ?? 2) * 1000), 'weight_kg' => $kg, 'service_hours_limit' => $hours,
            ]);
            $m->inspectionTypes()->syncWithoutDetaching($insp(...$types));
            $models[$name] = [$m, $price];
        }

        foreach ($models as $name => [$m, $price]) {
            if ($m->assets()->exists()) continue;
            $n = match (true) { $m->isQuantity() => 1, $name === 'SM58' => 6, $name === 'MegaPointe' => 8, default => 4 };
            for ($i = 1; $i <= $n; $i++) {
                $a = Asset::create([
                    'asset_model_id' => $m->id, 'status_label_id' => $ok->id, 'location_id' => $shelf->id, 'supplier_id' => $sup->id,
                    'serial' => $m->isQuantity() ? null : strtoupper(substr(md5($name.$i), 0, 8)), 'quantity' => $m->isQuantity() ? 120 : 1,
                    'purchase_date' => now()->subMonths(rand(2, 30)), 'purchase_cost_cents' => $price,
                ]);
                // Prüfhistorie: die meisten Geräte sind aktuell geprüft, einige bald fällig bzw. überfällig
                foreach ($m->inspectionTypes as $t) {
                    $monthsAgo = $i % 7 === 0 ? 14 : ($i % 5 === 0 ? 11 : rand(1, 8));
                    \App\Models\Inspection::create([
                        'asset_id' => $a->id, 'inspection_type_id' => $t->id, 'performed_at' => now()->subMonths($monthsAgo)->toDateString(), 'result' => 'passed',
                        'next_due' => now()->subMonths($monthsAgo)->addMonths($t->default_interval_months)->toDateString(), 'inspector_name' => 'Elektro Müller GmbH',
                    ]);
                }
                $a->load('model.inspectionTypes')->refreshInspectionDue();
            }
        }

        foreach ([['Gaffa schwarz 50 mm', 'Gaffa & Klebebänder', 40, 20], ['Mignon AA Batterien', 'Batterien', 6, 24], ['Nebelfluid 5 l', 'Nebelfluid', 12, 4]] as [$n, $c, $q, $min]) {
            Consumable::firstOrCreate(['name' => $n], ['category_id' => $cat($c), 'quantity' => $q, 'min_quantity' => $min, 'unit' => 'Stk', 'location_id' => $loc->id]);
        }

        $p = Project::firstOrCreate(['number' => Project::nextNumber()], [
            'name' => 'Sommerfest Stadthalle', 'customer_id' => $cust->id, 'manager_id' => User::first()?->id, 'venue' => 'Stadthalle Musterstadt', 'status' => 'planned',
            'setup_at' => now()->addDays(5)->setTime(8, 0), 'starts_at' => now()->addDays(6)->setTime(18, 0), 'ends_at' => now()->addDays(6)->setTime(23, 59), 'teardown_at' => now()->addDays(7)->setTime(12, 0),
        ]);
        if (! $p->items()->exists()) {
            ProjectItem::create(['project_id' => $p->id, 'asset_model_id' => $models['MegaPointe'][0]->id, 'quantity' => 4, 'group' => 'Licht']);
            ProjectItem::create(['project_id' => $p->id, 'asset_model_id' => $models['SM58'][0]->id, 'quantity' => 3, 'group' => 'Ton']);
            ProjectItem::create(['project_id' => $p->id, 'is_external' => true, 'external_name' => 'LED-Wand 3×2 m (Zumiete)', 'supplier_id' => $sup->id, 'quantity' => 1, 'group' => 'Video', 'external_cost_cents' => 45000]);
        }
    }
}
