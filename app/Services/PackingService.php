<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Checkout;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Scan-basiertes Beladen und Rückbuchen einer Packliste. */
class PackingService
{
    public function __construct(private CheckoutService $checkouts) {}

    public function findAsset(string $code): ?Asset
    {
        $code = trim($code);

        // 1. Asset-Tag (eindeutig), 2. UUID (nur bei gültigem UUID-Format – PostgreSQL wirft sonst), 3. Seriennummer nur wenn eindeutig
        if ($asset = Asset::query()->where('asset_tag', $code)->first()) {
            return $asset;
        }
        if (\Illuminate\Support\Str::isUuid($code) && ($asset = Asset::query()->where('uuid', $code)->first())) {
            return $asset;
        }
        $bySerial = Asset::query()->where('serial', $code)->limit(2)->get();

        return $bySerial->count() === 1 ? $bySerial->first() : null;
    }

    /** @return array{item:ProjectItem,asset:Asset,message:string} */
    public function load(Project $project, string $code, ?User $by, int $quantity = 1): array
    {
        $asset = $this->findAsset($code) ?? throw new DomainException("Unbekannter Code „{$code}“.");

        return DB::transaction(function () use ($project, $asset, $by, $quantity) {
            $item = $this->matchItem($project, $asset, 'load');
            $qty = $asset->model->isQuantity() ? min(max(1, $quantity), $item->quantity - $item->packed_quantity) : 1;

            $this->checkouts->checkout($asset, 'project', $project->id, $by, [
                'quantity' => $qty, 'project_id' => $project->id, 'expected_return_at' => $project->teardown_at ?? $project->ends_at,
            ]);
            $item->increment('packed_quantity', $qty);
            $this->syncProjectStatus($project);

            return ['item' => $item->fresh(), 'asset' => $asset, 'message' => "{$asset->asset_tag} verladen"];
        });
    }

    /** @return array{item:?ProjectItem,asset:Asset,message:string} */
    public function unload(Project $project, string $code, ?User $by, string $condition = 'ok', ?string $note = null): array
    {
        $asset = $this->findAsset($code) ?? throw new DomainException("Unbekannter Code „{$code}“.");

        return DB::transaction(function () use ($project, $asset, $by, $condition, $note) {
            $checkout = Checkout::query()->where('asset_id', $asset->id)->where('project_id', $project->id)->whereNull('returned_at')->first()
                ?? throw new DomainException("{$asset->asset_tag} ist dieses Projekt nicht zugebucht.");
            $qty = $checkout->quantity;
            $this->checkouts->checkin($checkout, $condition, $note, $by);

            $item = ProjectItem::query()->where('project_id', $project->id)->where(fn ($q) => $q->where('asset_id', $asset->id)->orWhere('asset_model_id', $asset->asset_model_id))
                ->whereColumn('returned_quantity', '<', 'packed_quantity')->first();
            $item?->increment('returned_quantity', $qty);
            $this->syncProjectStatus($project);

            return ['item' => $item?->fresh(), 'asset' => $asset, 'message' => "{$asset->asset_tag} zurückgebucht"];
        });
    }

    protected function matchItem(Project $project, Asset $asset, string $mode): ProjectItem
    {
        $items = $project->items()->where('is_external', false)->get();
        // 1. exakt gebuchtes Gerät, 2. Modellposition mit offener Menge
        $item = $items->first(fn ($i) => $i->asset_id === $asset->id && $i->packed_quantity < $i->quantity)
            ?? $items->first(fn ($i) => $i->asset_id === null && $i->asset_model_id === $asset->asset_model_id && $i->packed_quantity < $i->quantity);

        if (! $item) {
            if ($items->contains(fn ($i) => $i->asset_id === $asset->id || $i->asset_model_id === $asset->asset_model_id)) {
                throw new DomainException("{$asset->asset_tag}: Menge laut Packliste bereits vollständig.");
            }
            throw new DomainException("{$asset->asset_tag} ({$asset->model->full_name}) steht nicht auf der Packliste.");
        }

        return $item;
    }

    protected function syncProjectStatus(Project $project): void
    {
        $items = $project->items()->where('is_external', false)->get();
        if ($items->isEmpty() || ! in_array($project->status, ['planned', 'confirmed', 'loaded', 'running', 'returned'], true)) {
            return;
        }
        $packed = $items->sum('packed_quantity');
        $returned = $items->sum('returned_quantity');
        $total = $items->sum('quantity');

        $new = match (true) {
            $packed > 0 && $returned >= $packed && $packed >= $total => 'returned',
            $packed >= $total && $returned < $packed => in_array($project->status, ['running'], true) ? 'running' : 'loaded',
            default => $project->status,
        };
        if ($new !== $project->status) {
            $project->update(['status' => $new]);
            Webhook::fire('project.status_changed', ['number' => $project->number, 'name' => $project->name, 'status' => $new]);
        }
    }
}
