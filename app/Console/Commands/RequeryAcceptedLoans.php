<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{LoanOffer, Transaction};
use App\Services\Interswitch as InterswitchService;
use App\Services\Loans\Calculator as LoanCalculator;

class RequeryAcceptedLoans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:requery-accepted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Requery the accepted loans';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /**
         * Get the "ACCEPTED" loan offers in the past one hour
         */
        $acceptedLoanOffers = LoanOffer::with([
                                        'customer',
                                        'loan.transactions' => fn($query) => $query->whereIn('type', [
                                            Transaction::CREDIT,
                                            Transaction::NONE
                                        ])
                                    ])
                                    ->whereHas('loan.transactions', fn($query) => $query->whereIn('type', [
                                        Transaction::CREDIT,
                                        Transaction::NONE
                                    ]))
                                    ->where('status', LoanOffer::ACCEPTED)
                                    ->where('updated_at', '<=', now()->subHours(2))
                                    ->get();

        foreach ($acceptedLoanOffers as $acceptedLoanOffer) {
            /**
             * Due to the fact that a user might have multiple credit transactions, which is in part because some
             * could fail with the type of NONE, we make sure that we take all the transactions we can get
             * and query them
             */
            // Counter for requeried transactions
            $requeriedTransactions = 0;

            // Get the total transactions on the loan
            $totalTransactions = $acceptedLoanOffer->loan->transactions->count();

            // Loop through the transactions of the loan
            foreach ($acceptedLoanOffer->loan->transactions as $transaction) {
                /**
                 * Requery the transaction and depending on the response, we either update the status to "FAILED"
                 * or "OPEN"
                 */
                // Make the request to query a transaction
                $queryDetails = app()->make(InterswitchService::class)->query($transaction->id, true, true);

                /**
                 * We check if the transaction returned with a response code. This means that the transaction
                 * details was successfully fetched.
                 */
                if (isset($queryDetails['responseCode'])) {
                    // Based on the transaction code, we know if the transaction was successful or not
                    if ($queryDetails['responseCode'] === '00') {
                        /**
                         * Here the transaction was successful. We store the transaction and change the status
                         * of the loan to "OPEN"
                         */
                        // Credit was successful. Process the successful credit of a customer
                        app()->make(LoanCalculator::class)->processCredit($queryDetails, $acceptedLoanOffer, $transaction, true, true);
                    } else {
                        // Response gotten back on credit was not successful. Process the non credit situation
                        app()->make(LoanCalculator::class)->processNonCredit($queryDetails, $transaction, true);
                    }

                    $requeriedTransactions++;
                }
            }

            // We check if all the the transactions were successfully requeried
            if ($requeriedTransactions >= $totalTransactions) {
                /**
                 * All transactions were successful. So we check if the loan is "OPEN" based on previous
                 * requeried transactions. If it is not "OPEN", then we update the loan status to "FAILED"
                 */
                if ($acceptedLoanOffer->status !== LoanOffer::OPEN) {
                    $status = app()->make(InterswitchService::class)->status(LoanOffer::FAILED, $acceptedLoanOffer->id, true);

                    $acceptedLoanOffer->forceFill([
                        'status' => LoanOffer::FAILED
                    ])->save();
                }

                $acceptedLoanOffer->forceFill([
                    'last_requeried_at' => now()
                ])->save();
            }
        }
    }
}
