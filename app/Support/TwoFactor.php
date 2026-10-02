<?php

namespace App\Support;

use App\Models\User;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactor
{
    public function __construct(private Google2FA $g = new Google2FA) {}

    public function newSecret(): string
    {
        return $this->g->generateSecretKey(32);
    }

    public function uri(User $user, string $secret): string
    {
        return $this->g->getQRCodeUrl(config('app.name'), $user->email, $secret);
    }

    public function qrDataUri(string $text): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'svgViewBoxSize' => 200,
            'addQuietzone' => true,
            'scale' => 5,
        ]);

        return (new QRCode($options))->render($text);
    }

    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->g->verifyKey($secret, preg_replace('/\s+/', '', $code), 1);
    }

    /** @return list<string> */
    public function newRecoveryCodes(int $n = 8): array
    {
        return collect(range(1, $n))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
    }

    /** Prüft und verbraucht einen Recovery-Code. */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $code = Str::lower(trim($code));
        foreach ($codes as $i => $c) {
            if (hash_equals($c, $code)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
