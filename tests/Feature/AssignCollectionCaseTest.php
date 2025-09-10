<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{CollectionCase, Role, User};
use App\Exceptions\{UserAlreadyAssignedCollectionCaseException, ForbiddenException};
use Tests\TestCase;

class AssignCollectionCaseTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Collection case not found
     */
    public function test_collection_case_is_not_found()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('collection-cases.assign', [
            'collectionCase' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_assigning_collection_case()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $collectionCase = CollectionCase::factory()
                                        ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('collection-cases.assign', [
            'collectionCase' => $collectionCase
        ]), [
            'user_id' => null
        ]);

        $response->assertInvalid(['user_id']);
        $response->assertUnprocessable();
    }

    /**
     * User already assigned to collection cases
     */
    public function test_collection_case_is_already_assigned_to_user()
    {
        $this->withoutExceptionHandling();
        $this->expectException(UserAlreadyAssignedCollectionCaseException::class);
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $collectorRole = Role::factory()
                            ->collector()
                            ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $collector = User::factory()
                        ->for($collectorRole)
                        ->create();
        $collectionCase = CollectionCase::factory()
                                        ->create([
                                            'user_id' => $collector->id
                                        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('collection-cases.assign', [
            'collectionCase' => $collectionCase
        ]), [
            'user_id' => $collector->id
        ]);
    }

    /**
     * User successfully assigned to collection case
     */
    public function test_collection_case_is_assigned_to_user_successfully()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $collectorRole = Role::factory()
                            ->collector()
                            ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $collector1 = User::factory()
                        ->for($collectorRole)
                        ->create();
        $collector2 = User::factory()
                        ->for($collectorRole)
                        ->create();
        $collectionCase = CollectionCase::factory()
                                        ->create([
                                            'user_id' => $collector1->id
                                        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('collection-cases.assign', [
            'collectionCase' => $collectionCase
        ]), [
            'user_id' => $collector2->id
        ]);

        $response->assertOk();
        $response->assertValid();
        $this->assertDatabaseHas('collection_cases', [
            'id' => $collectionCase->id,
            'user_id' => $collector2->id
        ]);
    }
}
