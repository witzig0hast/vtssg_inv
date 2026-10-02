<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stocktake extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function location() { return $this->belongsTo(Location::class); }
    public function starter() { return $this->belongsTo(User::class, 'started_by'); }
    public function lines() { return $this->hasMany(StocktakeLine::class); }
}
