<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApproverDelegations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ApproverDelegationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('delegator_id')
                ->label('Original approver')
                ->relationship('delegator', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('delegate_id')
                ->label('Delegate')
                ->relationship('delegate', 'name')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('valid_from')->label('Valid from')->required()->default(now()->toDateString()),
            DatePicker::make('valid_until')->label('Valid until')->required()->afterOrEqual('valid_from'),
            Toggle::make('is_active')->label('Active')->default(true),
            Textarea::make('reason')->label('Reason')->required()->minLength(3)->columnSpanFull(),
        ])->columns(2);
    }
}
