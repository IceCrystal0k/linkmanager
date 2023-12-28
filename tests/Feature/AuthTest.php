<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /**
     * Test login without email and password
     *
     * @return void
     */
    public function test_loginNoEmailAndPassword()
    {
        $response = $this->postJson('/api/sessions', []);
        $response->assertStatus(401)->assertJson(['errors' => ['Invalid email or password'], 'status' => 401]);
    }

    /**
     * Test login without email
     *
     * @return void
     */
    public function test_loginNoEmail()
    {
        $response = $this->postJson('/api/sessions', ['password' => 'password']);
        $response->assertStatus(401)->assertJson(['errors' => ['Invalid email or password'], 'status' => 401]);
    }

    /**
     * Test login without password
     *
     * @return void
     */
    public function test_loginNoPassword()
    {
        $response = $this->postJson('/api/sessions', ['password' => 'password']);
        $response->assertStatus(401)->assertJson(['errors' => ['Invalid email or password'], 'status' => 401]);
    }

    /**
     * Test login with wrong email
     *
     * @return void
     */
    public function test_loginWrongEmail()
    {
        $response = $this->postJson('/api/sessions', ['email' => 'some@email.com', 'password' => 'Beyond1234$']);
        $response->assertStatus(401)->assertJson(['errors' => ['Invalid email or password'], 'status' => 401]);
    }

    /**
     * Test login with wrong password
     *
     * @return void
     */
    public function test_loginWrongPassword()
    {
        $response = $this->postJson('/api/sessions', ['email' => 'frozen0k@gmail.com', 'password' => 'Beyond1234$a']);
        $response->assertStatus(401)->assertJson(['errors' => ['Invalid email or password'], 'status' => 401]);
    }

    /**
     * Test login with correct email and password
     *
     * @return void
     */
    public function test_loginCorrect()
    {
        $response = $this->postJson('/api/sessions', ['email' => 'frozen0k@gmail.com', 'password' => 'Beyond1234$']);
        $response->assertStatus(201)->assertJsonStructure(['status', 'data' => [ 'token', 'first_name', 'last_name']]);
    }
}
