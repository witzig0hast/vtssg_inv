<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Inspection;
use App\Models\InspectionType;
use App\Models\Setting;
use App\Services\InspectionService;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    /** Fälligkeitsliste: überfällig / bald fällig. */
    public function index(Request $request)
    {
        $warn = (int) Setting::get('inspection_warn_days', 30);
        $q = Asset::query()->with(['model.manufacturer', 'model.inspectionTypes', 'status', 'location'])->whereNotNull('next_inspection_due');
        match ($request->query('scope', 'attention')) {
            'overdue' => $q->whereDate('next_inspection_due', '<', today()),
            'soon' => $q->whereBetween('next_inspection_due', [today(), today()->addDays($warn)]),
            'all' => null,
            default => $q->whereDate('next_inspection_due', '<=', today()->addDays($warn)),
        };

        return view('inspections.index', [
            'assets' => $q->orderBy('next_inspection_due')->paginate(30)->withQueryString(),
            'warn' => $warn,
            'recent' => Inspection::with(['asset', 'type', 'performer'])->latest('performed_at')->latest('id')->limit(10)->get(),
        ]);
    }

    public function create(Request $request)
    {
        $asset = $request->filled('asset') ? Asset::with('model.inspectionTypes')->findOrFail($request->integer('asset')) : null;

        return view('inspections.form', ['asset' => $asset, 'types' => InspectionType::orderBy('name')->get(), 'results' => Inspection::RESULTS]);
    }

    public function store(Request $request, InspectionService $service)
    {
        $data = $request->validate([
            'asset_tag' => ['required', 'string', 'exists:assets,asset_tag'],
            'inspection_type_id' => ['required', 'exists:inspection_types,id'],
            'performed_at' => ['required', 'date', 'before_or_equal:today'],
            'next_due' => ['nullable', 'date', 'after:performed_at'],
            'result' => ['required', 'in:passed,conditional,failed'],
            'inspector_name' => ['nullable', 'string', 'max:160'],
            'protocol_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:'.config('inventory.upload_max_kb'), 'mimes:'.implode(',', config('inventory.upload_mimes'))],
        ]);
        $asset = Asset::where('asset_tag', $data['asset_tag'])->firstOrFail();
        unset($data['asset_tag'], $data['file']);

        $inspection = $service->record($asset->load('model.inspectionTypes'), $data, $request->user());

        if ($request->hasFile('file')) {
            $f = $request->file('file');
            $path = $f->storeAs('attachments/'.date('Y/m'), bin2hex(random_bytes(16)).'.'.$f->extension(), 'local');
            $inspection->attachments()->create([
                'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path, 'original_name' => mb_substr($f->getClientOriginalName(), 0, 200),
                'mime' => $f->getMimeType(), 'size' => $f->getSize(), 'sha256' => hash_file('sha256', $f->getRealPath()), 'label' => 'Prüfprotokoll',
            ]);
        }

        return redirect()->route('assets.show', $asset)->with('status', 'Prüfung erfasst.'.($inspection->result === 'failed' ? ' Asset wurde gesperrt, Wartungsticket erstellt.' : ''));
    }
}
