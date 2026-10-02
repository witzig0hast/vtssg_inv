<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Project;
use App\Models\ProjectItem;
use Carbon\Carbon;

class AvailabilityService
{
    /** Einsetzbare Stückzahl eines Modells (ausgebbar, nicht gesperrt, nicht gelöscht). */
    public function totalUnits(AssetModel $model): int
    {
        return (int) Asset::query()->where('asset_model_id', $model->id)->deployable()->sum('quantity');
    }

    public function reservedUnits(AssetModel $model, Carbon $from, Carbon $to, ?int $excludeProjectId = null): int
    {
        return (int) ProjectItem::query()
            ->where('asset_model_id', $model->id)
            ->where('is_external', false)
            ->whereHas('project', function ($p) use ($from, $to, $excludeProjectId) {
                $p->reserving()->overlapping($from, $to);
                if ($excludeProjectId) {
                    $p->where('id', '!=', $excludeProjectId);
                }
            })
            ->sum('quantity');
    }

    /** @return array{total:int,reserved:int,free:int} */
    public function forModel(AssetModel $model, Carbon $from, Carbon $to, ?int $excludeProjectId = null): array
    {
        $total = $this->totalUnits($model);
        $reserved = $this->reservedUnits($model, $from, $to, $excludeProjectId);

        return ['total' => $total, 'reserved' => $reserved, 'free' => $total - $reserved];
    }

    /**
     * Prüft alle Positionen eines Projekts gegen den Bestand und andere Projekte.
     *
     * @return list<array{item:ProjectItem,message:string}>
     */
    public function conflicts(Project $project): array
    {
        $from = $project->windowStart();
        $to = $project->windowEnd();
        if (! $from || ! $to) {
            return [];
        }

        $conflicts = [];
        $project->loadMissing('items.model', 'items.asset');

        $perModel = $project->items->where('is_external', false)->whereNotNull('asset_model_id')->groupBy('asset_model_id');
        foreach ($perModel as $modelId => $items) {
            $model = $items->first()->model;
            $wanted = $items->sum('quantity');
            $avail = $this->forModel($model, $from, $to, $project->id);
            if ($wanted > $avail['free']) {
                $conflicts[] = [
                    'item' => $items->first(),
                    'message' => sprintf('%s: benötigt %d, frei %d (Bestand %d, in anderen Projekten %d)', $model->full_name, $wanted, max(0, $avail['free']), $avail['total'], $avail['reserved']),
                ];
            }
        }

        foreach ($project->items->whereNotNull('asset_id') as $item) {
            $clash = ProjectItem::query()->where('asset_id', $item->asset_id)->where('project_id', '!=', $project->id)
                ->whereHas('project', fn ($p) => $p->reserving()->overlapping($from, $to))->with('project')->first();
            if ($clash) {
                $conflicts[] = ['item' => $item, 'message' => sprintf('%s ist bereits in Projekt %s reserviert', $item->asset->asset_tag, $clash->project->number)];
            }
            if ($item->asset && ($item->asset->is_blocked || ! $item->asset->status->isDeployable())) {
                $conflicts[] = ['item' => $item, 'message' => sprintf('%s ist nicht einsetzbar (%s)', $item->asset->asset_tag, $item->asset->status->name)];
            }
            if ($item->asset && $item->asset->next_inspection_due && $item->asset->next_inspection_due->lt($to->copy()->startOfDay())) {
                $conflicts[] = ['item' => $item, 'message' => sprintf('%s: Prüfung wird bis zum Einsatzende fällig (%s)', $item->asset->asset_tag, $item->asset->next_inspection_due->format('d.m.Y'))];
            }
        }

        return $conflicts;
    }
}
