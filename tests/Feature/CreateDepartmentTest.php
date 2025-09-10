<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\{User, Role};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateDepartmentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_creating_department()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('departments.store'), [
            'name' => null
        ]);

        $response->assertInvalid(['name']);
        $response->assertUnprocessable();
    }

    /**
     * Department is successfully created
     */
    public function test_department_is_successfully_created()
    {
        $name = 'Collections';
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('departments.store'), [
            'name' => $name
        ]);

        $response->assertValid();
        $response->assertCreated();
        $this->assertDatabaseHas('departments', [
            'name' => $name
        ]);
    }
}
