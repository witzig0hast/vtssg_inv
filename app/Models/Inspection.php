<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    use Auditable;

    public const RESULTS = ['passed' => 'Bestanden', 'conditional' => 'Bestanden mit Auflagen', 'failed' => 'Nicht bestanden'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['performed_at' => 'date', 'next_due' => 'date'];
    }

    public function asset() { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function type() { return $this->belongsTo(InspectionType::class, 'inspection_type_id'); }
    public function performer() { return $this->belongsTo(User::class, 'performed_by')->withTrashed(); }
    public function attachments() { return $this->morphMany(Attachment::class, 'attachable'); }

    public function auditLabel(): string
    {
        return ($this->asset?->asset_tag ?? '?').' / '.($this->type?->code ?? '?');
    }
}
