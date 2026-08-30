<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ValidateEnvironment extends Command
{
    protected $signature = 'app:validate-environment';

    protected $description = 'Validate required production environment configuration without printing secrets';

    public function handle(): int
    {
        $required = ['APP_KEY', 'KEYCLOAK_BASE_URL', 'KEYCLOAK_REALM', 'KEYCLOAK_CLIENT_ID', 'KEYCLOAK_CLIENT_SECRET', 'KEYCLOAK_REDIRECT_URI', 'KEYCLOAK_POST_LOGOUT_REDIRECT_URI'];
        $missing = array_values(array_filter($required, fn (string $key) => blank(env($key))));
        if ($missing !== []) {
            $this->error('Missing required environment variables: '.implode(', ', $missing));

            return self::FAILURE;
        }
        if (! filter_var(env('KEYCLOAK_BASE_URL'), FILTER_VALIDATE_URL)) {
            $this->error('KEYCLOAK_BASE_URL must be a valid URL.');

            return self::FAILURE;
        }
        $this->info('Environment configuration is valid.');

        return self::SUCCESS;
    }
}
