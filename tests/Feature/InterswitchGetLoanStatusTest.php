<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\{Loan, Customer, User, LoanOffer};
use App\Exceptions\Interswitch\{
    ValidationException as InterswitchValidationException,
    InternalCustomerNotFoundException,
    UncollectedLoanException,
    UnknownOfferException
};
use Tests\TestCase;

class InterswitchGetLoanStatusTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_getting_loan_status()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchValidationException::class);
        
        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('loans.status', [
            'loanOfferId' => 'non-existent-id'
        ]));

        $response->assertUnprocessable();
    }

    /**
     * Customer not found
     */
    public function test_customer_is_not_found()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InternalCustomerNotFoundException::class);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('loans.status', [
            'loanOfferId' => 'non-existent-id',
            'customerId' => $this->faker->phoneNumber(),
            'channelCode' => $this->faker->word()
        ]));
    }

    /**
     * Loan offer not found
     */
    public function test_loan_offer_is_not_found()
    {
        $this->withoutExceptionHandling();
        $this->expectException(UnknownOfferException::class);
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('loans.status', [
            'loanOfferId' => 'non-existent-id',
            'customerId' => $customer->phone_number,
            'channelCode' => $this->faker->word()
        ]));
    }

    /**
     * Uncollected loan on loan offer
     */
    public function test_loan_has_not_been_collected_on_loan_offer()
    {
        $this->withoutExceptionHandling();
        $this->expectException(UncollectedLoanException::class);
        $customer = Customer::factory()
                            ->hasLoanOffers()
                            ->create();
        $customer->load(['loanOffers']);
        $loanOffer = $customer->loanOffers->first();

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('loans.status', [
            'loanOfferId' => $loanOffer->id,
            'customerId' => $customer->phone_number,
            'channelCode' => $this->faker->word()
        ]));
    }

    /**
     * Loan status successfully fetched
     */
    public function test_loan_status_was_successfully_fetched()
    {
        $this->withoutExceptionHandling();
        $customer = Customer::factory()
                            ->hasLoanOffers()
                            ->create();
        $customer->load(['loanOffers']);
        $loanOffer = $customer->loanOffers->first();
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('loans.status', [
            'loanOfferId' => $loanOffer->id,
            'customerId' => $customer->phone_number,
            'channelCode' => $this->faker->word()
        ]));

        $response->assertOk();
    }
}
