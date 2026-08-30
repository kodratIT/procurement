<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ValidateEnvironment extends Command
{
    protected $signature = 'app:validate-environment';

    protected $description = 'Validate required production environment configuration without printing secrets';

    public function handle(): int
    {
        $required = [
            'APP_KEY' => config('app.key'),
            'KEYCLOAK_BASE_URL' => config('keycloak.base_url'),
            'KEYCLOAK_REALM' => config('keycloak.realm'),
            'KEYCLOAK_CLIENT_ID' => config('keycloak.client_id'),
            'KEYCLOAK_CLIENT_SECRET' => config('keycloak.client_secret'),
            'KEYCLOAK_REDIRECT_URI' => config('keycloak.redirect_uri'),
            'KEYCLOAK_POST_LOGOUT_REDIRECT_URI' => config('keycloak.post_logout_redirect_uri'),
        ];
        $missing = array_keys(array_filter($required, fn (mixed $value): bool => blank($value)));
        if ($missing !== []) {
            $this->error('Missing required environment variables: '.implode(', ', $missing));

            return self::FAILURE;
        }
        if (! filter_var(config('keycloak.base_url'), FILTER_VALIDATE_URL)) {
            $this->error('KEYCLOAK_BASE_URL must be a valid URL.');

            return self::FAILURE;
        }
        $this->info('Environment configuration is valid.');

        return self::SUCCESS;
    }
}
