<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Checkout;
use App\Models\MaintenanceTicket;
use App\Models\StatusLabel;
use App\Models\UsageLog;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * Gibt ein Asset (und bei Cases dessen Inhalt) aus.
     *
     * @param  array{quantity?:int,project_id?:int,expected_return_at?:mixed,note?:string,condition?:string}  $opts
     */
    public function checkout(Asset $asset, string $assigneeType, int $assigneeId, ?User $by = null, array $opts = []): Checkout
    {
        return DB::transaction(function () use ($asset, $assigneeType, $assigneeId, $by, $opts) {
            $asset = Asset::query()->with(['status', 'model.inspectionTypes'])->lockForUpdate()->findOrFail($asset->id);
            $qty = $asset->model->isQuantity() ? max(1, (int) ($opts['quantity'] ?? 1)) : 1;

            $this->assertCheckoutAllowed($asset, $qty);

            $checkout = $this->create($asset, $assigneeType, $assigneeId, $by, $qty, $opts);

            if ($asset->is_container) {
                foreach ($asset->children()->with('status', 'model.inspectionTypes')->get() as $child) {
                    if ($child->availableQuantity() < 1 || ! $child->status->isDeployable() || $child->is_blocked) {
                        continue; // Defekte/ausgegebene Teile bleiben im Case, werden aber nicht mitgebucht
                    }
                    $this->create($child, $assigneeType, $assigneeId, $by, $child->availableQuantity(), $opts);
                }
            }

            return $checkout;
        });
    }

    public function assertCheckoutAllowed(Asset $asset, int $qty = 1): void
    {
        if ($asset->is_blocked) {
            throw new DomainException("{$asset->asset_tag} ist gesperrt: ".($asset->blocked_reason ?: 'ohne Angabe'));
        }
        if (! $asset->status->isDeployable()) {
            throw new DomainException("{$asset->asset_tag} hat den Status „{$asset->status->name}“ und kann nicht ausgegeben werden.");
        }
        if ($overdue = $asset->blockingOverdueInspections()) {
            $names = collect($overdue)->map(fn ($i) => $i['type']->name.' (fällig '.$i['due']->format('d.m.Y').')')->implode(', ');
            throw new DomainException("{$asset->asset_tag}: Prüfung überfällig – $names. Ausgabe gesperrt.");
        }
        if ($asset->availableQuantity() < $qty) {
            throw new DomainException("{$asset->asset_tag}: nur {$asset->availableQuantity()} verfügbar, $qty angefordert.");
        }
    }

    protected function create(Asset $asset, string $type, int $id, ?User $by, int $qty, array $opts): Checkout
    {
        return Checkout::create([
            'asset_id' => $asset->id,
            'performed_by' => $by?->id,
            'assignee_type' => $type,
            'assignee_id' => $id,
            'project_id' => $opts['project_id'] ?? ($type === 'project' ? $id : null),
            'quantity' => $qty,
            'checked_out_at' => now(),
            'expected_return_at' => ! empty($opts['expected_return_at']) ? Carbon::parse($opts['expected_return_at']) : null,
            'condition_out' => $opts['condition'] ?? 'ok',
            'note_out' => $opts['note'] ?? null,
        ]);
    }

    /**
     * Nimmt eine Ausgabe zurück. Bei Defekt wird das Asset gesperrt und ein Wartungsticket erzeugt.
     *
     * @param  array{quantity?:int,hours?:float|int|string|null}  $opts
     */
    public function checkin(Checkout $checkout, string $condition = 'ok', ?string $note = null, ?User $by = null, array $opts = []): Checkout
    {
        return DB::transaction(function () use ($checkout, $condition, $note, $by, $opts) {
            $checkout = Checkout::query()->lockForUpdate()->findOrFail($checkout->id);
            if ($checkout->returned_at) {
                throw new DomainException('Diese Ausgabe wurde bereits zurückgenommen.');
            }
            $asset = Asset::query()->with('model')->findOrFail($checkout->asset_id);

            $partial = isset($opts['quantity']) && (int) $opts['quantity'] > 0 && (int) $opts['quantity'] < $checkout->quantity;
            if ($partial) {
                // Teilrückgabe: Rest bleibt ausgegeben, zurückgegebener Teil wird als eigener Datensatz abgeschlossen.
                $returnQty = (int) $opts['quantity'];
                $checkout->decrement('quantity', $returnQty);
                $closed = $checkout->replicate();
                $closed->quantity = $returnQty;
            } else {
                $closed = $checkout;
            }
            $closed->fill([
                'returned_at' => now(), 'returned_by' => $by?->id, 'condition_in' => $condition, 'note_in' => $note,
            ])->save();

            if (! empty($opts['hours']) && (float) $opts['hours'] > 0) {
                $this->logHours($asset, (float) $opts['hours'], $by, $checkout->project_id, 'Rückgabe');
            }

            if ($condition === 'damaged') {
                $this->reportDefect($asset, $by, 'Bei Rückgabe als defekt gemeldet', $note, $checkout->project_id);
            }

            if ($asset->is_container && ! $partial) {
                $kids = Checkout::query()->whereNull('returned_at')
                    ->whereIn('asset_id', $asset->children()->pluck('id'))
                    ->where('assignee_type', $checkout->assignee_type)->where('assignee_id', $checkout->assignee_id)->get();
                foreach ($kids as $k) {
                    $this->checkin($k, 'ok', $note, $by);
                }
            }

            return $closed;
        });
    }

    public function logHours(Asset $asset, float $hours, ?User $by, ?int $projectId, ?string $note): void
    {
        UsageLog::create(['asset_id' => $asset->id, 'user_id' => $by?->id, 'project_id' => $projectId, 'hours' => $hours, 'note' => $note]);
        $asset->increment('operating_hours', $hours);
    }

    public function reportDefect(Asset $asset, ?User $by, string $title, ?string $description = null, ?int $projectId = null, string $priority = 'high'): MaintenanceTicket
    {
        $label = StatusLabel::query()->where('kind', 'undeployable')->orderBy('id')->first();
        if ($label) {
            $asset->forceFill(['status_label_id' => $label->id])->save();
        }

        return MaintenanceTicket::create([
            'asset_id' => $asset->id, 'reported_by' => $by?->id, 'project_id' => $projectId,
            'title' => $title, 'description' => $description, 'priority' => $priority, 'status' => 'open', 'blocks_asset' => true,
        ]);
    }
}
