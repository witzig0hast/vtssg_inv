<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manufacturer extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id'];

    public function assetModels() { return $this->hasMany(AssetModel::class); }
}
