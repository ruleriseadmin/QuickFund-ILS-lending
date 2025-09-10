<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Http, Bus};
use Laravel\Sanctum\Sanctum;
use App\Models\{LoanOffer, User, Loan, Role};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class UpdateLoanOfferStatusTest extends TestCase
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
        $response = $this->putJson(route('loan-offers.status', [
            'loanOffer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_loan_offer_status()
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
        $response = $this->putJson(route('loan-offers.status', [
            'loanOffer' => $loanOffer
        ]), [
            'status' => null
        ]);

        $response->assertInvalid(['status']);
        $response->assertUnprocessable();
    }

    /**
     * Loan offer status updated
     */
    public function test_loan_offer_status_is_updated_successfully()
    {
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
            ])
        ]);
        $loanOffer = LoanOffer::factory()->create([
            'status' => LoanOffer::PENDING
        ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $oldStatus = $loanOffer->status;
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('loan-offers.status', [
            'loanOffer' => $loanOffer
        ]), [
            'status' => LoanOffer::ACCEPTED
        ]);
        $loanOffer->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertNotEquals($oldStatus, $loanOffer->status);
    }

    /**
     * Loan offer status updated and closure message is sucessfully sent
     */
    public function test_loan_offer_status_is_updated_successfully_and_closed_message_is_successfully_sent()
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
            ])
        ]);
        $loanOffer = LoanOffer::factory()->create([
            'status' => LoanOffer::PENDING
        ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $oldStatus = $loanOffer->status;
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('loan-offers.status', [
            'loanOffer' => $loanOffer
        ]), [
            'status' => LoanOffer::CLOSED
        ]);
        $loanOffer->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertNotEquals($oldStatus, $loanOffer->status);
        Bus::assertDispatched(SendSms::class);
    }
}
