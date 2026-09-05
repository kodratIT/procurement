<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Http\Controllers\Auth\KeycloakController;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\RedirectResponse;

final class Login extends BaseLogin
{
    public function mount(): void
    {
        $user = Filament::auth()->user();

        if ($user !== null && ! $this->isUserAllowedToAccessPanel($user)) {
            $this->form->fill();

            return;
        }

        parent::mount();
    }

    public function getFormContentComponent(): Component
    {
        if (Filament::auth()->check()) {
            return Actions::make([
                Action::make('logout')
                    ->label('Keluar lalu masuk dengan akun lain')
                    ->color('gray')
                    ->action('logout'),
            ])->fullWidth();
        }

        return Actions::make([
            Action::make('keycloak')
                ->label('Masuk dengan Keycloak')
                ->url(route('keycloak.redirect'))
                ->button(),
        ])->fullWidth();
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (Filament::auth()->check()) {
            return 'Akun ini belum mendapat akses ke aplikasi. Hubungi administrator.';
        }

        return 'Gunakan akun Keycloak untuk melanjutkan.';
    }

    public function logout(): RedirectResponse
    {
        return app(KeycloakController::class)->logout(request());
    }
}
