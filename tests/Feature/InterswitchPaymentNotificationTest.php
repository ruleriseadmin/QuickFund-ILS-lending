<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Http, Bus};
use App\Exceptions\Interswitch\{
    CustomException as InterswitchCustomException,
    ValidationException as InterswitchValidationException,
    InternalCustomerNotFoundException,
    UncollectedLoanException,
    UnknownOfferException
};
use App\Jobs\Interswitch\SendSms;
use App\Models\{CollectionCase, Customer, Loan, LoanOffer, Transaction, User};
use Tests\TestCase;

class InterswitchPaymentNotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_receiving_payment_notification()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchValidationException::class);
        
        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('transactions.notification'));

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
        $response = $this->postJson(route('transactions.notification'), [
            'customerId' => $this->faker->phoneNumber(),
            'loanId' => 'non-existent-id',
            'paymentRef' => $this->faker->word(),
            'amount' => $this->faker->randomNumber(4)
        ]);
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
        $response = $this->postJson(route('transactions.notification'), [
            'customerId' => $customer->phone_number,
            'loanId' => 'non-existent-id',
            'paymentRef' => $this->faker->word(),
            'amount' => $this->faker->randomNumber(4)
        ]);
    }

    /**
     * Uncollected loan on loan offer
     */
    public function test_loan_has_not_been_collected_on_loan_offer()
    {
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $this->withoutExceptionHandling();
        $this->expectException(UncollectedLoanException::class);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('transactions.notification'), [
            'customerId' => $customer->phone_number,
            'loanId' => $loanOffer->id,
            'paymentRef' => $this->faker->word(),
            'amount' => $this->faker->randomNumber(4)
        ]);
    }

    /**
     * Interswitch authentication failed
     */
    public function test_interswitch_authentication_failed()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchCustomException::class);
        $amount = 900000;
        $amountRemaining = 1000000;
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'amount_remaining' => $amountRemaining
                    ]);
        $paymentReference = 'FBN|WEB|ILS|20170503121143|8903';
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'description' => 'Invalid credentials'
            ], 401, [
                'Content-Type' => 'application/json'
            ]),
        ]);
        
        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('transactions.notification'), [
            'customerId' => $customer->phone_number,
            'loanId' => $loanOffer->id,
            'paymentRef' => $paymentReference,
            'amount' => $amount
        ]);

        $response->assertSeeText('Interswitch authentication failed');
    }

    /**
     * Payment successful but customer still owes
     */
    public function test_payment_successful_but_customer_still_owes()
    {
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);
        $amount = 900000;
        $penalty = 2000;
        $amountRemaining = 1000000;
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'penalty' => $penalty,
                        'penalty_remaining' => $penalty,
                        'amount_remaining' => $amountRemaining
                    ]);
        $paymentReference = 'FBN|WEB|ILS|20170503121143|8903';

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('transactions.notification'), [
            'customerId' => $customer->phone_number,
            'loanId' => $loanOffer->id,
            'paymentRef' => $paymentReference,
            'amount' => $amount
        ]);
        $loan->refresh();

        $this->assertDatabaseHas('transactions', [
            'amount' => $amount,
            'interswitch_payment_reference' => $paymentReference,
            'type' => Transaction::PAYMENT
        ]);
        $this->assertSame(abs(($amountRemaining + $penalty) - $amount), $loan->amount_remaining + $loan->penalty_remaining);
        Bus::assertDispatched(SendSms::class);
        $response->assertSee([
            'loanStatus' => LoanOffer::OPEN
        ]);
    }

    /**
     * Complete loan payment successful but updating status failed
     */
    public function test_complete_loan_payment_was_successful_but_updating_status_failed()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchCustomException::class);
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'loans/*/update' => Http::response([
                'responseCode' => '104',
                'responseMessage' => 'Loan not found'
            ], 400, [
                'Content-Type' => 'application/json'
            ]),
        ]);
        $amountRemaining = 1000000;
        $penalty = 2000;
        $amount = $amountRemaining + $penalty;
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'penalty' => $penalty,
                        'penalty_remaining' => $penalty,
                        'amount_remaining' => $amountRemaining
                    ]);
        $paymentReference = 'FBN|WEB|ILS|20170503121143|8903';
        
        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('transactions.notification'), [
            'customerId' => $customer->phone_number,
            'loanId' => $loanOffer->id,
            'paymentRef' => $paymentReference,
            'amount' => $amount
        ]);
        $loanOffer->refresh();
        $loan->refresh();

        $this->assertDatabaseHas('transactions', [
            'amount' => $amount,
            'interswitch_payment_reference' => $paymentReference,
            'type' => Transaction::PAYMENT
        ]);
        $this->assertSame(0, $loan->amount_remaining);
        $this->assertSame(0, $loan->penalty_remaining);
        $this->assertSame(LoanOffer::OPEN, $loanOffer->status);
        $response->assertSeeText('Failed to update loan status');
    }

    /**
     * Complete loan payment successful but updating status failed
     */
    public function test_complete_loan_payment_was_successful_and_everything_worked_fine()
    {
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'loans/*/update' => Http::response([
                'responseCode' => '00',
                'responseMessage' => 'Successful.'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);
        $amountRemaining = 1000000;
        $penalty = 2000;
        $amount = $amountRemaining + $penalty;
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'penalty' => $penalty,
                        'penalty_remaining' => $penalty,
                        'amount_remaining' => $amountRemaining
                    ]);
        $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();
        $paymentReference = 'FBN|WEB|ILS|20170503121143|8903';
        
        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('transactions.notification'), [
            'customerId' => $customer->phone_number,
            'loanId' => $loanOffer->id,
            'paymentRef' => $paymentReference,
            'amount' => $amount
        ]);
        $loanOffer->refresh();
        $loan->refresh();
        $collectionCase->refresh();

        $this->assertDatabaseHas('transactions', [
            'amount' => $amount,
            'interswitch_payment_reference' => $paymentReference,
            'type' => Transaction::PAYMENT
        ]);
        $this->assertSame(0, $loan->amount_remaining);
        $this->assertSame(0, $loan->penalty_remaining);
        $this->assertSame(LoanOffer::CLOSED, $loanOffer->status);
        $this->assertSame(CollectionCase::CLOSED, $collectionCase->status);
        Bus::assertDispatched(SendSms::class);
        $response->assertSee([
            'loanStatus' => LoanOffer::CLOSED
        ]);
    }

}
