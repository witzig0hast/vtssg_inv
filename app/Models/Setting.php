<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public const DEFAULTS = [
        'company_name' => 'Veranstaltungstechnik',
        'company_address' => '',
        'asset_tag_prefix' => 'VT-',
        'asset_tag_digits' => '6',
        'inspection_warn_days' => '30',
        'notify_email' => '',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $all = Cache::remember('settings.all', 300, fn () => Schema::hasTable('settings') ? self::pluck('value', 'key')->all() : []);
        } catch (\Throwable) {
            $all = [];
        }

        return $all[$key] ?? self::DEFAULTS[$key] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
