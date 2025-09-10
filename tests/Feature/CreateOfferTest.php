<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{Offer, Role, User};
use Tests\TestCase;

class CreateOfferTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_creating_offer()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('offers.store'), [
            'amount' => null
        ]);

        $response->assertInvalid(['amount']);
        $response->assertUnprocessable();
    }

    /**
     * Offer already exist
     */
    public function test_offer_already_exist_error_occurs_while_creating_offer()
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
        $response = $this->postJson(route('offers.store'), [
            'amount' => $offer->amount,
            'tenure' => $offer->tenure,
            'cycles' => $this->faker->numberBetween(1, 5)
        ]);

        $response->assertStatus(400);
    }

    /**
     * Offer is successfully created
     */
    public function test_offer_is_successfully_created()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('offers.store'), [
            'amount' => $this->faker->numberBetween(config('quickfund.minimum_loan_amount'), config('quickfund.maximum_loan_amount')),
            'tenure' => $this->faker->randomElement(config('quickfund.loan_tenures')),
            'cycles' => $this->faker->numberBetween(1, 5)
        ]);

        $response->assertValid();
        $response->assertCreated();
        $this->assertDatabaseCount('offers', 1);
    }

}
