<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The application home redirects to the Filament admin dashboard.
     */
    public function test_the_application_home_redirects_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect();
    }
}
