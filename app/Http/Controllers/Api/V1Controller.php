<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Checkout;
use App\Models\Project;
use App\Services\CheckoutService;
use App\Services\PackingService;
use DomainException;
use Illuminate\Http\Request;

class V1Controller extends Controller
{
    protected function assetJson(Asset $a): array
    {
        $a->loadMissing('model.manufacturer', 'status', 'location');
        $c = $a->currentCheckout();

        return [
            'id' => $a->id, 'uuid' => $a->uuid, 'asset_tag' => $a->asset_tag, 'serial' => $a->serial,
            'model' => $a->model->full_name, 'status' => $a->status->name, 'deployable' => $a->status->isDeployable() && ! $a->is_blocked,
            'location' => $a->location?->name, 'quantity' => $a->quantity, 'available' => $a->availableQuantity(),
            'next_inspection_due' => $a->next_inspection_due?->toDateString(), 'checked_out_to' => $c?->assigneeLabel(),
        ];
    }

    public function assets(Request $request)
    {
        abort_unless($request->user()->can('assets.view'), 403);
        $p = Asset::query()->with('model.manufacturer', 'status', 'location')->search($request->query('q'))->orderBy('asset_tag')->paginate(min(100, $request->integer('per_page', 25)));

        return response()->json(['data' => $p->getCollection()->map(fn ($a) => $this->assetJson($a)), 'meta' => ['page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function asset(Request $request, string $code, PackingService $packing)
    {
        abort_unless($request->user()->can('assets.view'), 403);
        $a = $packing->findAsset($code) ?? abort(404, 'Unbekannter Code');

        return response()->json($this->assetJson($a));
    }

    public function checkout(Request $request, string $code, PackingService $packing, CheckoutService $svc)
    {
        abort_unless($request->user()->can('assets.checkout'), 403);
        $data = $request->validate(['assignee_type' => ['required', 'in:user,project,location,customer'], 'assignee_id' => ['required', 'integer'], 'quantity' => ['nullable', 'integer', 'min:1'], 'expected_return_at' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:255']]);
        $a = $packing->findAsset($code) ?? abort(404);
        try {
            $co = $svc->checkout($a, $data['assignee_type'], $data['assignee_id'], $request->user(), $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['checkout_id' => $co->id] + $this->assetJson($a->refresh()), 201);
    }

    public function checkin(Request $request, Checkout $checkout, CheckoutService $svc)
    {
        abort_unless($request->user()->can('assets.checkout'), 403);
        $data = $request->validate(['condition' => ['nullable', 'in:ok,worn,damaged'], 'note' => ['nullable', 'string', 'max:255']]);
        try {
            $svc->checkin($checkout, $data['condition'] ?? 'ok', $data['note'] ?? null, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function projects(Request $request)
    {
        abort_unless($request->user()->can('projects.view'), 403);

        return response()->json(Project::query()->whereIn('status', Project::RESERVING)->orderBy('setup_at')->limit(100)->get(['id', 'number', 'name', 'status', 'setup_at', 'starts_at', 'ends_at', 'teardown_at']));
    }

    public function projectItems(Request $request, Project $project)
    {
        abort_unless($request->user()->can('projects.view'), 403);

        return response()->json($project->items()->with('model', 'asset')->get()->map(fn ($i) => [
            'id' => $i->id, 'label' => $i->label(), 'quantity' => $i->quantity, 'packed' => $i->packed_quantity, 'returned' => $i->returned_quantity, 'external' => $i->is_external,
        ]));
    }
}
