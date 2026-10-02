<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** Alle Berechtigungen: Gruppe => [Aktionen]. */
    public const PERMISSIONS = [
        'assets' => ['view', 'create', 'update', 'delete', 'checkout', 'label', 'import', 'export'],
        'models' => ['view', 'manage'],
        'masterdata' => ['view', 'manage'],   // Kategorien, Hersteller, Lieferanten, Standorte, Status
        'consumables' => ['view', 'manage'],
        'projects' => ['view', 'create', 'update', 'delete', 'pack'],
        'customers' => ['view', 'manage'],
        'inspections' => ['view', 'record', 'manage'],
        'maintenance' => ['view', 'report', 'manage'],
        'stocktakes' => ['view', 'manage'],
        'reports' => ['view'],
        'audit' => ['view'],
        'users' => ['manage'],
        'settings' => ['manage'],
    ];

    public const ROLES = [
        'Administrator' => '*',
        'Lager' => [
            'assets.*', 'models.*', 'masterdata.*', 'consumables.*', 'projects.view', 'projects.pack', 'customers.view',
            'inspections.*', 'maintenance.*', 'stocktakes.*', 'reports.view',
        ],
        'Projektleitung' => [
            'assets.view', 'assets.checkout', 'models.view', 'masterdata.view', 'consumables.view', 'projects.*', 'customers.*',
            'inspections.view', 'maintenance.view', 'maintenance.report', 'reports.view',
        ],
        'Techniker' => [
            'assets.view', 'assets.checkout', 'models.view', 'masterdata.view', 'consumables.view', 'projects.view', 'projects.pack',
            'inspections.view', 'maintenance.view', 'maintenance.report',
        ],
        'Nur Lesen' => ['assets.view', 'models.view', 'masterdata.view', 'consumables.view', 'projects.view', 'inspections.view', 'maintenance.view'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];
        foreach (self::PERMISSIONS as $group => $actions) {
            foreach ($actions as $a) {
                $all[] = Permission::findOrCreate("$group.$a", 'web')->name;
            }
        }

        foreach (self::ROLES as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($perms === '*') {
                $role->syncPermissions($all);
                continue;
            }
            $expanded = collect($perms)->flatMap(function ($p) use ($all) {
                if (str_ends_with($p, '.*')) {
                    $prefix = substr($p, 0, -1);

                    return array_filter($all, fn ($x) => str_starts_with($x, $prefix));
                }

                return [$p];
            })->unique()->values()->all();
            $role->syncPermissions($expanded);
        }
    }
}
