<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use App\Models\{User, Role};
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_authenticating_to_the_application()
    {
        $response = $this->postJson(route('login'), [
            'username' => null
        ]);

        $response->assertInvalid(['username']);
        $response->assertUnprocessable();
    }

    /**
     * Failed to authenticate to the application
     */
    public function test_authentication_to_the_application_fails()
    {
        $response = $this->postJson(route('login'), [
            'username' => 'unknown-user',
            'password' => 'random-password'
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Login successful
     */
    public function test_login_was_successful()
    {
        $username = 'test@test.com';
        $password = 'password';
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create([
                        'email' => $username,
                        'password' => Hash::make($password)
                    ]);

        $response = $this->postJson(route('login'), [
            'username' => $username,
            'password' => $password
        ]);

        $response->assertValid();
        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

}
