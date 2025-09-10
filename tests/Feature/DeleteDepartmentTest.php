<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Department, Role, User};

class DeleteDepartmentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Department not found
     */
    public function test_department_is_not_found()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->deleteJson(route('departments.destroy', [
            'department' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Department was successfully deleted
     */
    public function test_department_is_successfully_deleted()
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
        $response = $this->deleteJson(route('departments.destroy', [
            'department' => $department
        ]));

        $response->assertOk();
        $this->assertModelMissing($department);
    }
}
