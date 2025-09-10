<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LoanOffer;

class CleanLoanOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the loan offers that have no collected loans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /**
         * Delete the loan offers that don't have collected loans
         */
        LoanOffer::doesntHave('loan')
                ->where('status', LoanOffer::NONE)
                ->where('created_at', '<=', now()->subHours(2))
                ->delete();
    }
}
