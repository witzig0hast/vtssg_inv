<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditTest extends TestCase
{
    public function test_changes_are_logged_and_chain_verifies(): void
    {
        $admin = $this->userWithRole('Administrator');
        $this->actingAs($admin);
        $c = Category::create(['name' => 'Test']);
        $c->update(['name' => 'Test2']);
        $c->delete();
        $this->assertGreaterThanOrEqual(3, AuditLog::count());
        $this->assertTrue(AuditLog::verifyChain()['ok']);
        $upd = AuditLog::where('event', 'updated')->latest('id')->first();
        $this->assertSame('Test', $upd->old_values['name']);
        $this->assertSame('Test2', $upd->new_values['name']);
    }

    public function test_sensitive_fields_never_logged(): void
    {
        $this->userWithRole('Administrator');
        $log = AuditLog::where('auditable_type', 'like', '%User')->first();
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }

    public function test_tampering_is_detected(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));
        Category::create(['name' => 'A']);
        Category::create(['name' => 'B']);
        $this->assertTrue(AuditLog::verifyChain()['ok']);
        $row = AuditLog::where('event', 'created')->orderBy('id')->skip(1)->first();
        DB::table('audit_logs')->where('id', $row->id)->update(['summary' => 'manipuliert']);
        $r = AuditLog::verifyChain();
        $this->assertFalse($r['ok']);
        $this->assertSame($row->id, $r['broken_at']);
    }

    public function test_deleted_entry_is_detected(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));
        Category::create(['name' => 'A']);
        Category::create(['name' => 'B']);
        $mid = AuditLog::orderBy('id')->skip(1)->first();
        DB::table('audit_logs')->where('id', $mid->id)->delete();
        $this->assertFalse(AuditLog::verifyChain()['ok']);
    }

    public function test_eloquent_cannot_modify_entries(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));
        Category::create(['name' => 'A']);
        $log = AuditLog::first();
        $this->expectException(\LogicException::class);
        $log->update(['summary' => 'x']);
    }

    public function test_login_events_are_audited(): void
    {
        $u = $this->userWithRole('Lager');
        $this->post('/login', ['email' => $u->email, 'password' => 'bad']);
        $this->post('/login', ['email' => $u->email, 'password' => 'Str0ng!Passw0rd#x']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login']);
    }
}
