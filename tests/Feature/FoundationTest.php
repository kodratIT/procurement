<?php

namespace Tests\Feature;

use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_application_health_check_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_primary_application_routes_are_registered(): void
    {
        $this->assertNotNull(route('keycloak.redirect'));
        $this->assertNotNull(route('keycloak.callback'));
        $this->assertNotNull(route('logout'));
    }

    public function test_filament_admin_panel_boots(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}
