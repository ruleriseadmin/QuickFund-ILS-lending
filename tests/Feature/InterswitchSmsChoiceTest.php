<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use App\Jobs\Interswitch\SendSms;
use App\Models\{Customer, LoanOffer, User, Loan};
use Tests\TestCase;

class InterswitchSmsChoiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Loan offer not found
     */
    public function test_loan_offer_is_not_found()
    {
        $loanOffer = LoanOffer::factory()->create();

        $this->actingAs(User::factory()->application()->create());
        $response = $this->postJson(route('loan-offers.sms-choice', [
            'loanOffer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_making_sms_choice_request()
    {
        $loanOffer = LoanOffer::factory()->create();
        
        $this->actingAs(User::factory()->application()->create());
        $response = $this->postJson(route('loan-offers.sms-choice', [
            'loanOffer' => $loanOffer
        ]), [
            'choice' => null
        ]);

        $response->assertInvalid(['choice']);
        $response->assertUnprocessable();
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
        $this->expectExceptionMessage(__('interswitch.uncollected_loan'));

        $this->actingAs(User::factory()->application()->create());
        $response = $this->postJson(route('loan-offers.sms-choice', [
            'loanOffer' => $loanOffer
        ]), [
            'choice' => 'choice'
        ]);
    }

    /**
     * SMS for different types were sent successfully
     * 
     * @dataProvider choices
     */
    public function test_sms_choices_were_dispatched_successfully($choice)
    {
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create([
                        'due_date' => now()->subDays(7)
                    ]);
        
        $this->actingAs(User::factory()->application()->create());
        $response = $this->postJson(route('loan-offers.sms-choice', [
            'loanOffer' => $loanOffer
        ]), [
            'choice' => $choice
        ]);

        $response->assertOk();
        Bus::assertDispatched(SendSms::class);
    }

    /**
     * The input values of the SMS choices
     */
    public function choices()
    {
        return [
            ['duplicate_loan_message'],
            ['insufficient_funds_collection_message'],
            ['disbursement_message'],
            ['debt_warning_days_3_message'],
            ['debt_warning_days_1_message'],
            ['insufficient_funds_message'],
            ['no_debts_at_hand_message'],
            ['loan_partially_collected_message'],
            ['loan_fully_collected_message'],
            ['late_fee_partially_collected_message'],
            ['late_fee_fully_collected_message'],
            ['loan_with_late_fee_partially_collected_message'],
            ['loan_with_late_fee_fully_collected_message'],
            ['debt_overdue_message'],
            ['debt_overdue_first_week_message'],
            ['debt_overdue_second_week_message'],
            ['debt_overdue_third_week_message'],
            ['debt_overdue_fourth_week_message'],
            ['has_no_debt_message'],
            ['has_debt_without_penalty_message'],
            ['has_debt_with_penalty_message'],
            ['blacklist_scoring_message'],
            ['failed_disbursement_due_to_wrong_account_number_message'],
            ['failed_disbursement_due_to_technical_issues'],
            ['debt_overdue_x_days_message']
        ];
    }

}
