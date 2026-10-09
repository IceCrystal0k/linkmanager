<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_the_application_returns_a_successful_response()
    {
        $loginResponse = $this->postJson('/api/sessions', [
            'email' => 'frozen0k@gmail.com',
            'password' => 'Test1234_',
        ]);

        $loginResponse->assertCreated();
        $token = $loginResponse->json('data.token');

        $response = $this->withToken($token)->getJson('/api/users/modules');

        $response->assertStatus(200);
    }
}
