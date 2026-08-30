<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_home_redirects_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect(route('filament.admin.pages.dashboard'));
    }
}
