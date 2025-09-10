<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Tests\TestCase;

class BlacklistCountTest extends TestCase
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
        $response = $this->getJson(route('blacklists.count'));

        $response->assertOk();
    }

}
