<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserModuleTest extends TestCase
{
    /**
     * Test getting user modules
     *
     * @return void
     */
    public function test_getUserModules()
    {
        $response = $this->postJson('/api/sessions', []);
        $response->assertStatus(401)->assertJson(['errors' => ['Invalid email or password'], 'status' => 401]);
    }
}
