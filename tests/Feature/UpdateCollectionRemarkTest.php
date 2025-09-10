<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{CollectionRemark, Role, User};
use Tests\TestCase;

class UpdateCollectionRemarkTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Collection remark not found
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
        $collectionRemark = CollectionRemark::factory()
                                            ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('collection-remarks.update', [
            'collectionRemark' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_collection_remark()
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
        $response = $this->putJson(route('collection-remarks.update', [
            'collectionRemark' => $collectionRemark
        ]), [
            'name' => null
        ]);

        $response->assertInvalid(['name']);
        $response->assertUnprocessable();
    }

    /**
     * Collection remark is successfully updated
     */
    public function test_collection_remark_is_successfully_updated()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $oldName = 'Promised to pay';
        $newName = 'Already paid';
        $collectionRemark = CollectionRemark::factory()
                                            ->create([
                                                'name' => $oldName
                                            ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('collection-remarks.update', [
            'collectionRemark' => $collectionRemark
        ]), [
            'name' => $newName
        ]);
        $collectionRemark->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertSame($newName, $collectionRemark->name);
    }

}
