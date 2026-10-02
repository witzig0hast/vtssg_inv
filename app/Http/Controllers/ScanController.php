<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Project;
use App\Services\CheckoutService;
use App\Services\PackingService;
use DomainException;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function index()
    {
        return view('scan.index', [
            'projects' => Project::reserving()->orderBy('setup_at')->get(),
        ]);
    }

    /** Liefert Kerninformationen zu einem gescannten Code als JSON. */
    public function lookup(Request $request, PackingService $packing)
    {
        $request->validate(['code' => ['required', 'string', 'max:120']]);
        $asset = $packing->findAsset($request->string('code'));
        if (! $asset) {
            return response()->json(['found' => false, 'message' => 'Code unbekannt.'], 404);
        }
        $asset->load('model.manufacturer', 'status', 'location');
        $current = $asset->currentCheckout();

        return response()->json([
            'found' => true,
            'id' => $asset->id,
            'tag' => $asset->asset_tag,
            'name' => $asset->model->full_name,
            'serial' => $asset->serial,
            'status' => $asset->status->name,
            'deployable' => $asset->status->isDeployable() && ! $asset->is_blocked,
            'location' => $asset->location?->name,
            'checked_out_to' => $current?->assigneeLabel(),
            'inspection_due' => $asset->next_inspection_due?->format('d.m.Y'),
            'inspection_overdue' => $asset->isInspectionOverdue(),
            'url' => route('assets.show', $asset),
            'is_container' => $asset->is_container,
            'contents' => $asset->is_container ? $asset->children()->with('model')->get()->map(fn ($c) => $c->asset_tag.' · '.$c->model->full_name)->all() : [],
        ]);
    }

    /** Schnellausgabe/-rückgabe per Scan, ohne Projekt. */
    public function action(Request $request, CheckoutService $checkouts, PackingService $packing)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:120'], 'action' => ['required', 'in:checkin,checkout_user'],
            'condition' => ['nullable', 'in:ok,worn,damaged'],
        ]);
        $asset = $packing->findAsset($data['code']);
        if (! $asset) {
            return response()->json(['ok' => false, 'message' => 'Code unbekannt.'], 404);
        }
        try {
            if ($data['action'] === 'checkin') {
                $co = $asset->openCheckouts()->first() ?? throw new DomainException("{$asset->asset_tag} ist nicht ausgegeben.");
                $checkouts->checkin($co, $data['condition'] ?? 'ok', null, $request->user());
                $msg = "{$asset->asset_tag} zurückgenommen";
            } else {
                $checkouts->checkout($asset, 'user', $request->user()->id, $request->user());
                $msg = "{$asset->asset_tag} auf dich ausgegeben";
            }
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => $msg]);
    }
}
