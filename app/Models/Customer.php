<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id'];

    public function projects() { return $this->hasMany(Project::class); }
}
