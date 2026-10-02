<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;

class RefreshInspections extends Command
{
    protected $signature = 'inv:refresh-inspections';
    protected $description = 'Berechnet die nächste Prüffälligkeit aller Assets neu.';

    public function handle(): int
    {
        $n = 0;
        Asset::with('model.inspectionTypes')->chunkById(200, function ($assets) use (&$n) {
            foreach ($assets as $a) {
                $a->refreshInspectionDue();
                $n++;
            }
        });
        $this->info("$n Assets aktualisiert.");

        return self::SUCCESS;
    }
}
