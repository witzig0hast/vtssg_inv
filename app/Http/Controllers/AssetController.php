<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    protected function lookups(): array
    {
        return [
            'models' => AssetModel::with('manufacturer')->orderBy('name')->get(),
            'statuses' => StatusLabel::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'containers' => Asset::where('is_container', true)->orderBy('asset_tag')->get(),
        ];
    }

    public static function filtered(Request $request)
    {
        $q = Asset::query()->with(['model.manufacturer', 'model.category', 'status', 'location'])
            ->search($request->query('q'));

        if ($v = $request->query('status')) $q->where('status_label_id', $v);
        if ($v = $request->query('location')) $q->where('location_id', $v);
        if ($v = $request->query('model')) $q->where('asset_model_id', $v);
        if ($v = $request->query('category')) $q->whereHas('model', fn ($m) => $m->where('category_id', $v));
        match ($request->query('state')) {
            'out' => $q->whereHas('openCheckouts'),
            'in' => $q->whereDoesntHave('openCheckouts'),
            'overdue' => $q->whereDate('next_inspection_due', '<', today()),
            'due_soon' => $q->whereBetween('next_inspection_due', [today(), today()->addDays((int) \App\Models\Setting::get('inspection_warn_days', 30))]),
            'blocked' => $q->where('is_blocked', true),
            default => null,
        };
        $sort = in_array($request->query('sort'), ['asset_tag', 'next_inspection_due', 'created_at', 'purchase_date'], true) ? $request->query('sort') : 'asset_tag';

        return $q->orderBy($sort, $request->query('dir') === 'desc' ? 'desc' : 'asc');
    }

    public function index(Request $request)
    {
        return view('assets.index', [
            'assets' => self::filtered($request)->paginate(25)->withQueryString(),
            'statuses' => StatusLabel::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'categories' => Category::where('kind', 'asset')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $asset = new Asset(['quantity' => 1, 'status_label_id' => StatusLabel::where('is_default', true)->value('id'), 'purchase_date' => today()]);
        if ($request->filled('model')) $asset->asset_model_id = $request->integer('model');
        if ($request->filled('parent')) $asset->parent_id = $request->integer('parent');

        return view('assets.form', ['asset' => $asset] + $this->lookups());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $copies = $request->integer('copies', 1);
        $model = AssetModel::findOrFail($data['asset_model_id']);
        $created = [];

        DB::transaction(function () use ($data, $copies, $model, &$created) {
            $n = $model->isQuantity() ? 1 : max(1, min($copies, 100));
            for ($i = 0; $i < $n; $i++) {
                $row = $data;
                if ($n > 1) $row['serial'] = null; // Serien bei Mehrfachanlage nicht duplizieren
                $created[] = Asset::create($row);
            }
        });
        foreach ($created as $a) {
            $a->load('model.inspectionTypes')->refreshInspectionDue();
        }

        return count($created) === 1
            ? redirect()->route('assets.show', $created[0])->with('status', 'Asset '.$created[0]->asset_tag.' angelegt.')
            : redirect()->route('assets.index', ['q' => $created[0]->asset_tag])->with('status', count($created).' Assets angelegt ('.$created[0]->asset_tag.' – '.end($created)->asset_tag.').');
    }

    public function show(Asset $asset)
    {
        $asset->load(['model.manufacturer', 'model.category', 'model.inspectionTypes', 'status', 'location', 'supplier', 'parent', 'children.status', 'attachments.uploader']);

        return view('assets.show', [
            'asset' => $asset,
            'checkouts' => $asset->checkouts()->with('performer')->latest('checked_out_at')->limit(50)->get(),
            'inspections' => $asset->inspections()->with(['type', 'performer'])->latest('performed_at')->get(),
            'tickets' => $asset->tickets()->with('reporter')->latest()->get(),
            'dues' => $asset->inspectionDueDates(),
            'current' => $asset->openCheckouts()->get(),
        ]);
    }

    public function edit(Asset $asset)
    {
        return view('assets.form', ['asset' => $asset] + $this->lookups());
    }

    public function update(Request $request, Asset $asset)
    {
        $asset->update($this->validated($request, $asset));
        $asset->load('model.inspectionTypes')->refreshInspectionDue();

        return redirect()->route('assets.show', $asset)->with('status', 'Gespeichert.');
    }

    public function destroy(Asset $asset)
    {
        if ($asset->isCheckedOut()) {
            return back()->withErrors(['delete' => 'Das Asset ist ausgegeben und kann nicht gelöscht werden.']);
        }
        $asset->children()->update(['parent_id' => null]);
        $asset->delete();

        return redirect()->route('assets.index')->with('status', "Asset {$asset->asset_tag} gelöscht.");
    }

    public function block(Request $request, Asset $asset)
    {
        $data = $request->validate(['blocked' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:200']]);
        $asset->update(['is_blocked' => (bool) $data['blocked'], 'blocked_reason' => $data['blocked'] ? ($data['reason'] ?? null) : null]);

        return back()->with('status', $data['blocked'] ? 'Asset gesperrt.' : 'Sperre aufgehoben.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer', 'exists:assets,id'],
            'action' => ['required', Rule::in(['status', 'location', 'labels'])],
            'status_label_id' => ['nullable', 'exists:status_labels,id'], 'location_id' => ['nullable', 'exists:locations,id'],
        ]);
        if ($data['action'] === 'labels') {
            return redirect()->route('labels.print', ['ids' => $data['ids']]);
        }
        abort_unless($request->user()->can('assets.update'), 403);
        $field = $data['action'] === 'status' ? 'status_label_id' : 'location_id';
        abort_if(empty($data[$field]), 422, 'Zielwert fehlt.');
        // einzeln speichern, damit das Audit-Log jede Änderung erfasst
        Asset::whereIn('id', $data['ids'])->get()->each(fn ($a) => $a->update([$field => $data[$field]]));

        return back()->with('status', count($data['ids']).' Assets aktualisiert.');
    }

    protected function validated(Request $request, ?Asset $asset = null): array
    {
        $data = $request->validate([
            'asset_model_id' => ['required', 'exists:asset_models,id'],
            'status_label_id' => ['required', 'exists:status_labels,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'parent_id' => ['nullable', 'exists:assets,id', Rule::notIn([$asset?->id ?? 0])],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'asset_tag' => ['nullable', 'string', 'max:60', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'serial' => ['nullable', 'string', 'max:120', Rule::unique('assets', 'serial')->ignore($asset?->id)->whereNull('deleted_at')],
            'name' => ['nullable', 'string', 'max:160'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'string', 'max:20'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'warranty_until' => ['nullable', 'date'],
            'operating_hours' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'is_container' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $model = AssetModel::findOrFail($data['asset_model_id']);
        $data['purchase_cost_cents'] = Money::toCents($data['purchase_cost'] ?? null);
        $data['quantity'] = $model->isQuantity() ? ($data['quantity'] ?? 1) : 1;
        $data['is_container'] = $request->boolean('is_container');
        $data['operating_hours'] = $data['operating_hours'] ?? 0;
        if (empty($data['asset_tag'])) unset($data['asset_tag']);
        unset($data['purchase_cost'], $data['copies']);

        return $data;
    }
}
