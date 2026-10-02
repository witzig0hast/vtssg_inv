<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditVerify extends Command
{
    protected $signature = 'inv:audit-verify';
    protected $description = 'Prüft die Hash-Kette des Audit-Logs auf Manipulation.';

    public function handle(): int
    {
        $r = AuditLog::verifyChain();
        if ($r['ok']) {
            $this->info("Audit-Log intakt ({$r['checked']} Einträge).");

            return self::SUCCESS;
        }
        $this->error("MANIPULATION erkannt ab Eintrag #{$r['broken_at']} (geprüft: {$r['checked']}).");
        logger()->critical('Audit-Log-Kette gebrochen', $r);

        return self::FAILURE;
    }
}
