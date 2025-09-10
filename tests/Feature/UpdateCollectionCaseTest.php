<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{CollectionCase, Role, User};
use Tests\TestCase;

class UpdateCollectionCaseTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Collection case not found
     */
    public function test_collection_remark_is_not_found()
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
        $response = $this->putJson(route('collection-cases.update', [
            'collectionCase' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_collection_case()
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
        $response = $this->putJson(route('collection-cases.update', [
            'collectionCase' => $collectionCase
        ]), [
            'remark' => null
        ]);

        $response->assertInvalid(['remark']);
        $response->assertUnprocessable();
    }

    /**
     * Collection case is successfully updated
     */
    public function test_collection_case_is_successfully_updated()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $remark = 'He promised to pay';
        $collectionCase = CollectionCase::factory()
                                        ->for($user)
                                        ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('collection-cases.update', [
            'collectionCase' => $collectionCase
        ]), [
            'remark' => $remark
        ]);
        $collectionCase->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertDatabaseHas('collection_case_remarks', [
            'user_id' => $user->id,
            'remark' => $remark
        ]);
    }

}
