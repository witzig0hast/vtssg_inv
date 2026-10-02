<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class MaintenanceTicket extends Model
{
    use Auditable;

    public const PRIORITIES = ['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch', 'critical' => 'Kritisch'];
    public const STATUSES = ['open' => 'Offen', 'in_progress' => 'In Arbeit', 'waiting_parts' => 'Wartet auf Teile', 'done' => 'Erledigt', 'cancelled' => 'Verworfen'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['blocks_asset' => 'boolean', 'closed_at' => 'datetime'];
    }

    public function asset() { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by')->withTrashed(); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to')->withTrashed(); }
    public function project() { return $this->belongsTo(Project::class); }
    public function comments() { return $this->hasMany(TicketComment::class)->latest(); }
    public function attachments() { return $this->morphMany(Attachment::class, 'attachable'); }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'waiting_parts'], true);
    }
}
