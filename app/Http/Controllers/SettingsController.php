<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings.edit', [
            's' => collect(array_keys(Setting::DEFAULTS))->mapWithKeys(fn ($k) => [$k => Setting::get($k)]),
            'hasSecret' => (bool) Setting::get('webhook_secret'),
            'deliveries' => \Illuminate\Support\Facades\DB::table('webhook_deliveries')->latest('id')->limit(10)->get(),
        ]);
    }

    public function webhookTest()
    {
        abort_unless(\App\Services\Webhook::enabled(), 422, 'Keine Webhook-URL konfiguriert.');
        \App\Services\Webhook::fire('test', ['message' => 'Testereignis aus dem Inventar']);

        return back()->with('status', 'Testereignis in die Queue gestellt – Ergebnis siehe Zustellprotokoll.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'], 'company_address' => ['nullable', 'string', 'max:500'],
            'asset_tag_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-_]+$/'], 'asset_tag_digits' => ['required', 'integer', 'min:3', 'max:10'],
            'inspection_warn_days' => ['required', 'integer', 'min:1', 'max:365'], 'notify_email' => ['nullable', 'email'],
            'revenue_account' => ['required', 'digits_between:4,8'],
            'webhook_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'min:16', 'max:200'],
        ]);
        if (! empty($data['webhook_url'])) {
            try {
                \App\Services\Webhook::assertSafeUrl($data['webhook_url']);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['webhook_url' => $e->getMessage()])->withInput();
            }
        }
        if (! empty($data['webhook_secret'])) {
            Setting::put('webhook_secret', \Illuminate\Support\Facades\Crypt::encryptString($data['webhook_secret']));
        }
        unset($data['webhook_secret']);
        foreach ($data as $k => $v) {
            Setting::put($k, (string) $v);
        }

        return back()->with('status', 'Einstellungen gespeichert.');
    }
}
