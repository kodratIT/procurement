<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssignmentScope extends Model
{
    use HasFactory, LogsActivity;

    /** @var list<string> */
    public const TYPES = ['office', 'branch', 'department', 'category', 'transaction'];

    protected $fillable = [
        'assignment_id',
        'scope_type',
        'scope_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $scope): void {
            $scope->scope_type = Str::lower((string) $scope->scope_type);

            if (! in_array($scope->scope_type, self::TYPES, true)) {
                throw new InvalidArgumentException('The assignment scope type is invalid.');
            }

            if ($scope->scope_id === null) {
                throw new InvalidArgumentException('An assignment scope requires a scope id.');
            }
        });
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(UserAssignment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rbac')
            ->logOnly(['assignment_id', 'scope_type', 'scope_id'])
            ->logOnlyDirty();
    }
}
