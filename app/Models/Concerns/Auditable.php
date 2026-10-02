<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

trait Auditable
{
    /** Attribute, die nie im Audit-Log landen. */
    protected array $auditHidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->writeAudit('created', [], $m->getAttributes()));
        static::updated(function ($m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($m->getOriginal(), $changes);
            $m->writeAudit('updated', $old, $changes);
        });
        static::deleted(fn ($m) => $m->writeAudit(method_exists($m, 'isForceDeleting') && $m->isForceDeleting() ? 'force_deleted' : 'deleted', $m->getOriginal(), []));
        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($m) => $m->writeAudit('restored', [], []));
        }
    }

    protected function writeAudit(string $event, array $old, array $new): void
    {
        $clean = fn (array $a) => array_diff_key($a, array_flip($this->auditHidden));
        AuditLog::record($event, $this, $clean($old), $clean($new));
    }

    public function auditLabel(): string
    {
        return $this->asset_tag ?? $this->number ?? $this->name ?? $this->title ?? ('#'.$this->getKey());
    }
}
