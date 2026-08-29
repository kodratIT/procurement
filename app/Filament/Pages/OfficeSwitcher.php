<?php

namespace App\Filament\Pages;

use App\Models\Office;
use App\Services\ActiveOfficeContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class OfficeSwitcher extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Kantor Aktif';

    protected static ?string $title = 'Pilih Kantor Aktif';

    protected static ?int $navigationSort = -1;

    public function getView(): string
    {
        return 'filament.pages.office-switcher';
    }

    public function switchOffice(int $officeId): void
    {
        app(ActiveOfficeContext::class)->set(Office::findOrFail($officeId));

        Notification::make()
            ->title('Kantor aktif diganti.')
            ->success()
            ->send();

        redirect()->to(static::getUrl());
    }

    public function getHeading(): string|Htmlable
    {
        return 'Pilih Kantor Aktif';
    }

    /**
     * @return Collection<int, Office>
     */
    public function getAvailableOffices(): Collection
    {
        return app(ActiveOfficeContext::class)->availableOffices();
    }

    public function getCurrentOffice(): ?Office
    {
        return app(ActiveOfficeContext::class)->current();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::ArrowPath)
                ->action(fn () => redirect()->to(static::getUrl())),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
