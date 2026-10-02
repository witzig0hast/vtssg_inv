<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StocktakeLine extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function stocktake() { return $this->belongsTo(Stocktake::class); }
    public function asset() { return $this->belongsTo(Asset::class)->withTrashed(); }
}
