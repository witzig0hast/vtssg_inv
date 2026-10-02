<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleted(fn (self $a) => Storage::disk($a->disk)->delete($a->path));
    }

    public function attachable() { return $this->morphTo(); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by')->withTrashed(); }
}
