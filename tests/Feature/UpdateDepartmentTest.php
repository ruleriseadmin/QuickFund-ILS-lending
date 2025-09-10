<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{Department, Role, User};
use Tests\TestCase;

class UpdateDepartmentTest extends TestCase
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
        $department = Department::factory()
                                ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('departments.update', [
            'department' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_department()
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
        $response = $this->putJson(route('departments.update', [
            'department' => $department
        ]), [
            'name' => null
        ]);

        $response->assertInvalid(['name']);
        $response->assertUnprocessable();
    }

    /**
     * Department is successfully updated
     */
    public function test_department_is_successfully_updated()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $oldName = 'Collections';
        $newName = 'Operations';
        $department = Department::factory()
                                ->create([
                                    'name' => $oldName
                                ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('departments.update', [
            'department' => $department
        ]), [
            'name' => $newName
        ]);
        $department->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertSame($newName, $department->name);
    }

}
