<?php

namespace App\Services;

use App\Jobs\SendWebhook;
use App\Models\Setting;

class Webhook
{
    public static function enabled(): bool
    {
        return (string) Setting::get('webhook_url', '') !== '';
    }

    /** Stellt ein Ereignis in die Queue (nur wenn eine Ziel-URL konfiguriert ist). */
    public static function fire(string $event, array $data): void
    {
        if (! self::enabled()) {
            return;
        }
        SendWebhook::dispatch($event, $data + ['occurred_at' => now()->toIso8601String()]);
    }

    /** Schutz vor SSRF: nur https, keine privaten/lokalen Ziele. */
    public static function assertSafeUrl(string $url): void
    {
        $p = parse_url($url);
        if (! $p || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
            throw new \InvalidArgumentException('Nur https-URLs sind erlaubt.');
        }
        $ips = filter_var($p['host'], FILTER_VALIDATE_IP) ? [$p['host']] : (gethostbynamel($p['host']) ?: []);
        if (! $ips) {
            throw new \InvalidArgumentException('Host nicht auflösbar.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \InvalidArgumentException('Ziel liegt in einem privaten/reservierten Netz.');
            }
        }
    }
}
