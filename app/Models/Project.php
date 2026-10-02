<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use Auditable, SoftDeletes;

    public const STATUSES = [
        'draft' => 'Entwurf', 'planned' => 'Geplant', 'confirmed' => 'Bestätigt', 'loaded' => 'Beladen',
        'running' => 'Im Einsatz', 'returned' => 'Zurück im Lager', 'closed' => 'Abgeschlossen', 'cancelled' => 'Storniert',
    ];

    /** Status, in denen Material reserviert ist. */
    public const RESERVING = ['planned', 'confirmed', 'loaded', 'running'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['setup_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'teardown_at' => 'datetime'];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function manager() { return $this->belongsTo(User::class, 'manager_id')->withTrashed(); }
    public function venueLocation() { return $this->belongsTo(Location::class, 'venue_location_id'); }
    public function items() { return $this->hasMany(ProjectItem::class)->orderBy('group')->orderBy('id'); }
    public function checkouts() { return $this->hasMany(Checkout::class); }
    public function attachments() { return $this->morphMany(Attachment::class, 'attachable'); }

    public static function nextNumber(): string
    {
        $prefix = now()->format('Y').'-';
        $last = self::withTrashed()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function windowStart(): ?Carbon
    {
        return $this->setup_at ?? $this->starts_at;
    }

    public function windowEnd(): ?Carbon
    {
        return $this->teardown_at ?? $this->ends_at;
    }

    /** Projekte, deren Zeitfenster [from, to] schneidet. */
    public function scopeOverlapping(Builder $q, Carbon $from, Carbon $to): Builder
    {
        return $q->whereRaw('COALESCE(setup_at, starts_at) <= ?', [$to])
            ->whereRaw('COALESCE(teardown_at, ends_at) >= ?', [$from]);
    }

    public function scopeReserving(Builder $q): Builder
    {
        return $q->whereIn('status', self::RESERVING);
    }

    public function rentalDays(): int
    {
        if (! $this->starts_at || ! $this->ends_at) {
            return 1;
        }

        return max(1, $this->starts_at->copy()->startOfDay()->diffInDays($this->ends_at->copy()->startOfDay()) + 1);
    }

    public function auditLabel(): string
    {
        return $this->number.' '.$this->name;
    }
}
