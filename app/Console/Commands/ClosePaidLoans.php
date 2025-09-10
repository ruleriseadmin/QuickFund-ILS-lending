<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{CollectionCase, LoanOffer};
use Illuminate\Support\Facades\DB;
use App\Services\Interswitch as InterswitchService;

class ClosePaidLoans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:close-paid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close the loans that are completely paid for.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get the loans that have been paid for but are not closed
        $paidLoans = LoanOffer::whereHas('loan', fn($query) => $query->where('amount_remaining', '<=', 0)
                                                                    ->where('penalty_remaining', '<=', 0))
                            ->where('status', '!=', LoanOffer::CLOSED)
                            ->get();

        foreach ($paidLoans as $paidLoan) {
            // Process the closure of the loan
            DB::transaction(function() use ($paidLoan) {
                // Close the loan
                $status = app()->make(InterswitchService::class)->status(LoanOffer::CLOSED, $paidLoan->id, true);

                $paidLoan->forceFill([
                    'status' => LoanOffer::CLOSED
                ])->save();

                // Update the collection case to closed
                $paidLoan->collectionCase()
                    ->update([
                        'status' => CollectionCase::CLOSED
                    ]);
            });
        }
    }
}
