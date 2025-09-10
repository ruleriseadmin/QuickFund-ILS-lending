<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\{User, Role};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateRoleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_creating_role()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('roles.store'), [
            'name' => null
        ]);

        $response->assertInvalid(['name']);
        $response->assertUnprocessable();
    }

    /**
     * Role is successfully created
     */
    public function test_role_is_successfully_created()
    {
        $name = 'Collector';
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('roles.store'), [
            'name' => $name,
            'permissions' => config('quickfund.available_permissions'),
        ]);

        $response->assertValid();
        $response->assertCreated();
        $this->assertDatabaseHas('roles', [
            'name' => $name
        ]);
    }
}
