<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumableMovement extends Model
{
    protected $guarded = ['id'];

    public function consumable() { return $this->belongsTo(Consumable::class); }
    public function user() { return $this->belongsTo(User::class); }
}
