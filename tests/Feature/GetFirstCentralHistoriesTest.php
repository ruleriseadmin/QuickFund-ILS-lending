<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Role};
use Tests\TestCase;

class GetFirstCentralHistoriesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Data was successfully fetched
     */
    public function test_data_is_successfully_fetched()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('first-central-histories.index'));

        $response->assertOk();
    }

}
