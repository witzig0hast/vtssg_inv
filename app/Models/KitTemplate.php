<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class KitTemplate extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function items() { return $this->hasMany(KitTemplateItem::class); }
}
