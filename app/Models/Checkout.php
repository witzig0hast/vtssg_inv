<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Checkout extends Model
{
    use Auditable;

    public const CONDITIONS = ['ok' => 'in Ordnung', 'worn' => 'Gebrauchsspuren', 'damaged' => 'Defekt/Beschädigt'];
    public const ASSIGNEES = ['user' => 'Mitarbeiter', 'project' => 'Projekt', 'location' => 'Standort', 'customer' => 'Kunde'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checked_out_at' => 'datetime', 'expected_return_at' => 'datetime', 'returned_at' => 'datetime'];
    }

    public function asset() { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function performer() { return $this->belongsTo(User::class, 'performed_by')->withTrashed(); }
    public function project() { return $this->belongsTo(Project::class); }

    public function assignee()
    {
        $class = match ($this->assignee_type) {
            'user' => User::class,
            'project' => Project::class,
            'location' => Location::class,
            'customer' => Customer::class,
        };

        return $class::withoutGlobalScopes()->find($this->assignee_id);
    }

    public function assigneeLabel(): string
    {
        $a = $this->assignee();

        return $a ? (self::ASSIGNEES[$this->assignee_type].': '.($a->name ?? $a->number ?? '#'.$a->id)) : '—';
    }

    public function isOverdue(): bool
    {
        return $this->returned_at === null && $this->expected_return_at && $this->expected_return_at->isPast();
    }

    public function auditLabel(): string
    {
        return $this->asset?->asset_tag.' → '.$this->assignee_type.'#'.$this->assignee_id;
    }
}
