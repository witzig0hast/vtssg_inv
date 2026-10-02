<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InspectionType;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\Supplier;

/** Deklarative Definition der einfachen Stammdaten-Verwaltungen (generisches CRUD). */
class MasterData
{
    public static function all(): array
    {
        return [
            'categories' => [
                'model' => Category::class, 'title' => 'Kategorien', 'singular' => 'Kategorie', 'perm' => 'masterdata', 'protect' => ['assetModels'],
                'columns' => [['name', 'Name'], ['kind', 'Art', fn ($r) => $r->kind === 'asset' ? 'Equipment' : 'Verbrauchsmaterial']],
                'fields' => [
                    ['name', 'Name', 'text', 'required|string|max:120'],
                    ['kind', 'Art', 'select', 'required|in:asset,consumable', ['asset' => 'Equipment', 'consumable' => 'Verbrauchsmaterial']],
                    ['color', 'Farbe', 'color', 'nullable|regex:/^#[0-9a-fA-F]{6}$/'],
                    ['notes', 'Notizen', 'textarea', 'nullable|string|max:2000'],
                ],
            ],
            'manufacturers' => [
                'model' => Manufacturer::class, 'title' => 'Hersteller', 'singular' => 'Hersteller', 'perm' => 'masterdata', 'protect' => ['assetModels'],
                'columns' => [['name', 'Name'], ['support_email', 'Support-E-Mail'], ['support_phone', 'Telefon']],
                'fields' => [
                    ['name', 'Name', 'text', 'required|string|max:120'],
                    ['url', 'Website', 'url', 'nullable|url|max:255'],
                    ['support_email', 'Support-E-Mail', 'email', 'nullable|email|max:255'],
                    ['support_phone', 'Support-Telefon', 'text', 'nullable|string|max:60'],
                    ['notes', 'Notizen', 'textarea', 'nullable|string|max:2000'],
                ],
            ],
            'suppliers' => [
                'model' => Supplier::class, 'title' => 'Lieferanten', 'singular' => 'Lieferant', 'perm' => 'masterdata', 'protect' => ['assets'],
                'columns' => [['name', 'Name'], ['contact', 'Kontakt'], ['email', 'E-Mail'], ['phone', 'Telefon']],
                'fields' => [
                    ['name', 'Name', 'text', 'required|string|max:160'],
                    ['contact', 'Ansprechpartner', 'text', 'nullable|string|max:160'],
                    ['email', 'E-Mail', 'email', 'nullable|email|max:255'],
                    ['phone', 'Telefon', 'text', 'nullable|string|max:60'],
                    ['address', 'Adresse', 'text', 'nullable|string|max:255'],
                    ['url', 'Website', 'url', 'nullable|url|max:255'],
                    ['notes', 'Notizen', 'textarea', 'nullable|string|max:2000'],
                ],
            ],
            'locations' => [
                'model' => Location::class, 'title' => 'Standorte', 'singular' => 'Standort', 'perm' => 'masterdata', 'protect' => ['assets', 'children'], 'with' => ['parent'],
                'columns' => [['name', 'Name', fn ($r) => $r->path], ['type', 'Typ', fn ($r) => Location::TYPES[$r->type] ?? $r->type], ['address', 'Adresse']],
                'fields' => [
                    ['name', 'Name', 'text', 'required|string|max:120'],
                    ['type', 'Typ', 'select', 'required|in:'.implode(',', array_keys(Location::TYPES)), Location::TYPES],
                    ['parent_id', 'Übergeordnet', 'select', 'nullable|exists:locations,id', fn () => Location::orderBy('name')->get()->mapWithKeys(fn ($l) => [$l->id => $l->path])->all(), true],
                    ['address', 'Adresse', 'text', 'nullable|string|max:255'],
                    ['max_load_kg', 'Max. Zuladung (kg, nur Fahrzeuge)', 'number', 'nullable|integer|min:1|max:100000'],
                    ['notes', 'Notizen', 'textarea', 'nullable|string|max:2000'],
                ],
            ],
            'status-labels' => [
                'model' => StatusLabel::class, 'title' => 'Status', 'singular' => 'Status', 'perm' => 'masterdata', 'protect' => ['assets'],
                'columns' => [['name', 'Name'], ['kind', 'Art', fn ($r) => StatusLabel::KINDS[$r->kind] ?? $r->kind]],
                'fields' => [
                    ['name', 'Name', 'text', 'required|string|max:80'],
                    ['kind', 'Art', 'select', 'required|in:'.implode(',', array_keys(StatusLabel::KINDS)), StatusLabel::KINDS],
                    ['color', 'Farbe', 'color', 'required|regex:/^#[0-9a-fA-F]{6}$/'],
                ],
            ],
            'customers' => [
                'model' => Customer::class, 'title' => 'Kunden', 'singular' => 'Kunde', 'perm' => 'customers', 'protect' => ['projects'],
                'columns' => [['number', 'Nr.'], ['name', 'Name'], ['contact', 'Kontakt'], ['email', 'E-Mail']],
                'fields' => [
                    ['number', 'Kundennummer', 'text', 'nullable|string|max:40|unique:customers,number,{id}'],
                    ['name', 'Name', 'text', 'required|string|max:160'],
                    ['contact', 'Ansprechpartner', 'text', 'nullable|string|max:160'],
                    ['email', 'E-Mail', 'email', 'nullable|email|max:255'],
                    ['phone', 'Telefon', 'text', 'nullable|string|max:60'],
                    ['address', 'Adresse', 'text', 'nullable|string|max:255'],
                    ['notes', 'Notizen', 'textarea', 'nullable|string|max:2000'],
                ],
            ],
            'inspection-types' => [
                'model' => InspectionType::class, 'title' => 'Prüfarten', 'singular' => 'Prüfart', 'perm' => 'inspections', 'perm_manage' => 'manage', 'protect' => ['assetModels'],
                'columns' => [['code', 'Kürzel'], ['name', 'Name'], ['default_interval_months', 'Intervall (Monate)'], ['blocks_checkout', 'Sperrt Ausgabe', fn ($r) => $r->blocks_checkout ? 'Ja' : 'Nein']],
                'fields' => [
                    ['code', 'Kürzel', 'text', 'required|alpha_dash|max:30|unique:inspection_types,code,{id}'],
                    ['name', 'Name', 'text', 'required|string|max:160'],
                    ['default_interval_months', 'Standard-Intervall (Monate)', 'number', 'required|integer|min:1|max:120'],
                    ['blocks_checkout', 'Überfällige Prüfung sperrt Ausgabe', 'checkbox', 'boolean'],
                    ['description', 'Beschreibung', 'textarea', 'nullable|string|max:2000'],
                ],
            ],
        ];
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? abort(404);
    }
}
