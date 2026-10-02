<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Checkout;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Setting;
use App\Models\StatusLabel;
use App\Services\RiderService;
use App\Services\Webhook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExtrasTest extends TestCase
{
    private function model(string $name, string $mf = 'Robe', array $extra = []): AssetModel
    {
        return AssetModel::create($extra + ['name' => $name, 'manufacturer_id' => Manufacturer::firstOrCreate(['name' => $mf])->id, 'category_id' => Category::first()->id]);
    }

    private function project(): Project
    {
        return Project::create(['number' => Project::nextNumber(), 'name' => 'P', 'status' => 'planned', 'setup_at' => '2026-12-01 08:00', 'starts_at' => '2026-12-01 18:00', 'ends_at' => '2026-12-02 23:00', 'teardown_at' => '2026-12-03 12:00']);
    }

    public function test_rider_parser_matches_inventory(): void
    {
        $mp = $this->model('MegaPointe');
        $sm = $this->model('SM58', 'Shure');
        $rows = app(RiderService::class)->parse("Technical Rider\n8x Robe MegaPointe\n- 4 Shure SM58 Mikrofon\n12 Stk XLR Kabel 10m\nKaffee fuer alle");
        $byRaw = collect($rows)->keyBy('raw');
        $this->assertSame(8, $byRaw['8x Robe MegaPointe']['qty']);
        $this->assertSame($mp->id, $byRaw['8x Robe MegaPointe']['candidates'][0]['id']);
        $this->assertSame(4, collect($rows)->firstWhere('candidates.0.id', $sm->id)['qty']);
        $unmatched = collect($rows)->firstWhere('qty', 12);
        $this->assertEmpty($unmatched['candidates']); // → Zumiete-Vorschlag
        $this->assertNull(collect($rows)->firstWhere('raw', 'Kaffee fuer alle'));
    }

    public function test_rider_flow_creates_items_and_external_fallback(): void
    {
        $admin = $this->userWithRole('Administrator');
        $mp = $this->model('MegaPointe');
        $p = $this->project();
        $this->actingAs($admin)->post(route('projects.rider.parse', $p), ['text' => "8x Robe MegaPointe\n12 Stk XLR Kabel 10m"])->assertOk()->assertSee('Robe MegaPointe');
        $this->post(route('projects.rider.apply', $p), ['group' => 'Rider', 'items' => [
            ['use' => 1, 'asset_model_id' => $mp->id, 'quantity' => 8, 'raw' => 'Robe MegaPointe'],
            ['use' => 1, 'asset_model_id' => '', 'quantity' => 12, 'raw' => 'XLR Kabel 10m'],
            ['quantity' => 3, 'raw' => 'ignoriert'],
        ]])->assertRedirect();
        $this->assertSame(2, $p->items()->count());
        $this->assertTrue($p->items()->where('is_external', true)->where('external_name', 'XLR Kabel 10m')->exists());
    }

    public function test_ical_feed_token_security(): void
    {
        $u = $this->userWithRole('Projektleitung');
        $this->project();
        $this->get('/ical/'.str_repeat('a', 48).'.ics')->assertNotFound();
        $this->actingAs($u)->post(route('profile.ical.regenerate'))->assertRedirect();
        $token = $u->fresh()->ical_token;
        $this->assertSame(48, strlen($token));
        auth()->logout();
        $r = $this->get("/ical/$token.ics")->assertOk();
        $this->assertStringContainsString('BEGIN:VEVENT', $r->getContent());
        $this->assertStringContainsString('text/calendar', $r->headers->get('Content-Type'));
        // Widerruf
        $this->actingAs($u)->delete(route('profile.ical.revoke'));
        auth()->logout();
        $this->get("/ical/$token.ics")->assertNotFound();
    }

    public function test_accounting_exports(): void
    {
        $admin = $this->userWithRole('Administrator');
        $m = $this->model('Wash', 'Robe', ['rental_price_cents' => 5000, 'depreciation_months' => 60]);
        $a = Asset::create(['asset_model_id' => $m->id, 'status_label_id' => StatusLabel::first()->id, 'purchase_date' => '2025-01-15', 'purchase_cost_cents' => 600000]);
        $p = $this->project();
        $p->update(['status' => 'closed']);
        ProjectItem::create(['project_id' => $p->id, 'asset_model_id' => $m->id, 'quantity' => 2]);
        $this->actingAs($admin);

        $afa = $this->get(route('accounting.afa', ['year' => 2026]))->streamedContent();
        $this->assertStringContainsString($a->asset_tag, $afa);
        $this->assertStringContainsString('6000,00', $afa);      // Anschaffungskosten
        $this->assertStringContainsString('1200,00', $afa);      // AfA 2026 = 6000/60*12

        $inv = $this->get(route('accounting.invoice', $p))->streamedContent();
        $this->assertStringContainsString('200,00', $inv);        // 2 × 50 € × 2 Tage

        $rev = $this->get(route('accounting.revenue', ['from' => '2026-12-01', 'to' => '2026-12-31']))->streamedContent();
        $this->assertStringContainsString('200,00;S;', $rev);
        $this->userWithRole('Techniker');
        $this->actingAs(\App\Models\User::where('email', 'techniker@test.local')->first())->get(route('accounting.afa'))->assertForbidden();
    }

    public function test_public_device_pass_is_minimal(): void
    {
        $m = $this->model('Pass');
        $a = Asset::create(['asset_model_id' => $m->id, 'status_label_id' => StatusLabel::first()->id, 'serial' => 'GEHEIM-SN-1', 'notes' => 'interne notiz', 'purchase_cost_cents' => 123456]);
        $r = $this->get(route('pass', $a->uuid))->assertOk();
        $r->assertSee($a->asset_tag)->assertDontSee('GEHEIM-SN-1')->assertDontSee('interne notiz')->assertDontSee('1.234,56');
        $this->get('/pass/nicht-eine-uuid')->assertNotFound();
        $this->get('/pass/'.\Illuminate\Support\Str::uuid())->assertNotFound();
    }

    public function test_signature_validation_and_storage(): void
    {
        Storage::fake('local');
        $u = $this->userWithRole('Techniker');
        $p = $this->project();
        $png = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->actingAs($u)->post(route('projects.signature.store', $p), ['name' => 'Max', 'image' => 'data:image/png;base64,'.$png])->assertRedirect();
        $this->assertSame('Max', $p->fresh()->signed_by);
        $this->assertSame(1, $p->attachments()->where('label', 'Unterschrift')->count());
        // kein PNG → abgelehnt
        $this->post(route('projects.signature.store', $p), ['name' => 'X', 'image' => 'data:image/png;base64,'.base64_encode('<?php evil ?>')])->assertSessionHasErrors('image');
        $this->post(route('projects.signature.store', $p), ['name' => 'X', 'image' => 'data:text/html;base64,'.base64_encode('<script>')])->assertSessionHasErrors('image');
        // Lieferschein mit Unterschrift rendert
        $this->get(route('projects.document', [$p, 'delivery-note']))->assertOk();
    }

    public function test_loading_photo_upload(): void
    {
        Storage::fake('local');
        $u = $this->userWithRole('Techniker');
        $p = $this->project();
        $this->actingAs($u)->post(route('projects.photo', $p), ['photo' => UploadedFile::fake()->image('case.jpg'), 'label' => 'Case 3'])->assertRedirect();
        $this->assertTrue($p->attachments()->where('label', 'like', 'Beladefoto%')->exists());
        $this->post(route('projects.photo', $p), ['photo' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])->assertSessionHasErrors('photo');
    }

    public function test_weight_and_vehicle_capacity_shown(): void
    {
        $admin = $this->userWithRole('Administrator');
        Location::create(['name' => 'Sprinter', 'type' => 'vehicle', 'max_load_kg' => 100]);
        $m = $this->model('Schwer', 'X', ['weight_kg' => 30]);
        $p = $this->project();
        ProjectItem::create(['project_id' => $p->id, 'asset_model_id' => $m->id, 'quantity' => 4]);
        $this->actingAs($admin)->get(route('projects.show', $p))->assertOk()->assertSee('120 kg')->assertSee('zu schwer');
    }

    public function test_webhook_dispatch_signature_and_ssrf_guard(): void
    {
        Bus::fake();
        Webhook::fire('x', ['a' => 1]);
        Bus::assertNotDispatched(SendWebhook::class); // keine URL → nichts
        Setting::put('webhook_url', 'https://example.org/hook');
        Webhook::fire('project.status_changed', ['number' => '1']);
        Bus::assertDispatched(SendWebhook::class);

        foreach (['http://example.org', 'https://127.0.0.1/x', 'https://10.0.0.5/x', 'https://169.254.169.254/latest', 'ftp://example.org', 'https://[::1]/x'] as $bad) {
            try {
                Webhook::assertSafeUrl($bad);
                $this->fail("SSRF nicht blockiert: $bad");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        Webhook::assertSafeUrl('https://93.184.216.34/hook'); // öffentliche IP erlaubt
    }

    public function test_webhook_job_posts_signed_payload(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('ok', 200)]);
        Setting::put('webhook_url', 'https://93.184.216.34/hook');
        Setting::put('webhook_secret', Crypt::encryptString('geheimes-secret-12345'));
        (new SendWebhook('test', ['k' => 'v']))->handle();
        \Illuminate\Support\Facades\Http::assertSent(function ($req) {
            $expected = 'sha256='.hash_hmac('sha256', $req->body(), 'geheimes-secret-12345');

            return $req->header('X-Inventory-Signature')[0] === $expected && $req->header('X-Inventory-Event')[0] === 'test';
        });
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'test', 'status' => 200]);
    }

    public function test_settings_reject_private_webhook_target_and_encrypt_secret(): void
    {
        $admin = $this->userWithRole('Administrator');
        $base = ['company_name' => 'F', 'asset_tag_prefix' => 'VT-', 'asset_tag_digits' => 6, 'inspection_warn_days' => 30, 'revenue_account' => '8400'];
        $this->actingAs($admin)->put(route('settings.update'), $base + ['webhook_url' => 'https://192.168.1.10/h'])->assertSessionHasErrors('webhook_url');
        $this->put(route('settings.update'), $base + ['webhook_url' => 'https://93.184.216.34/h', 'webhook_secret' => 'ein-langes-geheimnis-1'])->assertSessionHasNoErrors();
        $stored = Setting::get('webhook_secret');
        $this->assertNotSame('ein-langes-geheimnis-1', $stored);
        $this->assertSame('ein-langes-geheimnis-1', Crypt::decryptString($stored));
    }

    public function test_operating_hours_booking_and_forecast_report(): void
    {
        $admin = $this->userWithRole('Administrator');
        $m = $this->model('Lampe', 'Robe', ['service_hours_limit' => 1000]);
        $a = Asset::create(['asset_model_id' => $m->id, 'status_label_id' => StatusLabel::first()->id, 'purchase_date' => now()->subYear()]);
        $this->actingAs($admin)->post(route('assets.hours', $a), ['hours' => 800])->assertRedirect();
        $this->assertEquals(800.0, $a->fresh()->operating_hours);
        $this->get(route('reports'))->assertOk()->assertSee($a->asset_tag);
    }

    public function test_notify_due_includes_late_returns(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $admin = $this->userWithRole('Administrator');
        $m = $this->model('Spät');
        $a = Asset::create(['asset_model_id' => $m->id, 'status_label_id' => StatusLabel::first()->id]);
        Checkout::create(['asset_id' => $a->id, 'assignee_type' => 'user', 'assignee_id' => $admin->id, 'quantity' => 1, 'checked_out_at' => now()->subDays(3), 'expected_return_at' => now()->subDay()]);
        $this->artisan('inv:notify-due')->assertSuccessful();
        \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\InspectionsDue::class, fn ($n) => $n->lateReturns->count() === 1);
    }
}
