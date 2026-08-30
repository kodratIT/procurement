<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'guard_name',
        'code',
        'is_active',
    ];

    protected $attributes = [
        'guard_name' => 'web',
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            $role->code ??= Str::upper(Str::slug((string) $role->name, '_'));
        });
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rbac')
            ->logOnly(['name', 'guard_name', 'code', 'is_active'])
            ->logOnlyDirty();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
