<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssignmentPermissionOverride extends Model
{
    use HasFactory, LogsActivity;

    public const ALLOW = 'allow';

    public const DENY = 'deny';

    /** @var list<string> */
    public const EFFECTS = [self::ALLOW, self::DENY];

    protected $fillable = [
        'assignment_id',
        'permission_id',
        'effect',
    ];

    protected $attributes = [
        'effect' => self::ALLOW,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $override): void {
            $override->effect = Str::lower((string) $override->effect);

            if (! in_array($override->effect, self::EFFECTS, true)) {
                throw new InvalidArgumentException('The assignment permission override effect is invalid.');
            }
        });
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(UserAssignment::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rbac')
            ->logOnly(['assignment_id', 'permission_id', 'effect'])
            ->logOnlyDirty();
    }
}
