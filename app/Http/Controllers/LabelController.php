<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Setting;
use App\Support\Qr;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function print(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'format' => ['nullable', 'in:small,medium,sheet']]);
        $assets = Asset::with('model.manufacturer')->whereIn('id', $data['ids'])->orderBy('asset_tag')->get();
        $labels = $assets->map(fn ($a) => ['asset' => $a, 'qr' => Qr::svg($a->qrPayload())]);

        return view('labels.print', ['labels' => $labels, 'format' => $data['format'] ?? 'medium', 'company' => Setting::get('company_name')]);
    }

    public function single(Asset $asset)
    {
        return redirect()->route('labels.print', ['ids' => [$asset->id]]);
    }
}
