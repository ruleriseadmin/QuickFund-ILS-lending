<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{LoanOffer, Role, User};

class GetALoanOfferTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Loan offer not found
     */
    public function test_loan_offer_is_not_found()
    {
        $loanOffer = LoanOffer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('loan-offers.show', [
            'loanOffer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Loan offer was successfully fetched
     */
    public function test_loan_offer_is_successfully_fetched()
    {
        $loanOffer = LoanOffer::factory()
                            ->forCustomer()
                            ->hasLoan()
                            ->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('loan-offers.show', [
            'loanOffer' => $loanOffer
        ]));

        $response->assertOk();
    }
}
