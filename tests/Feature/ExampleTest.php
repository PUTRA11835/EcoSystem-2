<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guests_are_redirected_from_the_home_page_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
