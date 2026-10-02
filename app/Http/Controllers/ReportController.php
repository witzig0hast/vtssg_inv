<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Checkout;
use App\Models\MaintenanceTicket;
use App\Models\Project;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $assets = Asset::with('model.category')->get();
        $byCategory = $assets->groupBy(fn ($a) => $a->model->category->name ?? '—')->map(fn ($g) => [
            'count' => $g->sum('quantity'), 'value' => $g->sum(fn ($a) => ($a->purchase_cost_cents ?? 0) * ($a->model->isQuantity() ? $a->quantity : 1)),
        ])->sortByDesc('value');

        // Lineare Abschreibung: Restwert = Kaufpreis * (1 - Alter / Nutzungsdauer)
        $book = $assets->sum(function ($a) {
            if (! $a->purchase_cost_cents) return 0;
            $cost = $a->purchase_cost_cents * ($a->model->isQuantity() ? $a->quantity : 1);
            $months = $a->model->depreciation_months;
            if (! $months || ! $a->purchase_date) return $cost;
            $age = $a->purchase_date->diffInMonths(now());

            return (int) round($cost * max(0, 1 - $age / $months));
        });

        // Einsatztage und Kosten je Modell (Lebenszykluskosten)
        $repairs = MaintenanceTicket::select('asset_id', DB::raw('SUM(cost_cents) as cost'))->groupBy('asset_id')->pluck('cost', 'asset_id');
        $usage = Checkout::select('asset_id', DB::raw('COUNT(*) as n'))->whereNotNull('project_id')->groupBy('asset_id')->pluck('n', 'asset_id');
        $lifecycle = $assets->filter(fn ($a) => $a->purchase_cost_cents)->map(function ($a) use ($repairs, $usage) {
            $total = $a->purchase_cost_cents + (int) ($repairs[$a->id] ?? 0);
            $n = (int) ($usage[$a->id] ?? 0);

            return ['asset' => $a, 'total' => $total, 'repairs' => (int) ($repairs[$a->id] ?? 0), 'uses' => $n, 'per_use' => $n ? (int) round($total / $n) : null];
        })->sortByDesc('total')->take(15);

        // Verschleißprognose: Ersatzbedarf nach Betriebsstunden-Tempo (letzte 12 Monate) und nach Abschreibungsende
        $rate = \App\Models\UsageLog::where('created_at', '>=', now()->subYear())->select('asset_id', DB::raw('SUM(hours) as h'))->groupBy('asset_id')->pluck('h', 'asset_id');
        $forecast = $assets->map(function ($a) use ($rate) {
            $dates = [];
            if (($rem = $a->serviceHoursRemaining()) !== null) {
                $perDay = ((float) ($rate[$a->id] ?? 0)) / 365;
                $dates['Lebensdauer (Betriebsstunden)'] = $perDay > 0 ? now()->addDays((int) min(3650, ceil($rem / $perDay))) : null;
            }
            if ($a->model->depreciation_months && $a->purchase_date) {
                $dates['Abschreibung'] = $a->purchase_date->copy()->addMonths($a->model->depreciation_months);
            }
            $dates = array_filter($dates);
            if (! $dates) return null;
            asort($dates);

            return ['asset' => $a, 'reason' => array_key_first($dates), 'date' => reset($dates)];
        })->filter()->filter(fn ($r) => $r['date']->lte(now()->addYears(2)))->sortBy(fn ($r) => $r['date']->timestamp)->take(20);

        return view('reports.index', [
            'forecast' => $forecast,
            'byCategory' => $byCategory, 'totalValue' => $byCategory->sum('value'), 'bookValue' => $book, 'lifecycle' => $lifecycle,
            'projectsByMonth' => Project::whereNotIn('status', ['cancelled', 'draft'])->get()->groupBy(fn ($p) => ($p->starts_at ?? $p->setup_at ?? $p->created_at)->format('Y-m'))->map->count()->sortKeys()->take(-12),
            'utilization' => Checkout::whereNotNull('project_id')->where('checked_out_at', '>=', now()->subYear())->count(),
            'lampWarn' => $assets->filter(fn ($a) => $a->serviceHoursRemaining() !== null && $a->serviceHoursRemaining() <= ($a->serviceHoursLimit() * 0.1))->take(20),
        ]);
    }
}
