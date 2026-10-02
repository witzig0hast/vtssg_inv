<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Consumable;
use App\Models\KitTemplate;
use App\Models\MaintenanceTicket;
use App\Models\Project;
use App\Models\Stocktake;
use App\Services\CheckoutService;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

/** Rendert jede Seite mit realistischen Demo-Daten – fängt Blade-/Query-Fehler ab. */
class SmokeTest extends TestCase
{
    public function test_every_page_renders_with_demo_data(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = $this->userWithRole('Administrator', ['email' => 'smoke@test.local']);
        $this->actingAs($admin);
        $this->withoutExceptionHandling();

        $asset = $this->freeAsset();
        $case = Asset::create(['asset_model_id' => $asset->asset_model_id, 'status_label_id' => $asset->status_label_id, 'is_container' => true, 'name' => 'Case 1']);
        $asset->update(['parent_id' => $case->id]);
        $project = Project::first();
        $model = AssetModel::first();
        $co = app(CheckoutService::class)->checkout($asset->fresh(), 'user', $admin->id, $admin, ['expected_return_at' => now()->subDay()]);
        $ticket = MaintenanceTicket::create(['asset_id' => $case->id, 'title' => 'Test', 'reported_by' => $admin->id]);
        $st = Stocktake::create(['name' => 'Inv', 'location_id' => $asset->location_id, 'started_by' => $admin->id]);
        $kit = KitTemplate::create(['name' => 'Kit']);
        $kit->items()->create(['asset_model_id' => $model->id, 'quantity' => 2]);

        $urls = [
            '/', '/search?q=Robe', '/search?q=nichts-da', '/profile', '/scan', '/scan/lookup?code='.$asset->asset_tag,
            '/assets', '/assets?state=out&q=Robe&sort=next_inspection_due&dir=desc', '/assets?state=overdue', '/assets?state=blocked', '/assets/create', '/assets/create?model='.$model->id.'&parent='.$case->id,
            '/assets/import', '/assets/import/template', '/assets/export', "/assets/{$asset->id}", "/assets/{$case->id}", "/assets/{$asset->id}/edit", "/assets/{$asset->id}/checkout",
            '/labels?ids[]='.$asset->id.'&format=sheet', '/checkouts/open',
            '/models', '/models/create', "/models/{$model->id}", "/models/{$model->id}/edit",
            '/consumables', '/consumables?low=1', '/consumables/create', '/consumables/'.Consumable::first()->id, '/consumables/'.Consumable::first()->id.'/edit',
            '/projects', '/projects?status=planned', '/projects/calendar', '/projects/calendar?month=2026-12', '/projects/create', "/projects/{$project->id}", "/projects/{$project->id}/edit", "/projects/{$project->id}/pack",
            "/projects/{$project->id}/document/delivery-note", "/projects/{$project->id}/document/packing-list",
            '/kits', '/kits/create', "/kits/{$kit->id}/edit",
            '/inspections', '/inspections?scope=overdue', '/inspections?scope=all', '/inspections/create', "/inspections/create?asset={$asset->id}",
            '/maintenance', '/maintenance?status=all', '/maintenance/create', "/maintenance/create?asset={$asset->id}", "/maintenance/{$ticket->id}",
            '/stocktakes', "/stocktakes/{$st->id}", '/reports', '/audit', '/audit?verify=1&event=login', '/users', '/users/create', "/users/{$admin->id}/edit", '/settings',
        ];
        foreach (['categories', 'manufacturers', 'suppliers', 'locations', 'status-labels', 'customers', 'inspection-types'] as $r) {
            $class = \App\Support\MasterData::get($r)['model'];
            array_push($urls, "/master/$r", "/master/$r/create", "/master/$r/".$class::first()->id.'/edit');
        }

        foreach ($urls as $url) {
            $res = $this->get($url);
            $this->assertTrue(in_array($res->getStatusCode(), [200], true), "$url → ".$res->getStatusCode().' '.substr(strip_tags((string) $res->getContent()), 0, 300));
        }

        // Zustandsändernde Endpunkte
        $this->post("/checkouts/{$co->id}/checkin", ['condition' => 'damaged', 'note' => 'kaputt'])->assertRedirect();
        $this->post('/stocktakes/'.$st->id.'/scan', ['code' => $asset->asset_tag], ['Accept' => 'application/json'])->assertOk();
        $this->get('/stocktakes/'.$st->id)->assertOk();
        $this->postJson('/scan/action', ['code' => $case->asset_tag, 'action' => 'checkout_user'])->assertOk();
    }

    public function test_api_with_token(): void
    {
        $this->seed(DemoSeeder::class);
        $u = $this->userWithRole('Techniker', ['email' => 'api@test.local']);
        $tok = $u->createToken('t')->plainTextToken;
        $a = $this->freeAsset();
        $h = ['Authorization' => 'Bearer '.$tok];
        $this->getJson('/api/v1/assets', [])->assertUnauthorized();
        $this->getJson('/api/v1/assets?q='.$a->asset_tag, $h)->assertOk()->assertJsonPath('data.0.asset_tag', $a->asset_tag);
        $this->getJson('/api/v1/assets/'.$a->uuid, $h)->assertOk();
        $this->postJson('/api/v1/assets/'.$a->asset_tag.'/checkout', ['assignee_type' => 'user', 'assignee_id' => $u->id], $h)->assertCreated();
        $this->getJson('/api/v1/projects', $h)->assertOk();
    }

    private function freeAsset(): Asset
    {
        return Asset::with('model.inspectionTypes')->get()->first(fn ($a) => ! $a->blockingOverdueInspections() && ! $a->model->isQuantity());
    }
}
