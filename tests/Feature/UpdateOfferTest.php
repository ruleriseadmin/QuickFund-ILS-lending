<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{Offer, User, Role};
use Tests\TestCase;

class UpdateOfferTest extends TestCase
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
        $response = $this->putJson(route('offers.update', [
            'offer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_offer()
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
        $response = $this->putJson(route('offers.update', [
            'offer' => $offer->id
        ]), [
            'amount' => null
        ]);

        $response->assertInvalid(['amount']);
        $response->assertUnprocessable();
    }

    /**
     * Offer already exist
     */
    public function test_offer_already_exist_error_occurs_while_updating_offer()
    {
        $offer1 = Offer::factory()->create([
            'amount' => $this->faker->numberBetween(config('quickfund.minimum_loan_amount'), config('quickfund.maximum_loan_amount')),
            'tenure' => $this->faker->randomElement(config('quickfund.loan_tenures'))
        ]);
        $offer2 = Offer::factory()->create([
            'amount' => $this->faker->numberBetween(config('quickfund.minimum_loan_amount'), config('quickfund.maximum_loan_amount')),
            'tenure' => $this->faker->randomElement(config('quickfund.loan_tenures'))
        ]);
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('offers.update', [
            'offer' => $offer1->id
        ]), [
            'amount' => $offer2->amount,
            'tenure' => $offer2->tenure,
            'cycles' => $this->faker->numberBetween(1, 5)
        ]);

        $response->assertStatus(400);
    }

    /**
     * Offer is successfully updated
     */
    public function test_offer_is_successfully_updated()
    {
        $offer = Offer::factory()->create([
            'amount' => 10000
        ]);
        $oldAmount = $offer->amount;
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('offers.update', [
            'offer' => $offer->id
        ]), [
            'amount' => $this->faker->numberBetween(config('quickfund.minimum_loan_amount'), config('quickfund.maximum_loan_amount')),
            'tenure' => $this->faker->randomElement(config('quickfund.loan_tenures')),
            'cycles' => $this->faker->numberBetween(1, 5)
        ]);
        $offer->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertNotEquals($oldAmount, $offer->amount);
    }
}
