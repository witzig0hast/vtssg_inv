<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetModel extends Model
{
    use Auditable, SoftDeletes;

    public const TRACKING = ['serial' => 'Einzelgerät (Seriennummer)', 'quantity' => 'Mengenartikel (z. B. Kabel)'];

    protected $guarded = ['id'];

    public function manufacturer() { return $this->belongsTo(Manufacturer::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function assets() { return $this->hasMany(Asset::class); }

    public function inspectionTypes()
    {
        return $this->belongsToMany(InspectionType::class, 'asset_model_inspection_type')->withPivot('interval_months');
    }

    public function isQuantity(): bool
    {
        return $this->tracking === 'quantity';
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->manufacturer?->name ? $this->manufacturer->name.' ' : '').$this->name);
    }
}
