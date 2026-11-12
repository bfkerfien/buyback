<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_service_status(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'buyback');
    }

    public function test_homepage_renders_the_appraisal_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Create an appraisal')
            ->assertSee('Sign in with EVE SSO');
    }
}
