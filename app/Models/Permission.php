<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'guard_name',
        'code',
        'module',
        'action',
    ];

    protected $attributes = [
        'guard_name' => 'web',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $permission): void {
            $permission->code ??= Str::upper(Str::slug((string) $permission->name, '_'));

            if ($permission->module === null || $permission->action === null) {
                [$module, $action] = array_pad(explode('.', (string) $permission->name, 2), 2, null);
                $permission->module ??= $module;
                $permission->action ??= $action;
            }
        });
    }

    public function assignmentOverrides(): HasMany
    {
        return $this->hasMany(AssignmentPermissionOverride::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rbac')
            ->logOnly(['name', 'guard_name', 'code', 'module', 'action'])
            ->logOnlyDirty();
    }
}
