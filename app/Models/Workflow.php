<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(WorkflowBinding::class);
    }

    public function activeVersion(): ?WorkflowVersion
    {
        return $this->versions()->where('status', 'active')->latest('version_number')->first();
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function retire(): bool
    {
        return $this->update(['is_active' => false]);
    }
}
