<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Asset extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_until' => 'date',
            'next_inspection_due' => 'date',
            'is_container' => 'boolean',
            'is_blocked' => 'boolean',
            'operating_hours' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $a) {
            $a->uuid ??= (string) Str::uuid();
            $a->asset_tag = $a->asset_tag ?: self::nextTag();
        });
    }

    public static function nextTag(): string
    {
        $prefix = Setting::get('asset_tag_prefix', 'VT-');
        $digits = (int) Setting::get('asset_tag_digits', 6);
        $n = (int) (self::withTrashed()->where('asset_tag', 'like', $prefix.'%')->count()) + 1;
        do {
            $tag = $prefix.str_pad((string) $n++, $digits, '0', STR_PAD_LEFT);
        } while (self::withTrashed()->where('asset_tag', $tag)->exists());

        return $tag;
    }

    public function model() { return $this->belongsTo(AssetModel::class, 'asset_model_id')->withTrashed(); }
    public function status() { return $this->belongsTo(StatusLabel::class, 'status_label_id'); }
    public function location() { return $this->belongsTo(Location::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function checkouts() { return $this->hasMany(Checkout::class); }
    public function inspections() { return $this->hasMany(Inspection::class); }
    public function tickets() { return $this->hasMany(MaintenanceTicket::class); }
    public function usageLogs() { return $this->hasMany(UsageLog::class); }
    public function attachments() { return $this->morphMany(Attachment::class, 'attachable'); }

    public function openCheckouts() { return $this->checkouts()->whereNull('returned_at'); }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $q->where(function ($w) use ($like) {
            $w->where('asset_tag', 'like', $like)
                ->orWhere('serial', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhereHas('model', fn ($m) => $m->where('name', 'like', $like)
                    ->orWhereHas('manufacturer', fn ($x) => $x->where('name', 'like', $like)));
        });
    }

    public function scopeDeployable(Builder $q): Builder
    {
        return $q->where('is_blocked', false)
            ->whereHas('status', fn ($s) => $s->where('kind', 'deployable'));
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->model?->full_name ?? 'Asset');
    }

    public function auditLabel(): string
    {
        return $this->asset_tag;
    }

    public function checkedOutQuantity(): int
    {
        return (int) $this->openCheckouts()->sum('quantity');
    }

    public function availableQuantity(): int
    {
        return max(0, $this->quantity - $this->checkedOutQuantity());
    }

    public function isCheckedOut(): bool
    {
        return $this->checkedOutQuantity() > 0;
    }

    public function currentCheckout(): ?Checkout
    {
        return $this->openCheckouts()->latest('checked_out_at')->first();
    }

    /** Fälligkeit je Prüfart: [inspection_type_id => Carbon]. */
    public function inspectionDueDates(): array
    {
        $out = [];
        $baseline = Carbon::parse($this->purchase_date ?? $this->created_at ?? now());
        foreach ($this->model->inspectionTypes as $type) {
            $months = $type->pivot->interval_months ?: $type->default_interval_months;
            $last = $this->inspections()->where('inspection_type_id', $type->id)
                ->where('result', '!=', 'failed')->orderByDesc('performed_at')->first();
            $out[$type->id] = [
                'type' => $type,
                'due' => $last
                    ? ($last->next_due ?? Carbon::parse($last->performed_at)->addMonths($months))
                    : $baseline->copy()->addMonths($months),
                'last' => $last,
            ];
        }

        return $out;
    }

    public function refreshInspectionDue(): void
    {
        $dates = collect($this->inspectionDueDates())->pluck('due');
        $this->forceFill(['next_inspection_due' => $dates->isEmpty() ? null : $dates->min()->toDateString()])
            ->saveQuietly();
    }

    /** Prüfarten, die überfällig sind UND die Ausgabe blockieren. */
    public function blockingOverdueInspections(): array
    {
        return collect($this->inspectionDueDates())
            ->filter(fn ($i) => $i['type']->blocks_checkout && $i['due']->lt(today()))
            ->all();
    }

    public function isInspectionOverdue(): bool
    {
        return $this->next_inspection_due !== null && $this->next_inspection_due->lt(today());
    }

    public function serviceHoursLimit(): ?int
    {
        return $this->model->service_hours_limit;
    }

    public function serviceHoursRemaining(): ?float
    {
        $limit = $this->serviceHoursLimit();

        return $limit ? max(0, $limit - $this->operating_hours) : null;
    }

    public function qrPayload(): string
    {
        return $this->asset_tag;
    }
}
