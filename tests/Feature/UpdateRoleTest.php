<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{Role, User};
use Tests\TestCase;

class UpdateRoleTest extends TestCase
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
        $role2 = Role::factory()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('roles.update', [
            'role' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_role()
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
        $response = $this->putJson(route('roles.update', [
            'role' => $role2
        ]), [
            'name' => null
        ]);

        $response->assertInvalid(['name']);
        $response->assertUnprocessable();
    }

    /**
     * Role is successfully updated
     */
    public function test_role_is_successfully_updated()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $oldName = 'Collectors';
        $newName = 'Head of Operations';
        $role2 = Role::factory()->create([
            'name' => $oldName
        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('roles.update', [
            'role' => $role2
        ]), [
            'name' => $newName,
            'permissions' => config('quickfund.available_permissions'),
        ]);
        $role2->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertSame($newName, $role2->name);
    }

}
