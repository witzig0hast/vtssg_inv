<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_external' => 'boolean'];
    }

    public function project() { return $this->belongsTo(Project::class); }
    public function model() { return $this->belongsTo(AssetModel::class, 'asset_model_id')->withTrashed(); }
    public function asset() { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function supplier() { return $this->belongsTo(Supplier::class); }

    public function label(): string
    {
        if ($this->is_external) {
            return $this->external_name ?: 'Zumiete';
        }

        return $this->asset ? $this->asset->asset_tag.' · '.$this->asset->displayName() : ($this->model?->full_name ?? '—');
    }

    public function unitPriceCents(): int
    {
        return $this->price_cents ?? ($this->model?->rental_price_cents ?? 0);
    }

    public function isFullyPacked(): bool
    {
        return $this->packed_quantity >= $this->quantity;
    }
}
