<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectItem;
use App\Services\AvailabilityService;
use App\Services\RiderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderController extends Controller
{
    public function form(Project $project)
    {
        return view('projects.rider', ['project' => $project, 'rows' => null]);
    }

    public function parse(Request $request, Project $project, RiderService $rider, AvailabilityService $availability)
    {
        $request->validate([
            'text' => ['nullable', 'string', 'max:200000'],
            'file' => ['nullable', 'file', 'mimes:pdf,txt,csv', 'max:5120'],
        ]);
        if (! $request->filled('text') && ! $request->hasFile('file')) {
            return back()->withErrors(['text' => 'Bitte Text einfügen oder eine Datei hochladen.']);
        }
        try {
            $text = $rider->extractText($request->input('text'), $request->file('file'));
        } catch (\Throwable) {
            return back()->withErrors(['file' => 'Die Datei konnte nicht gelesen werden (evtl. gescanntes PDF ohne Text).']);
        }
        $rows = $rider->parse($text);
        $from = $project->windowStart();
        $to = $project->windowEnd();
        foreach ($rows as &$r) {
            foreach ($r['candidates'] as &$c) {
                $c['free'] = $from && $to ? $availability->forModel(\App\Models\AssetModel::find($c['id']), $from, $to, $project->id)['free'] : null;
            }
        }

        return view('projects.rider', ['project' => $project, 'rows' => $rows]);
    }

    public function apply(Request $request, Project $project)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:300'],
            'items.*.use' => ['nullable', 'boolean'],
            'items.*.asset_model_id' => ['nullable', 'exists:asset_models,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.raw' => ['nullable', 'string', 'max:200'],
            'group' => ['nullable', 'string', 'max:80'],
        ]);
        $n = 0;
        DB::transaction(function () use ($data, $project, &$n) {
            foreach ($data['items'] as $i) {
                if (empty($i['use'])) {
                    continue;
                }
                if (! empty($i['asset_model_id'])) {
                    ProjectItem::create(['project_id' => $project->id, 'asset_model_id' => $i['asset_model_id'], 'quantity' => $i['quantity'], 'group' => $data['group'] ?? 'Rider', 'note' => 'aus Rider']);
                } else {
                    // nicht im Bestand → als Zumiete vormerken
                    ProjectItem::create(['project_id' => $project->id, 'is_external' => true, 'external_name' => $i['raw'] ?? 'Rider-Position', 'quantity' => $i['quantity'], 'group' => $data['group'] ?? 'Rider', 'note' => 'aus Rider – Beschaffung klären']);
                }
                $n++;
            }
        });

        return redirect()->route('projects.show', $project)->with('status', "$n Positionen aus dem Rider übernommen.");
    }
}
