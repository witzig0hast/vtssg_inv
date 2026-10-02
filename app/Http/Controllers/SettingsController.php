<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings.edit', ['s' => collect(array_keys(Setting::DEFAULTS))->mapWithKeys(fn ($k) => [$k => Setting::get($k)])]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'], 'company_address' => ['nullable', 'string', 'max:500'],
            'asset_tag_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-_]+$/'], 'asset_tag_digits' => ['required', 'integer', 'min:3', 'max:10'],
            'inspection_warn_days' => ['required', 'integer', 'min:1', 'max:365'], 'notify_email' => ['nullable', 'email'],
        ]);
        foreach ($data as $k => $v) {
            Setting::put($k, (string) $v);
        }

        return back()->with('status', 'Einstellungen gespeichert.');
    }
}
