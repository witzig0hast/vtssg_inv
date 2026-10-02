<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Stocktake;
use App\Services\PackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StocktakeController extends Controller
{
    public function index()
    {
        return view('stocktakes.index', ['rows' => Stocktake::with(['location', 'starter'])->withCount('lines')->latest()->paginate(20), 'locations' => Location::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'location_id' => ['required', 'exists:locations,id']]);
        $st = DB::transaction(function () use ($data, $request) {
            $st = Stocktake::create($data + ['started_by' => $request->user()->id]);
            // Soll-Bestand: Assets am Standort (inkl. Unterstandorte), die nicht ausgegeben sind
            $ids = $this->locationTree((int) $data['location_id']);
            Asset::whereIn('location_id', $ids)->whereHas('status', fn ($s) => $s->where('kind', '!=', 'archived'))->get()
                ->filter(fn ($a) => $a->availableQuantity() > 0)
                ->each(fn ($a) => $st->lines()->create(['asset_id' => $a->id, 'expected_quantity' => $a->availableQuantity()]));

            return $st;
        });

        return redirect()->route('stocktakes.show', $st);
    }

    public function show(Stocktake $stocktake)
    {
        $lines = $stocktake->lines()->with('asset.model.manufacturer')->get();

        return view('stocktakes.show', ['st' => $stocktake->load('location'), 'lines' => $lines, 'missing' => $lines->where('found_quantity', 0), 'found' => $lines->where('found_quantity', '>', 0)]);
    }

    public function scan(Request $request, Stocktake $stocktake, PackingService $packing)
    {
        abort_if($stocktake->status === 'closed', 422, 'Inventur ist abgeschlossen.');
        $code = $request->validate(['code' => ['required', 'string', 'max:120']])['code'];
        $asset = $packing->findAsset($code);
        if (! $asset) {
            return response()->json(['ok' => false, 'message' => 'Code unbekannt.'], 404);
        }
        // Unerwartete Funde werden als zusätzliche Zeile (expected 0) geführt
        $line = $stocktake->lines()->firstOrCreate(['asset_id' => $asset->id], ['expected_quantity' => 0]);
        $line->update(['found_quantity' => $asset->model->isQuantity() ? min($line->found_quantity + 1, max($line->expected_quantity, 1) * 1000) : 1, 'scanned_at' => now(), 'scanned_by' => $request->user()->id]);

        return response()->json(['ok' => true, 'message' => $asset->asset_tag.($line->expected_quantity === 0 ? ' (nicht erwartet an diesem Standort!)' : ' erfasst'), 'unexpected' => $line->expected_quantity === 0]);
    }

    public function close(Stocktake $stocktake)
    {
        $stocktake->update(['status' => 'closed', 'closed_at' => now()]);
        $missing = $stocktake->lines()->where('found_quantity', 0)->where('expected_quantity', '>', 0)->count();
        AuditLog::record('stocktake_closed', null, summary: "Inventur „{$stocktake->name}“: $missing fehlend");

        return back()->with('status', "Inventur abgeschlossen. Fehlend: $missing");
    }

    protected function locationTree(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        while ($frontier) {
            $frontier = Location::whereIn('parent_id', $frontier)->pluck('id')->diff($ids)->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
