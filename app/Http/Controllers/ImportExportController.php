<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    private const HEADERS = ['asset_tag', 'modell', 'hersteller', 'seriennummer', 'status', 'standort', 'menge', 'kaufdatum', 'kaufpreis', 'bemerkung'];

    public function export(Request $request): StreamedResponse
    {
        $q = AssetController::filtered($request)->with('model.manufacturer', 'status', 'location');

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM für Excel
            fputcsv($out, self::HEADERS, ';');
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $a) {
                    fputcsv($out, array_map([self::class, 'safe'], [
                        $a->asset_tag, $a->model->name, $a->model->manufacturer?->name, $a->serial, $a->status->name, $a->location?->name,
                        $a->quantity, $a->purchase_date?->format('d.m.Y'), Money::input($a->purchase_cost_cents), $a->notes,
                    ]), ';');
                }
            });
            fclose($out);
        }, 'inventar-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function form()
    {
        return view('assets.import', ['headers' => self::HEADERS]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADERS, ';');
            fputcsv($out, ['', 'SL1 Moving Head', 'Beispiel GmbH', 'SN12345', 'Einsatzbereit', 'Hauptlager', 1, '01.03.2025', '1.299,00', ''], ';');
            fclose($out);
        }, 'inventar-vorlage.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], 'dry_run' => ['nullable', 'boolean']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $first = fgets($handle);
        rewind($handle);
        $delim = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $head = array_map(fn ($h) => mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), fgetcsv($handle, 0, $delim) ?: []);
        if (! in_array('modell', $head, true)) {
            return back()->withErrors(['file' => 'Spalte „modell“ fehlt. Bitte die Vorlage verwenden.']);
        }

        $errors = [];
        $ok = 0;
        $line = 1;
        $rows = [];
        while (($r = fgetcsv($handle, 0, $delim)) !== false) {
            $line++;
            if (count(array_filter($r, fn ($x) => $x !== null && $x !== '')) === 0) continue;
            if (count($r) !== count($head)) { $errors[] = "Zeile $line: falsche Spaltenanzahl."; continue; }
            $row = array_combine($head, array_map('trim', $r));
            if ($line > 5001) { $errors[] = 'Maximal 5000 Zeilen pro Import.'; break; }

            $model = AssetModel::whereRaw('LOWER(name) = ?', [mb_strtolower($row['modell'])])->first();
            if (! $model) { $errors[] = "Zeile $line: Modell „{$row['modell']}“ existiert nicht (zuerst unter Modelle anlegen)."; continue; }
            $status = ($row['status'] ?? '') !== '' ? StatusLabel::whereRaw('LOWER(name) = ?', [mb_strtolower($row['status'])])->first() : StatusLabel::where('is_default', true)->first();
            if (! $status) { $errors[] = "Zeile $line: Status „{$row['status']}“ unbekannt."; continue; }
            $loc = ($row['standort'] ?? '') !== '' ? Location::whereRaw('LOWER(name) = ?', [mb_strtolower($row['standort'])])->first() : null;
            if (($row['standort'] ?? '') !== '' && ! $loc) { $errors[] = "Zeile $line: Standort „{$row['standort']}“ unbekannt."; continue; }
            $date = null;
            if (($row['kaufdatum'] ?? '') !== '') {
                try { $date = \Carbon\Carbon::createFromFormat('d.m.Y', $row['kaufdatum'])->toDateString(); } catch (\Throwable) { $errors[] = "Zeile $line: Kaufdatum ungültig (TT.MM.JJJJ)."; continue; }
            }
            if (($row['seriennummer'] ?? '') !== '' && Asset::where('serial', $row['seriennummer'])->exists()) { $errors[] = "Zeile $line: Seriennummer {$row['seriennummer']} existiert bereits."; continue; }
            if (($row['asset_tag'] ?? '') !== '' && Asset::withTrashed()->where('asset_tag', $row['asset_tag'])->exists()) { $errors[] = "Zeile $line: Asset-Tag {$row['asset_tag']} existiert bereits."; continue; }

            $rows[] = array_filter([
                'asset_tag' => $row['asset_tag'] ?: null, 'asset_model_id' => $model->id, 'status_label_id' => $status->id, 'location_id' => $loc?->id,
                'serial' => $row['seriennummer'] ?: null, 'quantity' => $model->isQuantity() ? max(1, (int) ($row['menge'] ?: 1)) : 1,
                'purchase_date' => $date, 'purchase_cost_cents' => Money::toCents($row['kaufpreis'] ?? null), 'notes' => $row['bemerkung'] ?? null,
            ], fn ($v) => $v !== null);
            $ok++;
        }
        fclose($handle);

        if ($errors) {
            return back()->withErrors(['file' => 'Import abgebrochen – keine Daten übernommen.'])->with('import_errors', array_slice($errors, 0, 50));
        }
        if (! $request->boolean('dry_run')) {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $r) {
                    Asset::create($r)->load('model.inspectionTypes')->refreshInspectionDue();
                }
            });
        }

        return back()->with('status', $request->boolean('dry_run') ? "Prüfung erfolgreich: $ok Zeilen wären importiert worden." : "$ok Assets importiert.");
    }

    /** Schutz vor CSV/Formel-Injection in Excel. */
    public static function safe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }
}
