<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Setting;

/** Öffentlicher „Gerätepass“ – bewusst minimal (keine Seriennummer, keine internen Daten). */
class PassController extends Controller
{
    public function show(string $uuid)
    {
        abort_unless(\Illuminate\Support\Str::isUuid($uuid), 404);
        $asset = Asset::with('model.manufacturer', 'model.category', 'inspections.type')->where('uuid', $uuid)->firstOrFail();
        $last = $asset->inspections->where('result', '!=', 'failed')->sortByDesc('performed_at')->first();

        return view('pass', [
            'asset' => $asset, 'last' => $last,
            'company' => Setting::get('company_name'), 'address' => Setting::get('company_address'),
            'valid' => $asset->next_inspection_due === null || $asset->next_inspection_due->gte(today()),
        ]);
    }
}
