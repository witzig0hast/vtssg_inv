<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class InspectionType extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['blocks_checkout' => 'boolean'];
    }

    public function assetModels()
    {
        return $this->belongsToMany(AssetModel::class, 'asset_model_inspection_type')->withPivot('interval_months');
    }
}
