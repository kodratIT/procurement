<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Metadata for a file stored on the private attachment disk.
 *
 * The ERD describes uploader IDs as UUIDs, but this application currently
 * uses auto-incrementing bigint users.id values. Keep uploader_id aligned
 * with that deployed schema until a deliberate UUID migration is made.
 */
class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'uploader_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'collection',
        'disk',
        'metadata',
    ];

    /** Stored paths and disk names are not exposed by default serialization. */
    protected $hidden = [
        'path',
        'disk',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachable_id' => 'integer',
            'uploader_id' => 'integer',
            'size' => 'integer',
            'metadata' => 'array',
        ];
    }
}
