<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_use_the_token(): void
    {
        $user = User::factory()->planner()->create(['email' => 'planner@example.com']);

        $token = $this->postJson('/api/login', [
            'email' => 'planner@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'planner')
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'planner@example.com']);

        $this->postJson('/api/login', [
            'email' => 'planner@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_guest_gets_a_json_401(): void
    {
        // No "Accept: application/json" on purpose, the API should still answer with json
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
