<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Bus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\{LoanOffer, Customer};
use App\Jobs\SendFiveDaysAfterLoanClosedMessage as SendFiveDaysAfterLoanClosedMessageJob;

class SendFiveDaysAfterLoanClosedMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:five-days-after-loan-closed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send message to customers five days after loan has been closed';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = config('quickfund.date_query_timezone');

        // Get the customers that have fully repaid a loan in the last 5 days and have not collected a loan since then
        $customers = Customer::with([
                                'loanOffers' => fn($query) => $query->where('status', LoanOffer::CLOSED)
                                                                    ->latest()
                            ])
                            ->whereHas('loanOffers', fn($query) => $query->whereDate('updated_at', Carbon::parse(now()->timezone($timezone)->subDays(5)->toDateTimeString()))
                                                                        ->where('status', LoanOffer::CLOSED))
                            ->whereDoesntHave('loanOffers', fn($query) => $query->whereIn('status', [
                                                                                LoanOffer::OPEN,
                                                                                LoanOffer::OVERDUE
                                                                            ]))
                            ->get();

        if ($customers->isNotEmpty()) {
            $batch = Bus::batch([])
                        ->name('send-five-days-after-loan-closed-message')
                        ->allowFailures()
                        ->dispatch();

            $customers->chunk(100)
                            ->each(fn($customerChunk) => $batch->add(new SendFiveDaysAfterLoanClosedMessageJob($customerChunk)));
        }
    }
}
