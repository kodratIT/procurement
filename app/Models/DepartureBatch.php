<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartureBatch extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'departure_date', 'return_date', 'capacity', 'status', 'is_active'];

    protected static function booted(): void
    {
        static::saving(function (self $batch): void {
            if ($batch->return_date !== null
                && $batch->departure_date !== null
                && $batch->return_date->lt($batch->departure_date)) {
                throw new \InvalidArgumentException('return_date must not be earlier than departure_date.');
            }
        });
    }

    protected function casts(): array
    {
        return ['departure_date' => 'date', 'return_date' => 'date', 'capacity' => 'integer', 'is_active' => 'boolean'];
    }
}
