<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalInbox\Pages\ListApprovalInbox;
use App\Filament\Resources\ApprovalInbox\Pages\ViewApprovalInbox;
use App\Filament\Resources\PurchaseRequests\Schemas\PurchaseRequestInfolist;
use App\Filament\Resources\PurchaseRequests\Tables\PurchaseRequestsTable;
use App\Models\ApprovalInstanceStep;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\ApprovalTaskLifecycleService;
use App\Services\AuthorizationService;
use App\Services\FeatureModuleService;
use App\Services\FeatureRegistry;
use App\Services\ProcurementReviewService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class ApprovalInboxResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $navigationLabel = 'Approvals';

    protected static string|\UnitEnum|null $navigationGroup = 'Approvals';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'approval';

    protected static ?string $pluralModelLabel = 'approvals';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseRequestInfolist::configure($schema);
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $count = self::activeQuery($user)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return PurchaseRequestsTable::configure($table, filterByRequester: false)
            ->recordUrl(fn (PurchaseRequest $record): string => self::getUrl('view', ['record' => $record]));
    }

    /** @return Builder<PurchaseRequest> */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return self::baseQuery()->whereKey(0);
        }

        $activeIds = self::activeQuery($user)->reorder()->select('purchase_requests.id');
        $archiveIds = self::archiveQuery($user)->reorder()->select('purchase_requests.id');

        return self::baseQuery()
            ->where(function (Builder $query) use ($activeIds, $archiveIds): void {
                $query->whereIn('purchase_requests.id', $activeIds)
                    ->orWhereIn('purchase_requests.id', $archiveIds);
            })
            ->orderByDesc('updated_at');
    }

    /** @return Builder<PurchaseRequest> */
    public static function activeQuery(User $user): Builder
    {
        $reviews = app(ProcurementReviewService::class)->reviewQueue($user)
            ->reorder()
            ->select('purchase_requests.id');
        $taskIds = app(ApprovalTaskLifecycleService::class)->pendingTasks($user)
            ->reorder()
            ->select('approval_instance_steps.id');

        return self::baseQuery()
            ->where(function (Builder $query) use ($reviews, $taskIds): void {
                $query->whereIn('purchase_requests.id', $reviews)
                    ->orWhereHas('approvalInstances.steps', fn (Builder $steps): Builder => $steps->whereIn('approval_instance_steps.id', $taskIds));
            })
            ->orderByDesc('updated_at');
    }

    /** @return Builder<PurchaseRequest> */
    public static function archiveQuery(User $user): Builder
    {
        return self::baseQuery()
            ->whereNotIn('purchase_requests.status', [
                PurchaseRequest::STATUS_REJECTED,
                PurchaseRequest::STATUS_RETURNED,
                PurchaseRequest::STATUS_CANCELLED,
            ])
            ->whereHas('approvalInstances.steps.histories', fn (Builder $histories): Builder => $histories
                ->where('approval_histories.user_id', $user->getKey())
                ->where('approval_histories.action', 'approve'))
            ->orderByDesc('updated_at');
    }

    /** @param Builder<PurchaseRequest> $query */
    public static function scopeToActive(Builder $query, User $user): Builder
    {
        return $query->whereIn('purchase_requests.id', self::activeQuery($user)->reorder()->select('purchase_requests.id'));
    }

    /** @param Builder<PurchaseRequest> $query */
    public static function scopeToArchive(Builder $query, User $user): Builder
    {
        return $query->whereIn('purchase_requests.id', self::archiveQuery($user)->reorder()->select('purchase_requests.id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalInbox::route('/'),
            'view' => ViewApprovalInbox::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => self::canViewAnyFor($user));
    }

    public static function canViewAny(): bool
    {
        return app(FeatureModuleService::class)->allowsResource(self::class, fn (User $user): bool => self::canViewAnyFor($user));
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof PurchaseRequest) {
            return false;
        }

        return Gate::forUser(auth()->user())->allows('view', $record)
            || self::actionableTask($record) instanceof ApprovalInstanceStep
            || self::wasApprovedBy(auth()->user(), $record);
    }

    public static function actionableTask(PurchaseRequest $record): ?ApprovalInstanceStep
    {
        return app(ApprovalTaskLifecycleService::class)
            ->pendingTasks(auth()->user())
            ->whereHas('approvalInstance', fn (Builder $query): Builder => $query->where('purchase_request_id', $record->getKey()))
            ->get()
            ->first(fn (ApprovalInstanceStep $step): bool => Gate::forUser(auth()->user())->allows('view', $step));
    }

    private static function canViewAnyFor(User $user): bool
    {
        return (app(AuthorizationService::class)->allows($user, 'ViewAny:ApprovalInstanceStep')
                || app(AuthorizationService::class)->allows($user, 'ViewAny:PurchaseRequest'))
            && (app(FeatureModuleService::class)->featureIsAvailable(FeatureRegistry::FEATURE_APPROVAL_INBOX, $user)
                || app(FeatureModuleService::class)->featureIsAvailable(FeatureRegistry::FEATURE_PROCUREMENT_REVIEWS, $user));
    }

    private static function wasApprovedBy(?User $user, PurchaseRequest $record): bool
    {
        return $user instanceof User
            && self::archiveQuery($user)->whereKey($record->getKey())->exists();
    }

    /** @return Builder<PurchaseRequest> */
    private static function baseQuery(): Builder
    {
        return PurchaseRequest::query()
            ->withoutGlobalScopes()
            ->with(['requester', 'office', 'branch', 'department', 'category', 'items', 'fieldValues', 'attachments']);
    }
}
