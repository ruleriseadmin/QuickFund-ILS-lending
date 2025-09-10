<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\LoanOffer;
use Tests\TestCase;

class CleanLoanOffersTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Loan offers were cleaned
     */
    public function test_loan_offers_were_cleaned()
    {
        $loanOffer = LoanOffer::factory()->create([
            'status' => LoanOffer::NONE,
            'created_at' => now()->subHours(5)
        ]);

        $this->artisan('loans:clean');

        $this->assertModelMissing($loanOffer);
    }

}
