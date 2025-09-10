<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\{User, Role};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateCollectionRemarkTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_creating_collection_remark()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('collection-remarks.store'), [
            'name' => null
        ]);

        $response->assertInvalid(['name']);
        $response->assertUnprocessable();
    }

    /**
     * Collection remark is successfully created
     */
    public function test_collection_remark_is_successfully_created()
    {
        $name = 'Promised to pay';
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('collection-remarks.store'), [
            'name' => $name
        ]);

        $response->assertValid();
        $response->assertCreated();
        $this->assertDatabaseHas('collection_remarks', [
            'name' => $name
        ]);
    }
}
