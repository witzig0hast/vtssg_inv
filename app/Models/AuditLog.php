<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only Audit-Log mit Hash-Kette: jeder Eintrag enthält den Hash des Vorgängers.
 * Nachträgliche Änderungen/Löschungen werden mit `php artisan inv:audit-verify` erkannt.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        // Append-only: Änderungen und Löschungen über Eloquent sind verboten.
        static::updating(fn () => throw new \LogicException('Audit-Log ist unveränderlich.'));
        static::deleting(fn () => throw new \LogicException('Audit-Log ist unveränderlich.'));
    }

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public static function record(string $event, ?Model $subject = null, array $old = [], array $new = [], ?string $summary = null): ?self
    {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        return DB::transaction(function () use ($event, $subject, $old, $new, $summary) {
            // Pessimistisches Lock auf den letzten Eintrag, damit die Kette linear bleibt.
            $prev = self::query()->orderByDesc('id')->lockForUpdate()->first();
            $request = app()->bound('request') ? request() : null;

            $row = [
                'user_id' => auth()->id(),
                'event' => $event,
                'auditable_type' => $subject ? $subject->getMorphClass() : null,
                'auditable_id' => $subject?->getKey(),
                'summary' => $summary ?? ($subject && method_exists($subject, 'auditLabel') ? $subject->auditLabel() : null),
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'prev_hash' => $prev?->hash,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ];
            $row['hash'] = self::computeHash($row);

            return self::unguarded(fn () => self::create($row));
        });
    }

    public static function computeHash(array $row): string
    {
        $payload = json_encode([
            $row['prev_hash'] ?? '',
            $row['user_id'] ?? null,
            $row['event'],
            $row['auditable_type'] ?? null,
            $row['auditable_id'] ?? null,
            $row['summary'] ?? null,
            $row['old_values'] ?? null,
            $row['new_values'] ?? null,
            $row['created_at'],
        ], JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /** @return array{ok:bool,checked:int,broken_at:?int} */
    public static function verifyChain(): array
    {
        $prevHash = null;
        $checked = 0;
        foreach (self::query()->orderBy('id')->cursor() as $log) {
            $row = [
                'prev_hash' => $log->prev_hash,
                'user_id' => $log->user_id,
                'event' => $log->event,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'summary' => $log->summary,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ];
            if ($log->prev_hash !== $prevHash || ! hash_equals($log->hash, self::computeHash($row))) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => $log->id];
            }
            $prevHash = $log->hash;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null];
    }
}
