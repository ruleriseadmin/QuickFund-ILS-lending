<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Blacklist, Role, User};

class GetABlacklistTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Blacklist not found
     */
    public function test_blacklist_is_not_found()
    {
        $blacklist = Blacklist::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('blacklists.show', [
            'customerId' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Blacklist was successfully fetched
     */
    public function test_blacklist_is_successfully_fetched()
    {
        $blacklist = Blacklist::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('blacklists.show', [
            'customerId' => $blacklist->phone_number
        ]));

        $response->assertOk();
    }
}
