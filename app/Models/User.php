<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\ProcurementPermissions;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'avatar', 'last_login_at', 'last_token_sync_at', 'is_active', 'keycloak_sub'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $attributes = [
        'is_active' => true,
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasActiveAssignment();
    }

    public function hasActiveAssignment(): bool
    {
        return $this->is_active && $this->assignments()->currentlyActive()->exists();
    }

    public function hasPermissionInAssignment(UserAssignment $assignment, string $permission): bool
    {
        return $assignment->user_id === $this->getKey() && $assignment->allows($permission);
    }

    public function can($ability, $arguments = []): bool
    {
        if (parent::can($ability, $arguments)) {
            return true;
        }

        if (! is_string($ability) || ! str_contains($ability, ':')) {
            return false;
        }

        $fallback = match ($ability) {
            'ViewAny:PurchaseRequest', 'View:PurchaseRequest', 'Create:PurchaseRequest', 'Update:PurchaseRequest' => ProcurementPermissions::VIEW,
            'ViewAny:Quotation' => ProcurementPermissions::UPDATE,
            'View:Quotation' => ProcurementPermissions::VIEW,
            'Create:Quotation', 'Update:Quotation', 'Delete:Quotation' => ProcurementPermissions::UPDATE,
            'Submit:PurchaseRequest', 'Recommend:Quotation' => ProcurementPermissions::UPDATE,
            'ViewAny:PurchaseOrder', 'View:PurchaseOrder' => ProcurementPermissions::VIEW,
            'ViewAny:Invoice', 'View:Invoice' => ProcurementPermissions::VIEW,
            'ViewAny:Distribution', 'View:Distribution' => ProcurementPermissions::VIEW,
            'ViewAny:Vendor', 'View:Vendor', 'ViewAny:VendorItem', 'View:VendorItem' => ProcurementPermissions::VIEW,
            'Create:Vendor', 'Update:Vendor', 'Delete:Vendor', 'Deactivate:Vendor', 'Activate:Vendor', 'Create:VendorItem', 'Update:VendorItem', 'Delete:VendorItem' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:Branch', 'View:Branch', 'Create:Branch', 'Update:Branch' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:Office', 'View:Office', 'Create:Office', 'Update:Office' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:Department', 'View:Department', 'Create:Department', 'Update:Department' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:CostCenter', 'View:CostCenter', 'Create:CostCenter', 'Update:CostCenter' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:Budget', 'View:Budget', 'Create:Budget', 'Update:Budget' => ProcurementPermissions::MANAGE_FINANCE,
            'ViewAny:Pilgrim', 'View:Pilgrim' => ProcurementPermissions::VIEW,
            'ViewAny:UmrahBatch', 'View:UmrahBatch' => ProcurementPermissions::VIEW,
            'ViewAny:SampleShipment', 'View:SampleShipment' => ProcurementPermissions::VIEW,
            'ViewAny:UserAssignment', 'View:UserAssignment', 'Create:UserAssignment', 'Update:UserAssignment' => ProcurementPermissions::MANAGE_USERS,
            'ViewAny:Workflow', 'View:Workflow', 'Create:Workflow', 'Update:Workflow' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:WorkflowStep', 'View:WorkflowStep' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:WorkflowVersion', 'View:WorkflowVersion' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:ProcurementItem', 'View:ProcurementItem' => ProcurementPermissions::VIEW,
            'ViewAny:ProcurementCategory', 'View:ProcurementCategory', 'View:ProcurementCategory' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:ProcurementUnit', 'View:ProcurementUnit' => ProcurementPermissions::VIEW,
            'ViewAny:ProcurementVariant', 'View:ProcurementVariant' => ProcurementPermissions::VIEW,
            'ViewAny:ProcurementField', 'View:ProcurementField' => ProcurementPermissions::VIEW,
            'ViewAny:ApproverMapping', 'View:ApproverMapping', 'Create:ApproverMapping', 'Update:ApproverMapping', 'Delete:ApproverMapping' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:ApproverDelegation', 'View:ApproverDelegation', 'Create:ApproverDelegation', 'Update:ApproverDelegation' => ProcurementPermissions::MANAGE_MASTER_DATA,
            'ViewAny:ApprovalInstanceStep', 'View:ApprovalInstanceStep' => ProcurementPermissions::APPROVE,
            default => null,
        };

        if ($fallback === null) {
            $model = explode(':', $ability, 2)[1] ?? null;
            $fallback = match ($model) {
                'PurchaseRequest' => ProcurementPermissions::VIEW,
                'Quotation' => ProcurementPermissions::UPDATE,
                'PurchaseOrder' => ProcurementPermissions::VIEW,
                'Invoice' => ProcurementPermissions::VIEW,
                'Distribution' => ProcurementPermissions::VIEW,
                'Vendor' => ProcurementPermissions::VIEW,
                'Branch' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'Office' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'Department' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'CostCenter' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'Budget' => ProcurementPermissions::MANAGE_FINANCE,
                'Pilgrim' => ProcurementPermissions::VIEW,
                'UmrahBatch' => ProcurementPermissions::VIEW,
                'SampleShipment' => ProcurementPermissions::VIEW,
                'UserAssignment' => ProcurementPermissions::VIEW,
                'Workflow' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'WorkflowStep' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'WorkflowVersion' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'ProcurementItem' => ProcurementPermissions::VIEW,
                'ProcurementCategory' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'ProcurementUnit' => ProcurementPermissions::VIEW,
                'ProcurementVariant' => ProcurementPermissions::VIEW,
                'ProcurementField' => ProcurementPermissions::VIEW,
                'ApproverMapping' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'ApproverDelegation' => ProcurementPermissions::MANAGE_MASTER_DATA,
                'ApprovalInstanceStep' => ProcurementPermissions::APPROVE,
                'VendorItem' => ProcurementPermissions::VIEW,
                default => null,
            };
        }

        if ($fallback === null) {
            return false;
        }

        if (parent::can($fallback, $arguments)) {
            return true;
        }

        return $this->assignments()->currentlyActive()->get()->contains(fn (UserAssignment $assignment): bool => $assignment->allows($fallback));
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class)->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    public function approverMappings(): HasMany
    {
        return $this->hasMany(ApproverMapping::class);
    }

    public function delegationsGiven(): HasMany
    {
        return $this->hasMany(ApproverDelegation::class, 'delegator_id');
    }

    public function delegationsReceived(): HasMany
    {
        return $this->hasMany(ApproverDelegation::class, 'delegate_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->isDirty('keycloak_sub') && $user->getOriginal('keycloak_sub') !== null) {
                throw new \LogicException('The Keycloak subject is immutable.');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_token_sync_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
