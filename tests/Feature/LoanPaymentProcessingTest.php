<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Http, Bus};
use Laravel\Sanctum\Sanctum;
use App\Jobs\Interswitch\SendSms;
use App\Models\{CollectionCase, Customer, Loan, LoanOffer, Role, Transaction, User};
use Tests\TestCase;

class LoanPaymentProcessingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Loan offer not found
     */
    public function test_loan_offer_is_not_found()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.payment-processing', [
            'loanOffer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_receiving_processing_loan_payment()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create();
                            
        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.payment-processing', [
            'loanOffer' => $loanOffer
        ]));

        $response->assertInvalid(['amount']);
        $response->assertUnprocessable();
    }

    /**
     * Loan is either OPEN or OVERDUE
     */
    public function test_loan_offer_status_is_either_open_or_overdue()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create();
        $this->withoutExceptionHandling();
        $this->expectExceptionMessage(__('interswitch.loan_unprocessable', [
            'loan_status' => $loanOffer->status,
            'expected_loan_statuses' => implode(' or ', [
                '"'.LoanOffer::OPEN.'"',
                '"'.LoanOffer::OVERDUE.'"'
            ])
        ]));

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.payment-processing', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $this->faker->numberBetween(1000, 10000)
        ]);
    }

    /**
     * Uncollected loan on loan offer
     */
    public function test_loan_has_not_been_collected_on_loan_offer()
    {
        $this->withoutExceptionHandling();
        $this->expectExceptionMessage(__('interswitch.uncollected_loan'));
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.payment-processing', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $this->faker->numberBetween(1000, 10000)
        ]);
    }

    /**
     * Loan is paid in full
     */
    public function test_loan_is_paid_in_full()
    {
        $this->withoutExceptionHandling();
        $this->expectExceptionMessage(__('interswitch.loan_paid_in_full'));
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'amount_remaining' => 0,
                        'penalty_remaining' => 0
                    ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.payment-processing', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $this->faker->numberBetween(1000, 10000)
        ]);
    }

    /**
     * Payment processing successful and loan is fully repaid
     */
    public function test_loan_processing_was_successful_and_loan_was_fully_repaid()
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
        $amountRemaining = 500000;
        $penaltyRemaining = 20000;
        $amountPaid = $amountRemaining + $penaltyRemaining;
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'amount_remaining' => $amountRemaining,
                        'penalty_remaining' => $penaltyRemaining
                    ]);
        $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.payment-processing', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $amountPaid
        ]);
        $loanOffer->refresh();
        $loan->refresh();
        $collectionCase->refresh();

        $response->assertOk();
        $this->assertSame(0, $loan->amount_remaining);
        $this->assertSame(0, $loan->penalty_remaining);
        $this->assertSame(LoanOffer::CLOSED, $loanOffer->status);
        $this->assertSame(CollectionCase::CLOSED, $collectionCase->status);
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::MANUAL
        ]);
        Bus::assertDispatched(SendSms::class);
    }

    /**
     * Payment processing successful but loan is not fully repaid
     */
    public function test_loan_processing_was_successful_but_loan_is_not_fully_repaid()
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
        $amountRemaining = 500000;
        $penaltyRemaining = 20000;
        $amountPaid = ($amountRemaining + $penaltyRemaining) - 1000;
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'amount_remaining' => $amountRemaining,
                        'penalty_remaining' => $penaltyRemaining
                    ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('loan-offers.payment-processing', [
            'loanOffer' => $loanOffer
        ]), [
            'amount' => $amountPaid
        ]);
        $loanOffer->refresh();
        $loan->refresh();

        $response->assertOk();
        $this->assertNotSame(LoanOffer::CLOSED, $loanOffer->status);
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::MANUAL
        ]);
        Bus::assertDispatched(SendSms::class);
    }
}
