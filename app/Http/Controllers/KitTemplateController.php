<?php

namespace App\Http\Controllers;

use App\Models\AssetModel;
use App\Models\KitTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitTemplateController extends Controller
{
    public function index()
    {
        return view('kits.index', ['kits' => KitTemplate::withCount('items')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('kits.form', ['kit' => new KitTemplate, 'models' => AssetModel::with('manufacturer')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $kit = DB::transaction(fn () => $this->persist(new KitTemplate, $request));

        return redirect()->route('kits.index')->with('status', "Vorlage „{$kit->name}“ angelegt.");
    }

    public function edit(KitTemplate $kit)
    {
        return view('kits.form', ['kit' => $kit->load('items'), 'models' => AssetModel::with('manufacturer')->orderBy('name')->get()]);
    }

    public function update(Request $request, KitTemplate $kit)
    {
        DB::transaction(fn () => $this->persist($kit, $request));

        return redirect()->route('kits.index')->with('status', 'Gespeichert.');
    }

    public function destroy(KitTemplate $kit)
    {
        $kit->delete();

        return redirect()->route('kits.index')->with('status', 'Vorlage gelöscht.');
    }

    protected function persist(KitTemplate $kit, Request $request): KitTemplate
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array', 'max:300'],
            'items.*.asset_model_id' => ['required', 'exists:asset_models,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.group' => ['nullable', 'string', 'max:80'],
        ]);
        $kit->fill(['name' => $data['name'], 'description' => $data['description'] ?? null])->save();
        $kit->items()->delete();
        foreach ($data['items'] ?? [] as $i) {
            $kit->items()->create($i);
        }

        return $kit;
    }
}
