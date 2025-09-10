<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Exceptions\CustomException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{LoanOffer, Loan, Role, User};

class GetLoanOfferTransactionsTest extends TestCase
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
        $response = $this->getJson(route('loan-offers.transactions', [
            'loanOffer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Uncollected loan on loan offer
     */
    public function test_loan_has_not_been_collected_on_loan_offer()
    {
        $this->withoutExceptionHandling();
        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(__('interswitch.uncollected_loan'));
        $loanOffer = LoanOffer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('loan-offers.transactions', [
            'loanOffer' => $loanOffer
        ]));
    }

    /**
     * Transactions on loan offer are successfully fetched
     */
    public function test_transactions_on_loan_offer_is_successfully_fetched()
    {
        $loanOffer = LoanOffer::factory()
                            ->has(Loan::factory())
                            ->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        
        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('loan-offers.transactions', [
            'loanOffer' => $loanOffer
        ]));

        $response->assertOk();
    }

}
