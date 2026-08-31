<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations;

use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\EditQuotation;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\Schemas\QuotationForm;
use App\Filament\Resources\Quotations\Tables\QuotationsTable;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\User;
use App\Support\ProcurementPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?string $navigationLabel = 'Quotation';

    protected static ?string $modelLabel = 'quotation';

    protected static ?string $pluralModelLabel = 'quotation';

    public static function form(Schema $schema): Schema
    {
        return QuotationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotationsTable::configure($table);
    }

    /** @return Builder<Quotation> */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereKey(0);
        }

        $requestIds = PurchaseRequest::query()
            ->acrossOffices(ProcurementPermissions::UPDATE)
            ->select('id');

        return parent::getEloquentQuery()
            ->whereIn('purchase_request_id', $requestIds)
            ->with(['purchaseRequest', 'vendor', 'items', 'attachments']);
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->is_active
            && $user->assignments()->currentlyActive()->get()->contains(
                fn ($assignment): bool => $assignment->allows(ProcurementPermissions::UPDATE),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotations::route('/'),
            'create' => CreateQuotation::route('/create'),
            'edit' => EditQuotation::route('/{record}/edit'),
        ];
    }
}
