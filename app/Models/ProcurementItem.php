<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementItem extends Model
{
    use HasFactory;
    protected $fillable = ['category_id', 'unit_id', 'code', 'name', 'description', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(ProcurementCategory::class, 'category_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(ProcurementUnit::class, 'unit_id'); }
    public function variants(): HasMany { return $this->hasMany(ProcurementVariant::class, 'item_id'); }
}
