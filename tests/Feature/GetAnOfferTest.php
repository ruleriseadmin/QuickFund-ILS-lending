<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Offer, Role, User};

class GetAnOfferTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Offer not found
     */
    public function test_offer_is_not_found()
    {
        $offer = Offer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('offers.show', [
            'offer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Offer was successfully fetched
     */
    public function test_offer_is_successfully_fetched()
    {
        $offer = Offer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('offers.show', [
            'offer' => $offer
        ]));

        $response->assertOk();
    }
}
