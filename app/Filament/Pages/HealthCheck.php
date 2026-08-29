<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HealthCheck extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Kesehatan Sistem';

    protected static ?string $title = 'Kesehatan Sistem';

    protected static ?string $slug = 'health';

    protected static ?int $navigationSort = 98;

    protected string $view = 'filament.pages.health-check';
}
