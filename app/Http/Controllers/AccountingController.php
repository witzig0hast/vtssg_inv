<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Project;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Buchhaltungs-Exporte (CSV, Semikolon, UTF-8 mit BOM). Kontenrahmen/Format bitte mit dem Steuerberater abstimmen. */
class AccountingController extends Controller
{
    public function afa(Request $request): StreamedResponse
    {
        $year = $request->integer('year', (int) now()->format('Y'));
        $end = \Carbon\Carbon::create($year, 12, 31);

        return $this->csv("anlagenspiegel-$year.csv", ['Asset-Tag', 'Bezeichnung', 'Kaufdatum', 'Anschaffungskosten', 'ND Monate', 'AfA Jahr', 'AfA kumuliert', "Buchwert 31.12.$year", 'Lieferant', 'Rechnungsnr.'], function ($out) use ($end, $year) {
            Asset::with('model.manufacturer', 'supplier')->whereNotNull('purchase_cost_cents')->whereDate('purchase_date', '<=', $end)->orderBy('purchase_date')->chunk(300, function ($assets) use ($out, $end, $year) {
                foreach ($assets as $a) {
                    $cost = $a->purchase_cost_cents * ($a->model->isQuantity() ? $a->quantity : 1);
                    $months = $a->model->depreciation_months;
                    $afaYear = $acc = 0;
                    if ($months && $a->purchase_date) {
                        $monthly = $cost / $months;
                        $usedEnd = min($months, max(0, $a->purchase_date->copy()->startOfMonth()->diffInMonths($end->copy()->startOfMonth()) + 1));
                        $usedPrev = min($months, max(0, $a->purchase_date->copy()->startOfMonth()->diffInMonths($end->copy()->subYear()->startOfMonth()) + 1));
                        if ($a->purchase_date->gt($end->copy()->subYear())) $usedPrev = 0;
                        $acc = (int) round($monthly * $usedEnd);
                        $afaYear = (int) round($monthly * ($usedEnd - $usedPrev));
                    }
                    fputcsv($out, array_map([ImportExportController::class, 'safe'], [
                        $a->asset_tag, $a->model->full_name, $a->purchase_date?->format('d.m.Y'), Money::input($cost), $months, Money::input($afaYear), Money::input($acc),
                        Money::input(max(0, $cost - $acc)), $a->supplier?->name, $a->invoice_number,
                    ]), ';');
                }
            });
        });
    }

    public function invoice(Project $project): StreamedResponse
    {
        $project->load('customer', 'items.model.manufacturer', 'items.asset');
        $days = $project->rentalDays();

        return $this->csv("rechnungsposten-{$project->number}.csv", ['Projekt', 'Kundennr.', 'Kunde', 'Pos', 'Bezeichnung', 'Menge', 'Tage', 'Einzelpreis/Tag', 'Summe netto', 'Zumiete (EK)'], function ($out) use ($project, $days) {
            $n = 0;
            foreach ($project->items as $i) {
                $n++;
                $sum = $i->is_external ? 0 : $i->unitPriceCents() * $i->quantity * $days;
                fputcsv($out, array_map([ImportExportController::class, 'safe'], [
                    $project->number, $project->customer?->number, $project->customer?->name, $n, $i->label(), $i->quantity, $days,
                    Money::input($i->unitPriceCents()), Money::input($sum), $i->is_external ? Money::input(($i->external_cost_cents ?? 0) * $i->quantity) : '',
                ]), ';');
            }
        });
    }

    /** Erlösbuchungen im Stil eines DATEV-Buchungsstapels (vereinfacht). */
    public function revenue(Request $request): StreamedResponse
    {
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $revenueAccount = Setting::get('revenue_account', '8400');

        return $this->csv('erloese-'.$data['from'].'_'.$data['to'].'.csv', ['Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'Konto', 'Gegenkonto (ohne BU-Schlüssel)', 'Belegdatum', 'Belegfeld 1', 'Buchungstext'], function ($out) use ($data, $revenueAccount) {
            Project::with('customer', 'items.model')->whereIn('status', ['returned', 'closed'])->whereBetween('ends_at', [$data['from'].' 00:00:00', $data['to'].' 23:59:59'])->orderBy('ends_at')->chunk(100, function ($projects) use ($out, $revenueAccount) {
                foreach ($projects as $p) {
                    $sum = $p->items->where('is_external', false)->sum(fn ($i) => $i->unitPriceCents() * $i->quantity) * $p->rentalDays();
                    if ($sum <= 0) continue;
                    fputcsv($out, array_map([ImportExportController::class, 'safe'], [Money::input($sum), 'S', $p->customer?->number ?: '', $revenueAccount, $p->ends_at?->format('dm'), $p->number, mb_substr('Miete '.$p->name, 0, 60)]), ';');
                }
            });
        });
    }

    private function csv(string $name, array $header, \Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            $rows($out);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
