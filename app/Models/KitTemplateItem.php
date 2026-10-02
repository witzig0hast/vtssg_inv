<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitTemplateItem extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    public function model() { return $this->belongsTo(AssetModel::class, 'asset_model_id'); }
}
