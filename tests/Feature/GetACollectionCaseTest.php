<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{CollectionCase, Role, User};

class GetACollectionCaseTest extends TestCase
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
        $response = $this->getJson(route('collection-cases.show', [
            'collectionCase' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Collection case was successfully fetched
     */
    public function test_collection_case_is_successfully_fetched()
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
        $response = $this->getJson(route('collection-cases.show', [
            'collectionCase' => $collectionCase
        ]));

        $response->assertOk();
    }

}
