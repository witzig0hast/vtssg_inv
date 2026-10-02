<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Inspection;
use App\Models\InspectionType;
use App\Models\Location;
use App\Models\MaintenanceTicket;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\StatusLabel;
use App\Services\AvailabilityService;
use App\Services\CheckoutService;
use App\Services\InspectionService;
use App\Services\PackingService;
use DomainException;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    private function model(string $name = 'Moving Head', string $tracking = 'serial', array $inspections = []): AssetModel
    {
        $m = AssetModel::create(['name' => $name, 'category_id' => Category::first()->id, 'tracking' => $tracking, 'rental_price_cents' => 5000]);
        foreach ($inspections as $code) {
            $m->inspectionTypes()->attach(InspectionType::where('code', $code)->first());
        }

        return $m;
    }

    private function asset(AssetModel $m, array $attrs = []): Asset
    {
        $a = Asset::create($attrs + ['asset_model_id' => $m->id, 'status_label_id' => StatusLabel::where('is_default', true)->value('id'), 'purchase_date' => now()->subDay()]);
        $a->load('model.inspectionTypes')->refreshInspectionDue();

        return $a->refresh();
    }

    public function test_asset_tags_are_unique_and_sequential(): void
    {
        $m = $this->model();
        $a = $this->asset($m);
        $b = $this->asset($m);
        $this->assertSame('VT-000001', $a->asset_tag);
        $this->assertSame('VT-000002', $b->asset_tag);
        $this->assertNotEmpty($a->uuid);
    }

    public function test_checkout_and_checkin(): void
    {
        $svc = app(CheckoutService::class);
        $u = $this->userWithRole('Lager');
        $a = $this->asset($this->model());
        $co = $svc->checkout($a, 'user', $u->id, $u);
        $this->assertTrue($a->fresh()->isCheckedOut());
        $this->expectException(DomainException::class);
        try {
            $svc->checkout($a, 'user', $u->id, $u); // doppelt
        } finally {
            $svc->checkin($co, 'ok', null, $u);
            $this->assertFalse($a->fresh()->isCheckedOut());
        }
    }

    public function test_cannot_checkout_blocked_or_undeployable(): void
    {
        $svc = app(CheckoutService::class);
        $u = $this->userWithRole('Lager');
        $a = $this->asset($this->model());
        $a->update(['is_blocked' => true, 'blocked_reason' => 'Test']);
        try {
            $svc->checkout($a, 'user', $u->id, $u);
            $this->fail('gesperrt');
        } catch (DomainException $e) {
            $this->assertStringContainsString('gesperrt', $e->getMessage());
        }
        $a->update(['is_blocked' => false, 'status_label_id' => StatusLabel::where('kind', 'undeployable')->value('id')]);
        $this->expectException(DomainException::class);
        $svc->checkout($a, 'user', $u->id, $u);
    }

    public function test_overdue_blocking_inspection_prevents_checkout(): void
    {
        $svc = app(CheckoutService::class);
        $u = $this->userWithRole('Lager');
        $m = $this->model('Kettenzug', 'serial', ['DGUV_V3']);
        $a = $this->asset($m, ['purchase_date' => now()->subMonths(30)]); // 12 Monate Intervall → überfällig
        $this->assertTrue($a->isInspectionOverdue());
        try {
            $svc->checkout($a, 'user', $u->id, $u);
            $this->fail('Prüfung überfällig');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Prüfung überfällig', $e->getMessage());
        }
        // Prüfung erfassen → wieder ausgebbar
        app(InspectionService::class)->record($a->load('model.inspectionTypes'), ['inspection_type_id' => InspectionType::where('code', 'DGUV_V3')->value('id'), 'performed_at' => today()->toDateString(), 'result' => 'passed'], $u);
        $a->refresh();
        $this->assertFalse($a->isInspectionOverdue());
        $this->assertEquals(today()->addMonths(12)->toDateString(), $a->next_inspection_due->toDateString());
        $svc->checkout($a, 'user', $u->id, $u);
        $this->assertTrue($a->fresh()->isCheckedOut());
    }

    public function test_failed_inspection_blocks_asset_and_creates_ticket(): void
    {
        $u = $this->userWithRole('Lager');
        $a = $this->asset($this->model('Motor', 'serial', ['MOTOR']));
        app(InspectionService::class)->record($a->load('model.inspectionTypes'), ['inspection_type_id' => InspectionType::where('code', 'MOTOR')->value('id'), 'performed_at' => today()->toDateString(), 'result' => 'failed', 'notes' => 'Kette verschlissen'], $u);
        $this->assertSame('undeployable', $a->fresh()->status->kind);
        $this->assertDatabaseHas('maintenance_tickets', ['asset_id' => $a->id, 'priority' => 'critical']);
        $this->assertSame(1, Inspection::count());
    }

    public function test_damaged_return_creates_ticket(): void
    {
        $svc = app(CheckoutService::class);
        $u = $this->userWithRole('Lager');
        $a = $this->asset($this->model());
        $co = $svc->checkout($a, 'user', $u->id, $u);
        $svc->checkin($co, 'damaged', 'Linse gebrochen', $u);
        $this->assertSame('undeployable', $a->fresh()->status->kind);
        $this->assertSame(1, MaintenanceTicket::where('asset_id', $a->id)->count());
    }

    public function test_quantity_items_partial_checkout_and_return(): void
    {
        $svc = app(CheckoutService::class);
        $u = $this->userWithRole('Lager');
        $a = $this->asset($this->model('XLR 10m', 'quantity'), ['quantity' => 50]);
        $co = $svc->checkout($a, 'user', $u->id, $u, ['quantity' => 30]);
        $this->assertSame(20, $a->fresh()->availableQuantity());
        try {
            $svc->checkout($a, 'user', $u->id, $u, ['quantity' => 25]);
            $this->fail('zu viel');
        } catch (DomainException) {
        }
        $svc->checkin($co, 'ok', null, $u, ['quantity' => 10]); // Teilrückgabe
        $this->assertSame(30, $a->fresh()->availableQuantity());
        $this->assertSame(20, $a->fresh()->checkedOutQuantity());
    }

    public function test_container_checkout_includes_children(): void
    {
        $svc = app(CheckoutService::class);
        $u = $this->userWithRole('Lager');
        $m = $this->model();
        $case = $this->asset($m, ['is_container' => true]);
        $c1 = $this->asset($m, ['parent_id' => $case->id]);
        $c2 = $this->asset($m, ['parent_id' => $case->id]);
        $co = $svc->checkout($case, 'user', $u->id, $u);
        $this->assertTrue($c1->fresh()->isCheckedOut() && $c2->fresh()->isCheckedOut());
        $svc->checkin($co, 'ok', null, $u);
        $this->assertFalse($c1->fresh()->isCheckedOut());
    }

    private function project(string $start, string $end, string $status = 'planned'): Project
    {
        return Project::create(['number' => Project::nextNumber(), 'name' => 'P'.$start, 'status' => $status, 'setup_at' => $start, 'starts_at' => $start, 'ends_at' => $end, 'teardown_at' => $end]);
    }

    public function test_availability_detects_overbooking_across_projects(): void
    {
        $m = $this->model('LED Par');
        foreach (range(1, 4) as $_) $this->asset($m);
        $p1 = $this->project('2026-12-01 08:00', '2026-12-03 18:00');
        ProjectItem::create(['project_id' => $p1->id, 'asset_model_id' => $m->id, 'quantity' => 3]);
        $p2 = $this->project('2026-12-02 08:00', '2026-12-04 18:00');
        ProjectItem::create(['project_id' => $p2->id, 'asset_model_id' => $m->id, 'quantity' => 2]);
        $p3 = $this->project('2026-12-10 08:00', '2026-12-11 18:00'); // kein Überlapp
        ProjectItem::create(['project_id' => $p3->id, 'asset_model_id' => $m->id, 'quantity' => 4]);

        $svc = app(AvailabilityService::class);
        $this->assertCount(1, $svc->conflicts($p2));
        $this->assertCount(0, $svc->conflicts($p3));
        $this->assertSame(1, $svc->forModel($m, $p2->windowStart(), $p2->windowEnd(), $p2->id)['free']);
    }

    public function test_status_change_blocked_by_conflict_and_cancelled_frees_stock(): void
    {
        $admin = $this->userWithRole('Administrator');
        $m = $this->model('Spot');
        $this->asset($m);
        $p1 = $this->project('2026-12-01 08:00', '2026-12-03 18:00');
        ProjectItem::create(['project_id' => $p1->id, 'asset_model_id' => $m->id, 'quantity' => 1]);
        $p2 = $this->project('2026-12-02 08:00', '2026-12-02 18:00', 'draft');
        ProjectItem::create(['project_id' => $p2->id, 'asset_model_id' => $m->id, 'quantity' => 1]);

        $this->actingAs($admin)->post(route('projects.status', $p2), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->assertSame('draft', $p2->fresh()->status);
        $p1->update(['status' => 'cancelled']);
        $this->post(route('projects.status', $p2), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $p2->fresh()->status);
    }

    public function test_packing_load_unload_via_scan(): void
    {
        $u = $this->userWithRole('Lager');
        $m = $this->model('Wash');
        $a1 = $this->asset($m);
        $a2 = $this->asset($m);
        $p = $this->project('2026-12-01 08:00', '2026-12-03 18:00', 'confirmed');
        ProjectItem::create(['project_id' => $p->id, 'asset_model_id' => $m->id, 'quantity' => 2]);
        $svc = app(PackingService::class);

        $svc->load($p, $a1->asset_tag, $u);
        $this->assertTrue($a1->fresh()->isCheckedOut());
        try {
            $svc->load($p, $a1->asset_tag, $u);
            $this->fail('doppelt');
        } catch (DomainException) {
        }
        $svc->load($p, $a2->asset_tag, $u);
        $this->assertSame('loaded', $p->fresh()->status);

        $other = $this->asset($this->model('Fremdgerät'));
        $this->expectException(DomainException::class);
        try {
            $svc->load($p, $other->asset_tag, $u);
        } finally {
            $svc->unload($p, $a1->asset_tag, $u);
            $svc->unload($p, $a2->asset_tag, $u);
            $this->assertSame('returned', $p->fresh()->status);
            $this->assertFalse($a1->fresh()->isCheckedOut());
        }
    }

    public function test_pack_scan_endpoint(): void
    {
        $u = $this->userWithRole('Techniker');
        $m = $this->model('Spot2');
        $a = $this->asset($m);
        $p = $this->project('2026-12-01 08:00', '2026-12-03 18:00', 'confirmed');
        ProjectItem::create(['project_id' => $p->id, 'asset_model_id' => $m->id, 'quantity' => 1]);
        $this->actingAs($u)->postJson(route('projects.pack.scan', $p), ['code' => $a->asset_tag, 'mode' => 'load'])->assertOk()->assertJson(['ok' => true, 'packed' => 1, 'total' => 1]);
        $this->postJson(route('projects.pack.scan', $p), ['code' => 'NOPE', 'mode' => 'load'])->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_calendar_and_documents_render(): void
    {
        $admin = $this->userWithRole('Administrator');
        $m = $this->model('Doc');
        $this->asset($m);
        $p = $this->project(now()->addDay()->format('Y-m-d 08:00'), now()->addDays(2)->format('Y-m-d 18:00'));
        ProjectItem::create(['project_id' => $p->id, 'asset_model_id' => $m->id, 'quantity' => 1]);
        $this->actingAs($admin)->get(route('projects.calendar'))->assertOk();
        $this->get(route('projects.show', $p))->assertOk();
        foreach (['delivery-note', 'packing-list'] as $t) {
            $r = $this->get(route('projects.document', [$p, $t]))->assertOk();
            $this->assertStringStartsWith('%PDF', $r->getContent());
        }
    }

    public function test_location_is_used_for_asset_creation_form(): void
    {
        $admin = $this->userWithRole('Administrator');
        $m = $this->model();
        $loc = Location::create(['name' => 'Lager', 'type' => 'warehouse']);
        $this->actingAs($admin)->post(route('assets.store'), [
            'asset_model_id' => $m->id, 'status_label_id' => StatusLabel::first()->id, 'location_id' => $loc->id, 'copies' => 3, 'purchase_cost' => '1.299,50', 'serial' => 'S1',
        ])->assertRedirect();
        $this->assertSame(3, Asset::count());
        $this->assertSame(129950, Asset::first()->purchase_cost_cents);
        $this->assertSame(1, Asset::where('serial', 'S1')->count() + Asset::whereNull('serial')->count() - 2);
    }
}
