<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;

class MakeFilamentUser extends Command
{
    protected $signature = 'make:filament-user {--name= : Name of the user} {--email= : A valid and unique email address} {--password= : The password for the user (min. 8 characters)}';

    protected $description = 'Create a new Filament user (local environments only)';

    /**
     * The underlying vendor command that performs the actual user creation.
     */
    protected string $vendorCommand = 'filament:user';

    public function handle(Application $app): int
    {
        if (! App::environment(['local', 'testing'])) {
            $this->error('make:filament-user is only available in local or testing environments.');

            return self::FAILURE;
        }

        $options = collect($this->options())
            ->filter(fn ($value, $key) => in_array($key, ['name', 'email', 'password'], true) && $value !== null)
            ->mapWithKeys(fn ($value, $key) => ["--{$key}" => $value])
            ->all();

        return $this->call($this->vendorCommand, $options);
    }
}
