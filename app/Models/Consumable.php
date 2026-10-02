<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consumable extends Model
{
    use Auditable, SoftDeletes;

    public const REASONS = ['purchase' => 'Einkauf/Zugang', 'usage' => 'Verbrauch', 'correction' => 'Korrektur', 'loss' => 'Schwund'];

    protected $guarded = ['id'];

    public function category() { return $this->belongsTo(Category::class); }
    public function manufacturer() { return $this->belongsTo(Manufacturer::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function movements() { return $this->hasMany(ConsumableMovement::class)->latest(); }

    public function isLow(): bool
    {
        return $this->min_quantity > 0 && $this->quantity <= $this->min_quantity;
    }
}
