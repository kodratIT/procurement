<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartureBatch extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'departure_date', 'return_date', 'office_id', 'capacity', 'pax_count', 'status', 'is_active'];

    protected static function booted(): void
    {
        static::saving(function (self $batch): void {
            if ($batch->return_date !== null
                && $batch->departure_date !== null
                && $batch->return_date->lt($batch->departure_date)) {
                throw new \InvalidArgumentException('return_date must not be earlier than departure_date.');
            }

            if ($batch->pax_count !== null && $batch->pax_count < 1) {
                throw new \InvalidArgumentException('pax_count must be at least 1.');
            }
        });
    }

    protected function casts(): array
    {
        return ['departure_date' => 'date', 'return_date' => 'date', 'office_id' => 'integer', 'capacity' => 'integer', 'pax_count' => 'integer', 'is_active' => 'boolean'];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
