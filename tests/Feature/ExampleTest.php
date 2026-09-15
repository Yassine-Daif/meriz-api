<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_route_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_unknown_route_returns_json_404(): void
    {
        $this->get('/api/inconnue')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
