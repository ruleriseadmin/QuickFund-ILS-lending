<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Offer, Role, User};

class DeleteOfferTest extends TestCase
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
        $response = $this->deleteJson(route('offers.destroy', [
            'offer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Offer was successfully deleted
     */
    public function test_offer_is_successfully_deleted()
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
        $response = $this->deleteJson(route('offers.destroy', [
            'offer' => $offer->id
        ]));

        $response->assertOk();
        $this->assertModelMissing($offer);
    }

}
