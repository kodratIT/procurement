<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Models\Payment;
use App\Services\InvoicePaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')->label('Paid')->date()->sortable(),
                TextColumn::make('amount')->label('Amount')->money('IDR')->sortable(),
                TextColumn::make('reference_number')->label('Reference')->searchable(),
                TextColumn::make('recordedBy.name')->label('Recorded by'),
            ])
            ->headerActions([
                Action::make('recordPayment')
                    ->label('Record payment')
                    ->authorize('recordPayment')
                    ->schema([
                        TextInput::make('amount')->numeric()->minValue(0.01)->required(),
                        DatePicker::make('payment_date')->default(now())->required(),
                        TextInput::make('reference_number')->maxLength(100)->required(),
                        TextInput::make('notes')->maxLength(10000),
                    ])
                    ->action(fn (array $data): Payment => app(InvoicePaymentService::class)->record(
                        $this->getOwnerRecord(),
                        $data,
                        auth()->user(),
                    )),
            ])
            ->recordActions([]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
