<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Role, User};

class GetARoleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Role not found
     */
    public function test_role_is_not_found()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('roles.show', [
            'role' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Role was successfully fetched
     */
    public function test_role_is_successfully_fetched()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $role2 = Role::factory()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('roles.show', [
            'role' => $role2
        ]));

        $response->assertOk();
    }

}
