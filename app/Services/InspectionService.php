<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Inspection;
use App\Models\MaintenanceTicket;
use App\Models\StatusLabel;
use App\Models\User;
use Carbon\Carbon;

class InspectionService
{
    public function __construct(private CheckoutService $checkouts) {}

    public function record(Asset $asset, array $data, ?User $by = null): Inspection
    {
        $type = \App\Models\InspectionType::findOrFail($data['inspection_type_id']);
        $performed = Carbon::parse($data['performed_at']);

        if (empty($data['next_due']) && $data['result'] !== 'failed') {
            $months = $asset->model->inspectionTypes->firstWhere('id', $type->id)?->pivot?->interval_months ?: $type->default_interval_months;
            $data['next_due'] = $performed->copy()->addMonths($months)->toDateString();
        }
        if ($data['result'] === 'failed') {
            $data['next_due'] = null;
        }

        $inspection = Inspection::create($data + ['asset_id' => $asset->id, 'performed_by' => $by?->id]);

        if ($inspection->result === 'failed') {
            $this->checkouts->reportDefect($asset, $by, "Prüfung nicht bestanden: {$type->name}", $inspection->notes, null, 'critical');
        }
        $asset->refresh()->load('model.inspectionTypes');
        $asset->refreshInspectionDue();

        return $inspection;
    }
}
