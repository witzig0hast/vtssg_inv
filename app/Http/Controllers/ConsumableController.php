<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Consumable;
use App\Models\ConsumableMovement;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Project;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsumableController extends Controller
{
    public function index(Request $request)
    {
        $q = Consumable::query()->with(['category', 'location']);
        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like));
        }
        if ($request->boolean('low')) {
            $q->whereColumn('quantity', '<=', 'min_quantity')->where('min_quantity', '>', 0);
        }

        return view('consumables.index', ['rows' => $q->orderBy('name')->paginate(25)->withQueryString()]);
    }

    public function create()
    {
        return view('consumables.form', ['c' => new Consumable(['unit' => 'Stk']), ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $initial = (int) ($data['quantity'] ?? 0);
        $data['quantity'] = 0;
        $c = DB::transaction(function () use ($data, $initial, $request) {
            $c = Consumable::create($data);
            if ($initial > 0) {
                $this->move($c, $initial, 'purchase', 'Anfangsbestand', $request->user()->id);
            }

            return $c;
        });

        return redirect()->route('consumables.show', $c)->with('status', 'Angelegt.');
    }

    public function show(Consumable $consumable)
    {
        return view('consumables.show', [
            'c' => $consumable->load(['category', 'manufacturer', 'supplier', 'location']),
            'movements' => $consumable->movements()->with('user')->limit(100)->get(),
            'projects' => Project::reserving()->orderBy('setup_at')->get(),
        ]);
    }

    public function edit(Consumable $consumable)
    {
        return view('consumables.form', ['c' => $consumable, ...$this->lookups()]);
    }

    public function update(Request $request, Consumable $consumable)
    {
        $data = $this->validated($request);
        unset($data['quantity']); // Bestand nur über Buchungen
        $consumable->update($data);

        return redirect()->route('consumables.show', $consumable)->with('status', 'Gespeichert.');
    }

    public function destroy(Consumable $consumable)
    {
        $consumable->delete();

        return redirect()->route('consumables.index')->with('status', 'Gelöscht.');
    }

    public function movement(Request $request, Consumable $consumable)
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', 'in:purchase,usage,correction,loss'],
            'project_id' => ['nullable', 'exists:projects,id'], 'note' => ['nullable', 'string', 'max:200'],
        ]);
        // Verbrauch/Schwund sind immer negativ, Einkauf immer positiv
        $delta = match ($data['reason']) { 'usage', 'loss' => -abs($data['delta']), 'purchase' => abs($data['delta']), default => $data['delta'] };

        DB::transaction(function () use ($consumable, $delta, $data, $request) {
            $c = Consumable::lockForUpdate()->findOrFail($consumable->id);
            if ($c->quantity + $delta < 0) {
                abort(422, 'Bestand würde negativ.');
            }
            $this->move($c, $delta, $data['reason'], $data['note'] ?? null, $request->user()->id, $data['project_id'] ?? null);
        });

        return back()->with('status', 'Buchung erfasst.');
    }

    protected function move(Consumable $c, int $delta, string $reason, ?string $note, ?int $userId, ?int $projectId = null): void
    {
        ConsumableMovement::create(['consumable_id' => $c->id, 'user_id' => $userId, 'project_id' => $projectId, 'delta' => $delta, 'reason' => $reason, 'note' => $note]);
        $c->increment('quantity', $delta);
        \App\Models\AuditLog::record('stock_movement', $c, summary: "{$c->name}: ".($delta > 0 ? '+' : '').$delta.' ('.$reason.')');
    }

    protected function lookups(): array
    {
        return [
            'categories' => Category::where('kind', 'consumable')->orderBy('name')->get(),
            'manufacturers' => Manufacturer::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'sku' => ['nullable', 'string', 'max:80', 'unique:consumables,sku,'.($request->route('consumable')?->id ?? 'NULL')],
            'category_id' => ['nullable', 'exists:categories,id'], 'manufacturer_id' => ['nullable', 'exists:manufacturers,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'], 'location_id' => ['nullable', 'exists:locations,id'],
            'unit' => ['required', 'string', 'max:20'], 'quantity' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'min_quantity' => ['nullable', 'integer', 'min:0', 'max:10000000'], 'unit_cost' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $d['min_quantity'] = $d['min_quantity'] ?? 0;
        $d['unit_cost_cents'] = Money::toCents($d['unit_cost'] ?? null);
        unset($d['unit_cost']);

        return $d;
    }
}
