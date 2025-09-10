<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use App\Models\{LoanOffer, CollectionCase};
use Tests\TestCase;

class ClosePaidLoansTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Loan offers were successfully closed
     */
    public function test_loan_offers_were_successfully_closed()
    {
        $loanOffer = LoanOffer::factory([
                                'status' => LoanOffer::OPEN
                            ])
                            ->hasLoan([
                                'amount_remaining' => 0
                            ])
                            ->create();
        $collectionCase = CollectionCase::factory()
                                        ->for($loanOffer)
                                        ->create();
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

        $this->artisan('loans:close-paid');
        $loanOffer->refresh();
        $collectionCase->refresh();

        $this->assertSame(LoanOffer::CLOSED, $loanOffer->status);
        $this->assertSame(CollectionCase::CLOSED, $collectionCase->status);
    }
}
