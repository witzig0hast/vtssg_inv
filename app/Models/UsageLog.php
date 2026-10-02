<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    protected $guarded = ['id'];

    public function asset() { return $this->belongsTo(Asset::class); }
    public function user() { return $this->belongsTo(User::class); }
}
