<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Department, Role, User};

class GetADepartmentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Department not found
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
        $response = $this->getJson(route('departments.show', [
            'department' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Department was successfully fetched
     */
    public function test_department_is_successfully_fetched()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $department = Department::factory()
                                ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('departments.show', [
            'department' => $department
        ]));

        $response->assertOk();
    }

}
