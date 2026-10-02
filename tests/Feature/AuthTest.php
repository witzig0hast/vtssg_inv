<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TwoFactor;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/assets')->assertRedirect('/login');
    }

    public function test_login_success_and_failure(): void
    {
        $u = $this->userWithRole('Administrator');
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $u->email, 'password' => 'Str0ng!Passw0rd#x'])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }

    public function test_account_locks_after_too_many_failures(): void
    {
        $u = $this->userWithRole('Lager');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $u->email, 'password' => 'nope']);
        }
        $this->assertTrue($u->fresh()->isLocked());
        // auch mit richtigem Passwort gesperrt
        $this->post('/login', ['email' => $u->email, 'password' => 'Str0ng!Passw0rd#x'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $u = $this->userWithRole('Lager', ['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'Str0ng!Passw0rd#x'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_must_change_password_redirects_to_profile(): void
    {
        $u = $this->userWithRole('Lager', ['must_change_password' => true]);
        $this->actingAs($u)->get('/assets')->assertRedirect(route('profile.edit'));
        $this->actingAs($u)->get('/profile')->assertOk();
    }

    public function test_two_factor_challenge_blocks_until_verified(): void
    {
        $g = new Google2FA;
        $secret = $g->generateSecretKey();
        $u = $this->userWithRole('Administrator');
        $u->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => ['abcde-fghij'], 'two_factor_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => $u->email, 'password' => 'Str0ng!Passw0rd#x'])->assertRedirect(route('two-factor.challenge'));
        $this->get('/assets')->assertRedirect(route('two-factor.challenge'));
        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->get('/assets')->assertRedirect(route('two-factor.challenge'));
        $this->post('/two-factor', ['code' => $g->getCurrentOtp($secret)])->assertRedirect('/');
        $this->get('/assets')->assertOk();
    }

    public function test_recovery_code_is_single_use(): void
    {
        $u = $this->userWithRole('Administrator');
        $u->forceFill(['two_factor_secret' => (new TwoFactor)->newSecret(), 'two_factor_recovery_codes' => ['abcde-fghij', 'klmno-pqrst'], 'two_factor_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => $u->email, 'password' => 'Str0ng!Passw0rd#x']);
        $this->post('/two-factor', ['recovery_code' => 'abcde-fghij'])->assertRedirect('/');
        $this->assertSame(['klmno-pqrst'], $u->fresh()->two_factor_recovery_codes);
    }

    public function test_2fa_enrollment_flow(): void
    {
        $u = $this->userWithRole('Lager');
        $this->actingAs($u)->post('/profile/2fa/start')->assertRedirect();
        $secret = session('2fa_setup_secret');
        $this->assertNotEmpty($secret);
        $this->post('/profile/2fa/confirm', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->post('/profile/2fa/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect();
        $this->assertTrue($u->fresh()->hasTwoFactor());
        $this->assertCount(8, $u->fresh()->two_factor_recovery_codes);
    }

    public function test_password_policy_enforced(): void
    {
        $u = $this->userWithRole('Lager');
        $this->actingAs($u)->put('/profile/password', ['current_password' => 'Str0ng!Passw0rd#x', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->put('/profile/password', ['current_password' => 'Str0ng!Passw0rd#x', 'password' => 'Ganz-Neues-Passwort-7391', 'password_confirmation' => 'Ganz-Neues-Passwort-7391'])->assertSessionHasNoErrors();
    }

    public function test_security_headers_present(): void
    {
        $r = $this->get('/login');
        $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'self'", $r->headers->get('Content-Security-Policy'));
    }
}
