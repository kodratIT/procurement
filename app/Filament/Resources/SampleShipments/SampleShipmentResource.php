<?php

declare(strict_types=1);

namespace App\Filament\Resources\SampleShipments;

use App\Filament\Resources\SampleShipments\Pages\CreateSampleShipment;
use App\Filament\Resources\SampleShipments\Pages\EditSampleShipment;
use App\Filament\Resources\SampleShipments\Pages\ListSampleShipments;
use App\Filament\Resources\SampleShipments\Pages\ViewSampleShipment;
use App\Filament\Resources\SampleShipments\Schemas\SampleShipmentForm;
use App\Filament\Resources\SampleShipments\Schemas\SampleShipmentInfolist;
use App\Filament\Resources\SampleShipments\Tables\SampleShipmentsTable;
use App\Models\SampleShipment;
use App\Models\User;
use App\Services\AccessContextService;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class SampleShipmentResource extends Resource
{
    protected static ?string $model = SampleShipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Sample shipments';

    protected static ?string $modelLabel = 'sample shipment';

    protected static ?string $pluralModelLabel = 'sample shipments';

    public static function form(Schema $schema): Schema
    {
        return SampleShipmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SampleShipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SampleShipmentsTable::configure($table);
    }

    /** @return Builder<SampleShipment> */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereKey(0);
        }

        $query = app(MultiOfficeAuthorization::class)->scopeForUser(
            parent::getEloquentQuery()->with([
                'purchaseOrder',
                'senderOffice',
                'receiverOffice',
                'sender',
                'receiver',
                'costCenter',
                'items.procurementItem',
                'items.procurementVariant',
                'receipt.attachments',
                'attachments',
            ]),
            $user,
            ProcurementPermissions::VIEW,
        );
        $receiverOfficeIds = app(AccessContextService::class)->allowedAssignments($user)
            ->filter(fn ($assignment): bool => $assignment->allows(ProcurementPermissions::VIEW))
            ->pluck('office_id')
            ->unique()
            ->values()
            ->all();

        return $receiverOfficeIds === []
            ? $query
            : $query->orWhereIn('sample_shipments.receiver_office_id', $receiverOfficeIds);
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(MultiOfficeAuthorization::class)->canView($user);
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListSampleShipments::route('/'),
            'create' => CreateSampleShipment::route('/create'),
            'view' => ViewSampleShipment::route('/{record}'),
            'edit' => EditSampleShipment::route('/{record}/edit'),
        ];
    }
}
