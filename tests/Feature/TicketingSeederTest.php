<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketing_seed_data_is_repeatable(): void
    {
        $this->seed();

        $this->assertDatabaseCount('departments', 5);
        $this->assertDatabaseCount('categories', 7);
        $this->assertDatabaseHas('departments', ['name' => 'College of Engineering']);
        $this->assertDatabaseHas('categories', [
            'name' => 'Bluebook',
            'description' => 'Online learning platform',
        ]);

        $this->seed();

        $this->assertDatabaseCount('departments', 5);
        $this->assertDatabaseCount('categories', 7);
    }
}
