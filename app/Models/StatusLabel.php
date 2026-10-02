<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class StatusLabel extends Model
{
    use Auditable;

    public const KINDS = ['deployable' => 'Ausgebbar', 'pending' => 'In Klärung', 'undeployable' => 'Nicht ausgebbar (defekt/Werkstatt)', 'archived' => 'Archiviert (ausgemustert/verloren)'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function assets() { return $this->hasMany(Asset::class); }

    public function isDeployable(): bool
    {
        return $this->kind === 'deployable';
    }
}
