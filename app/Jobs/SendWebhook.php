<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Services\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 300];

    public function __construct(public string $event, public array $data) {}

    public function handle(): void
    {
        $url = (string) Setting::get('webhook_url', '');
        if ($url === '') {
            return;
        }
        $status = null;
        $error = null;
        try {
            Webhook::assertSafeUrl($url);
            $secret = Setting::get('webhook_secret') ? Crypt::decryptString(Setting::get('webhook_secret')) : '';
            $body = json_encode(['event' => $this->event, 'data' => $this->data], JSON_UNESCAPED_UNICODE);
            $res = Http::timeout(5)->withoutRedirecting()->withHeaders([
                'Content-Type' => 'application/json',
                'X-Inventory-Event' => $this->event,
                'X-Inventory-Signature' => 'sha256='.hash_hmac('sha256', $body, $secret),
            ])->withBody($body, 'application/json')->post($url);
            $status = $res->status();
            if (! $res->successful()) {
                $error = 'HTTP '.$status;
            }
        } catch (\Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 200);
        }
        DB::table('webhook_deliveries')->insert(['event' => $this->event, 'payload' => json_encode($this->data), 'status' => $status, 'error' => $error, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('webhook_deliveries')->where('created_at', '<', now()->subDays(30))->delete();
        if ($error) {
            throw new \RuntimeException($error); // Queue wiederholt mit Backoff
        }
    }
}
