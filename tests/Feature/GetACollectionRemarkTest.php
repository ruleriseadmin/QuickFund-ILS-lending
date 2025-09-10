<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{CollectionRemark, Role, User};

class GetACollectionRemarkTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Department not found
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

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('collection-remarks.show', [
            'collectionRemark' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Collection remark was successfully fetched
     */
    public function test_collection_remark_is_successfully_fetched()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $collectionRemark = CollectionRemark::factory()
                                            ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('collection-remarks.show', [
            'collectionRemark' => $collectionRemark
        ]));

        $response->assertOk();
    }

}
