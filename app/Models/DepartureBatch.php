<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartureBatch extends Model
{
    use HasFactory;
    protected $fillable = ['code', 'name', 'departure_date', 'return_date', 'capacity', 'status', 'is_active'];
    protected function casts(): array { return ['departure_date' => 'date', 'return_date' => 'date', 'capacity' => 'integer', 'is_active' => 'boolean']; }
}
