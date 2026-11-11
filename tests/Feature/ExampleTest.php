<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        config(['services.super_admin_setup_key' => str_repeat('a', 32)]);

        $this->get('/')->assertRedirect(route('super-admin-setup.create'));
    }
}
