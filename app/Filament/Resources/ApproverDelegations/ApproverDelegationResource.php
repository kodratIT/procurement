<?php

namespace App\Filament\Resources\ApproverDelegations;

use App\Filament\Resources\ApproverDelegations\Pages\CreateApproverDelegation;
use App\Filament\Resources\ApproverDelegations\Pages\EditApproverDelegation;
use App\Filament\Resources\ApproverDelegations\Pages\ListApproverDelegations;
use App\Filament\Resources\ApproverDelegations\Schemas\ApproverDelegationForm;
use App\Filament\Resources\ApproverDelegations\Tables\ApproverDelegationsTable;
use App\Models\ApproverDelegation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApproverDelegationResource extends Resource
{
    protected static ?string $model = ApproverDelegation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Approver Delegations';

    protected static ?string $modelLabel = 'approver delegation';

    protected static ?string $pluralModelLabel = 'approver delegations';

    public static function form(Schema $schema): Schema
    {
        return ApproverDelegationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApproverDelegationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApproverDelegations::route('/'),
            'create' => CreateApproverDelegation::route('/create'),
            'edit' => EditApproverDelegation::route('/{record}/edit'),
        ];
    }
}
