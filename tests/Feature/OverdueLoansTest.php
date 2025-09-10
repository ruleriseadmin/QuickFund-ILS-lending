<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use Illuminate\Http\Client\Request;
use App\Models\{LoanOffer, Customer, User, Role};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class OverdueLoansTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Overdue loan offer status were successfully changed and default amount was successfully added
     */
    public function test_overdue_loan_offer_status_were_successfully_changed_and_default_amount_was_added()
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
                'responseMessage' => 'Successful'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->hasLoan([
                                'due_date' => now()->subDays(config('quickfund.days_to_stop_penalty_from_accruing') - 1)->format('Y-m-d')
                            ])
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loanOffer->load(['loan']);
        $loan = $loanOffer->loan;
        $amountRemaining = $loan->amount_remaining;

        $this->artisan('loans:overdue');
        $loanOffer->refresh();
        $loan->refresh();

        Http::assertSentInOrder([
            fn(Request $request) => $request->url() === config('services.interswitch.oauth_token_url'),
            fn(Request $request) => str_starts_with($request->url(), config('services.interswitch.base_url').'loans') && str_ends_with($request->url(), 'update'),
        ]);
        $this->assertSame(LoanOffer::OVERDUE, $loanOffer->status);
        $this->assertSame($amountRemaining, $loan->amount_remaining);
        $this->assertNotSame(0, $loan->penalty);
        $this->assertNotSame(0, $loan->penalty_remaining);
        $this->assertNotSame(0, $loan->defaults);
        $this->assertNotNull($loan->next_due_date);
        Bus::assertDispatched(SendSms::class);
    }

    /**
     * Overdue loan offer status were successfully changed and default amount was not added due to the number of days
     * to add penalty exceeded
     */
    public function test_overdue_loan_offer_status_were_successfully_changed_and_default_amount_was_not_added_due_to_number_of_days_to_add_penalty_exceeded()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $collectorRole = Role::factory()
                            ->collector()
                            ->create();
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'loans/*/update' => Http::response([
                'responseCode' => '00',
                'responseMessage' => 'Successful'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $customer3 = Customer::factory()->create();
        $loanOffer1 = LoanOffer::factory()
                            ->for($customer1)
                            ->hasLoan([
                                'due_date' => now()->subDays(config('quickfund.days_to_stop_penalty_from_accruing') + 1)->format('Y-m-d')
                            ])
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loanOffer2 = LoanOffer::factory()
                            ->for($customer2)
                            ->hasLoan([
                                'due_date' => now()->subDays(config('quickfund.days_to_stop_penalty_from_accruing') + 1)->format('Y-m-d')
                            ])
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loanOffer3 = LoanOffer::factory()
                            ->for($customer3)
                            ->hasLoan([
                                'due_date' => now()->subDays(config('quickfund.days_to_stop_penalty_from_accruing') + 1)->format('Y-m-d')
                            ])
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $user1 = User::factory()
                    ->for($collectorRole)
                    ->create();
        $user2 = User::factory()
                    ->for($collectorRole)
                    ->create();
        $loanOffer1->load(['loan']);
        $loan1 = $loanOffer1->loan;
        $amountRemaining1 = $loan1->amount_remaining;
        $loanOffer2->load(['loan']);
        $loan2 = $loanOffer2->loan;
        $amountRemaining2 = $loan2->amount_remaining;
        $loanOffer3->load(['loan']);
        $loan3 = $loanOffer3->loan;
        $amountRemaining3 = $loan3->amount_remaining;

        $this->artisan('loans:overdue');
        $loanOffer1->refresh();
        $loan1->refresh();
        $loanOffer2->refresh();
        $loan2->refresh();
        $loanOffer3->refresh();
        $loan3->refresh();

        Http::assertSentInOrder([
            fn(Request $request) => $request->url() === config('services.interswitch.oauth_token_url'),
            fn(Request $request) => str_starts_with($request->url(), config('services.interswitch.base_url').'loans') && str_ends_with($request->url(), 'update'),
            fn(Request $request) => str_starts_with($request->url(), config('services.interswitch.base_url').'loans') && str_ends_with($request->url(), 'update'),
            fn(Request $request) => str_starts_with($request->url(), config('services.interswitch.base_url').'loans') && str_ends_with($request->url(), 'update'),
        ]);
        $this->assertSame(LoanOffer::OVERDUE, $loanOffer1->status);
        $this->assertSame($amountRemaining1, $loan1->amount_remaining);
        $this->assertSame(0, $loan1->penalty);
        $this->assertSame(0, $loan1->penalty_remaining);
        $this->assertNotSame(0, $loan1->defaults);
        $this->assertNotNull($loan1->next_due_date);
        $this->assertSame(LoanOffer::OVERDUE, $loanOffer2->status);
        $this->assertSame($amountRemaining2, $loan2->amount_remaining);
        $this->assertSame(0, $loan2->penalty);
        $this->assertSame(0, $loan2->penalty_remaining);
        $this->assertNotSame(0, $loan2->defaults);
        $this->assertNotNull($loan2->next_due_date);
        $this->assertSame(LoanOffer::OVERDUE, $loanOffer3->status);
        $this->assertSame($amountRemaining3, $loan3->amount_remaining);
        $this->assertSame(0, $loan3->penalty);
        $this->assertSame(0, $loan3->penalty_remaining);
        $this->assertNotSame(0, $loan3->defaults);
        $this->assertNotNull($loan3->next_due_date);
        $this->assertDatabaseHas('collection_cases', [
            'loan_offer_id' => $loanOffer1->id,
            'user_id' => $user1->id
        ]);
        $this->assertDatabaseHas('collection_cases', [
            'loan_offer_id' => $loanOffer2->id,
            'user_id' => $user2->id
        ]);
        $this->assertDatabaseHas('collection_cases', [
            'loan_offer_id' => $loanOffer3->id,
            'user_id' => $user1->id
        ]);
        Bus::assertDispatched(SendSms::class);
    }
}
