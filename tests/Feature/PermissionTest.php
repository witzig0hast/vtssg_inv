<?php

namespace Tests\Feature;

use Tests\TestCase;

class PermissionTest extends TestCase
{
    public function test_technician_boundaries(): void
    {
        $t = $this->userWithRole('Techniker');
        $this->actingAs($t);
        $this->get('/assets')->assertOk();
        $this->get('/users')->assertForbidden();
        $this->get('/audit')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/assets/create')->assertForbidden();
        $this->get('/master/categories/create')->assertForbidden();
        $this->get('/master/categories')->assertOk(); // lesen erlaubt
    }

    public function test_read_only_cannot_checkout(): void
    {
        $r = $this->userWithRole('Nur Lesen');
        $this->actingAs($r)->get('/projects')->assertOk();
        $this->get('/checkouts/open')->assertForbidden();
        $this->get('/projects/create')->assertForbidden();
    }

    public function test_admin_has_all_access(): void
    {
        $a = $this->userWithRole('Administrator');
        foreach (['/users', '/audit', '/settings', '/reports', '/inspections', '/master/inspection-types'] as $url) {
            $this->actingAs($a)->get($url)->assertOk();
        }
    }

    public function test_last_admin_cannot_be_demoted_or_deleted(): void
    {
        $a = $this->userWithRole('Administrator');
        $this->actingAs($a)->put(route('users.update', $a), ['name' => 'X', 'email' => $a->email, 'role' => 'Lager', 'is_active' => 1])->assertSessionHasErrors('role');
        $this->assertTrue($a->fresh()->hasRole('Administrator'));
        $this->delete(route('users.destroy', $a))->assertSessionHasErrors('delete');
    }

    public function test_deactivating_user_revokes_tokens(): void
    {
        $a = $this->userWithRole('Administrator');
        $u = $this->userWithRole('Lager');
        $u->createToken('t');
        $this->actingAs($a)->put(route('users.update', $u), ['name' => 'L', 'email' => $u->email, 'role' => 'Lager'])->assertRedirect();
        $this->assertFalse($u->fresh()->is_active);
        $this->assertSame(0, $u->tokens()->count());
    }

    public function test_attachment_access_follows_object_permissions(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->userWithRole('Administrator');
        $m = \App\Models\AssetModel::create(['name' => 'X', 'category_id' => \App\Models\Category::first()->id]);
        $a = \App\Models\Asset::create(['asset_model_id' => $m->id, 'status_label_id' => \App\Models\StatusLabel::first()->id]);
        $file = \Illuminate\Http\UploadedFile::fake()->create('handbuch.pdf', 20, 'application/pdf');
        $this->actingAs($admin)->post(route('attachments.store', ['asset', $a->id]), ['file' => $file])->assertRedirect();
        $att = $a->attachments()->first();
        $this->assertNotNull($att);
        $this->assertStringNotContainsString('handbuch', $att->path); // zufälliger Dateiname
        $this->get(route('attachments.download', $att))->assertOk();

        // gefährliche Typen werden abgelehnt
        $bad = \Illuminate\Http\UploadedFile::fake()->create('shell.php', 1, 'application/x-php');
        $this->post(route('attachments.store', ['asset', $a->id]), ['file' => $bad])->assertSessionHasErrors('file');
        $svg = \Illuminate\Http\UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml');
        $this->post(route('attachments.store', ['asset', $a->id]), ['file' => $svg])->assertSessionHasErrors('file');

        // Techniker: darf ansehen, aber nicht hochladen/löschen
        $t = $this->userWithRole('Techniker');
        $this->actingAs($t)->get(route('attachments.download', $att))->assertOk();
        $this->post(route('attachments.store', ['asset', $a->id]), ['file' => \Illuminate\Http\UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')])->assertForbidden();
        $this->delete(route('attachments.destroy', $att))->assertForbidden();
    }
}
