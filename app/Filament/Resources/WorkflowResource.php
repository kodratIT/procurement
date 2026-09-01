<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowResource\RelationManagers\BindingsRelationManager;
use App\Filament\Resources\WorkflowResource\RelationManagers\VersionsRelationManager;
use App\Models\Workflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Workflow';

    protected static ?string $modelLabel = 'workflow';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            Textarea::make('description')->columnSpanFull(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('versions_count')->counts('versions')->label('Versi'),
            TextColumn::make('active_version')->state(fn (Workflow $record): string => $record->activeVersion()?->version_number ? 'v'.$record->activeVersion()->version_number : '-')->label('Versi aktif'),
            IconColumn::make('is_active')->boolean()->label('Aktif'),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
            Action::make('activate')->label('Aktifkan')->authorize(fn (Workflow $record): bool => Gate::allows('activate', $record))->visible(fn (Workflow $record): bool => ! $record->is_active)->action(fn (Workflow $record): bool => $record->activate()),
            Action::make('retire')->label('Pensiunkan')->requiresConfirmation()->authorize(fn (Workflow $record): bool => Gate::allows('retire', $record))->visible(fn (Workflow $record): bool => $record->is_active)->action(fn (Workflow $record): bool => $record->retire()),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
            BindingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageWorkflows::route('/')];
    }
}
