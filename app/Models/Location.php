<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id'];

    public const TYPES = ['warehouse' => 'Lager', 'shelf' => 'Regal/Fach', 'vehicle' => 'Fahrzeug', 'venue' => 'Veranstaltungsort', 'workshop' => 'Werkstatt', 'other' => 'Sonstiges'];

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function assets() { return $this->hasMany(Asset::class); }

    public function getPathAttribute(): string
    {
        $names = [$this->name];
        $p = $this->parent;
        $guard = 0;
        while ($p && $guard++ < 10) { array_unshift($names, $p->name); $p = $p->parent; }
        return implode(' › ', $names);
    }
}
