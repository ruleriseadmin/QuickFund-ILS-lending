<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Role};
use Tests\TestCase;

class GetCollectionCasesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Collection cases were successfully fetched
     */
    public function test_collection_cases_is_successfully_fetched()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('collection-cases.index'));

        $response->assertOk();
    }

}
