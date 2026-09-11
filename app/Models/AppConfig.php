<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    protected $table    = 'app_configs';
    protected $fillable = ['key', 'value', 'description'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    public static function getJson(string $key, mixed $default = []): mixed
    {
        $raw = static::get($key);
        if ($raw === null) return $default;
        $decoded = json_decode($raw, true);
        return $decoded ?? $default;
    }

    public static function set(string $key, mixed $value, string $description = ''): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description]
        );
    }

    public static function setJson(string $key, mixed $value, string $description = ''): void
    {
        static::set($key, json_encode($value), $description);
    }
}
