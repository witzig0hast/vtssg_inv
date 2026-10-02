<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\InspectionType;
use App\Models\Manufacturer;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssetModelController extends Controller
{
    public function index(Request $request)
    {
        $q = AssetModel::query()->with(['manufacturer', 'category'])->withCount('assets');
        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('model_number', 'like', $like)
                ->orWhereHas('manufacturer', fn ($m) => $m->where('name', 'like', $like)));
        }
        if ($c = $request->query('category')) $q->where('category_id', $c);

        return view('models.index', [
            'models' => $q->orderBy('name')->paginate(25)->withQueryString(),
            'categories' => Category::where('kind', 'asset')->orderBy('name')->get(),
        ]);
    }

    public function image(AssetModel $model)
    {
        abort_unless($model->image_path && Storage::disk('local')->exists($model->image_path), 404);

        return Storage::disk('local')->response($model->image_path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function create()
    {
        return view('models.form', ['m' => new AssetModel(['tracking' => 'serial', 'unit' => 'Stk']), ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $m = DB::transaction(function () use ($request) {
            $m = AssetModel::create($this->validated($request));
            $this->syncInspections($m, $request);

            return $m;
        });

        return redirect()->route('models.show', $m)->with('status', 'Modell angelegt.');
    }

    public function show(AssetModel $model)
    {
        $model->load(['manufacturer', 'category', 'inspectionTypes']);

        return view('models.show', [
            'm' => $model,
            'assets' => $model->assets()->with(['status', 'location'])->orderBy('asset_tag')->get(),
        ]);
    }

    public function edit(AssetModel $model)
    {
        return view('models.form', ['m' => $model->load('inspectionTypes'), ...$this->lookups()]);
    }

    public function update(Request $request, AssetModel $model)
    {
        DB::transaction(function () use ($request, $model) {
            $model->update($this->validated($request, $model));
            $this->syncInspections($model, $request);
        });
        // Fälligkeiten aller Geräte dieses Modells neu berechnen
        $model->assets()->with('model.inspectionTypes')->get()->each->refreshInspectionDue();

        return redirect()->route('models.show', $model)->with('status', 'Gespeichert.');
    }

    public function destroy(AssetModel $model)
    {
        if ($model->assets()->exists()) {
            return back()->withErrors(['delete' => 'Zu diesem Modell existieren noch Assets.']);
        }
        $model->delete();

        return redirect()->route('models.index')->with('status', 'Modell gelöscht.');
    }

    protected function lookups(): array
    {
        return [
            'manufacturers' => Manufacturer::orderBy('name')->get(),
            'categories' => Category::where('kind', 'asset')->orderBy('name')->get(),
            'inspectionTypes' => InspectionType::orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request, ?AssetModel $m = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'manufacturer_id' => ['nullable', 'exists:manufacturers,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'model_number' => ['nullable', 'string', 'max:120'],
            'tracking' => ['required', 'in:serial,quantity'],
            'unit' => ['required', 'string', 'max:20'],
            'rental_price' => ['nullable', 'string', 'max:20'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'power_w' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'depreciation_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'service_hours_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('inventory.upload_max_kb')],
            'inspection_types' => ['nullable', 'array'],
            'inspection_types.*' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
        $data['rental_price_cents'] = Money::toCents($data['rental_price'] ?? null) ?? 0;
        if ($request->hasFile('image')) {
            if ($m?->image_path) Storage::disk('local')->delete($m->image_path);
            // Re-Encoding wäre ideal; Dateiname wird serverseitig vergeben, Endung aus geprüftem MIME.
            $data['image_path'] = $request->file('image')->store('models', 'local');
        }
        unset($data['rental_price'], $data['image'], $data['inspection_types']);

        return $data;
    }

    protected function syncInspections(AssetModel $m, Request $request): void
    {
        $selected = $request->input('inspection_enabled', []);
        $intervals = $request->input('inspection_types', []);
        $sync = [];
        foreach (InspectionType::pluck('id') as $id) {
            if (isset($selected[$id])) {
                $sync[$id] = ['interval_months' => ($intervals[$id] ?? null) ?: null];
            }
        }
        $m->inspectionTypes()->sync($sync);
    }
}
