<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['office_id', 'branch_id', 'code', 'name', 'is_active', 'disabled_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'disabled_at' => 'datetime'];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
