<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Salinan penuh satu topik User Guide untuk satu bahasa (lihat migrasi untuk semantiknya). */
class UserGuideTopic extends Model
{
    use Auditable;

    protected static string $auditModule = 'User Guide';

    /** Isi artikel panjang; yang dicatat cukup metadatanya. */
    protected static array $auditExcept = ['body'];

    protected $fillable = [
        'key', 'locale', 'chapter', 'sort_order', 'title', 'summary',
        'requires', 'body', 'updated_on', 'base_hash', 'edited_by',
    ];

    protected $casts = [
        'requires'   => 'array',
        'sort_order' => 'integer',
        'updated_on' => 'date:Y-m-d',
    ];

    /** Bentuk yang sama dengan hasil UserGuideService::parseFile(). */
    public function toTopic(): array
    {
        return [
            'key'      => $this->key,
            'chapter'  => $this->chapter,
            'order'    => $this->sort_order,
            'title'    => $this->title,
            'summary'  => $this->summary,
            'requires' => array_values((array) $this->requires),
            'updated'  => $this->updated_on?->format('Y-m-d'),
            'body'     => trim($this->body),
        ];
    }
}
