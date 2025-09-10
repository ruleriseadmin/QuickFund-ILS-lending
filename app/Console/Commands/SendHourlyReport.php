<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Mail};
use Illuminate\Support\Carbon;
use App\Models\{Loan, LoanOffer, Setting, Transaction, Offer};
use App\Mail\HourlyReport;

class SendHourlyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:send-hourly-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send hourly report of loans.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = 'Africa/Lagos';

        // The report to take for
        $reportDate = now()->timezone($timezone)->subHour()->format('Y-m-d');

        // The report hours
        $reportHours = now()->timezone($timezone)->subHour()->format('G');

        $dbTimezone = config('quickfund.date_query_timezone');

        // Get all the offers
        $offers = Offer::orderBy('amount')->get();

        $hourlyReport = [];

        for ($i = 0; $i <= (int) $reportHours; $i++) {
            $key = "{$reportDate}-{$i}";

            // The from timestamp for queries
            $from = Carbon::parse($reportDate)->addHour($i)->startOfHour()->timezone($dbTimezone)->toDateTimeString();

            // The to timestamp for queries
            $to = Carbon::parse($reportDate)->addHour($i)->endOfHour()->timezone($dbTimezone)->toDateTimeString();

            /**
             * We store the daily report for preceeding hours since we will still need the data later for future
             */
            $hourlyReport[$key] = Cache::remember($key, now()->addDay(), function () use ($i, $from, $to, $offers) {
                // The query for disbursement
                $disbursedQuery = Loan::whereBetween('created_at', [
                    $from,
                    $to
                ])
                    ->where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
                        LoanOffer::OPEN,
                        LoanOffer::OVERDUE,
                        LoanOffer::CLOSED
                    ]))
                        ->orWhereHas('transactions', fn($query2) => $query2->where('type', Transaction::CREDIT)
                            ->where('interswitch_transaction_code', '00')
                            ->whereNotNull('interswitch_transaction_reference')));

                // The query for collection
                $collectedQuery = Loan::where(fn($query) => $query->whereHas('loanOffer', fn($query2) => $query2->where('status', LoanOffer::CLOSED)
                    ->whereBetween('updated_at', [
                        $from,
                        $to
                    ])));

                return [
                    'hour' => $i,
                    'total_money_provided' => $disbursedQuery->sum('amount'),
                    'total_money_collected' => $collectedQuery->sum('amount_payable') + $collectedQuery->sum('penalty'),
                    'credit_body_collected' => $collectedQuery->sum('amount'),
                    'offers' => $offers->map(function ($offer) use ($disbursedQuery, $collectedQuery) {
                        /**
                         * We clone the disbursed and the collected query because we want to chain where clauses
                         * and looping them might cause unexpected results if they are not cloned
                         */
                        $clonedDisbursedQuery = clone $disbursedQuery;
                        $clonedCollectedQuery = clone $collectedQuery;

                        // Get the disbursed query for an amount
                        $disbursedAmountQuery = $clonedDisbursedQuery->where('amount', $offer->amount);

                        // Get the collected query for an amount
                        $collectedAmountQuery = $clonedCollectedQuery->where('amount', $offer->amount);

                        return [
                            'amount' => $offer->amount,
                            'provided_loans' => $disbursedAmountQuery->count(),
                            'collected_loans' => $collectedAmountQuery->count(),
                            'money_provided' => $disbursedAmountQuery->sum('amount'),
                            'money_collected' => $collectedAmountQuery->sum('amount_payable') + $collectedAmountQuery->sum('penalty')
                        ];
                    }),
                ];
            });
        }

        $setting = Setting::find(Setting::MAIN_ID);

        // The emails to report to
        $reportEmails = $setting?->emails_to_report ?? config('quickfund.emails_to_report');

        // Send the hourly report to the emails
        Mail::to($reportEmails)
            ->send(new HourlyReport($reportDate, $hourlyReport));
    }
}
