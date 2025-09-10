<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Mail, Cache};
use App\Mail\MonthlyReport;
use App\Models\{Loan, LoanOffer, Transaction, Customer, Setting};

class SendMonthlyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:send-monthly-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send monthly report of loans.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = 'Africa/Lagos';

        // The report days
        $reportDays = now()->timezone($timezone)->subMonth()->format('m');

        // The report date
        $reportDate = now()->timezone($timezone)->subMonth()->format('Y-m-d');

        $dbTimezone = config('quickfund.date_query_timezone');

        $monthlyReport = [];

        for ($i = 1; $i <= (int) $reportDays;  $i++) {
            // The date for the current iteration
            $month = Carbon::parse($reportDate)->startOfYear()->addMonths($i - 1)->format('M');
            
            $key = "month-{$month}-report";

            // The from timestamp for queries
            $from = Carbon::parse($reportDate)->startOfYear()->addMonths($i - 1)->startOfMonth()->timezone($dbTimezone)->toDateTimeString();

            // The to timestamp for queries
            $to = Carbon::parse($reportDate)->startOfYear()->addMonths($i - 1)->endOfMonth()->timezone($dbTimezone)->toDateTimeString();

            $monthlyReport[$key] = Cache::remember($key, now()->addMonth(), function() use ($month, $from, $to) {
                $disbursedQuery = Loan::whereBetween('created_at', [
                                    $from,
                                    $to,
                                ])
                                ->where(fn($query) => $query->whereHas('loanOffer', fn($query) => $query->whereIn('status', [
                                                                LoanOffer::OPEN,
                                                                LoanOffer::OVERDUE,
                                                                LoanOffer::CLOSED
                                                            ]))
                                                            ->orWhereHas('transactions', fn($query2) => $query2->where('type', Transaction::CREDIT)
                                                                                                            ->where('interswitch_transaction_code', '00')
                                                                                                            ->whereNotNull('interswitch_transaction_reference')));

                $fullyRepaidQuery = Loan::whereBetween('updated_at', [
                                        $from,
                                        $to,
                                    ])
                                    ->where(fn($query) => $query->whereHas('loanOffer', fn($query2) => $query2->where('status', LoanOffer::CLOSED)));

                $firstTimeDebtorsQuery = Customer::whereHas('loanOffers', fn($query) => $query->whereIn('status', [
                                                    LoanOffer::OPEN,
                                                    LoanOffer::OVERDUE,
                                                    LoanOffer::CLOSED
                                                ]))
                                                ->whereHas('loanOffers.loan', fn($query) => $query->whereBetween('created_at', [
                                                    $from,
                                                    $to,
                                                ]))
                                                ->whereDoesntHave('loanOffers', fn($query) => $query->whereIn('status', [
                                                                                                        LoanOffer::OPEN,
                                                                                                        LoanOffer::OVERDUE,
                                                                                                        LoanOffer::CLOSED
                                                                                                    ])
                                                                                                    ->where('created_at', '<', $from));

                $uniqueDebtors = Customer::whereHas('loanOffers', fn($query) => $query->whereIn('status', [
                                        LoanOffer::OPEN,
                                        LoanOffer::OVERDUE,
                                        LoanOffer::CLOSED
                                    ]))
                                    ->whereHas('loanOffers.loan', fn($query) => $query->whereBetween('created_at', [
                                        $from,
                                        $to,
                                    ]))
                                    ->distinct(['id']);

                $penaltyQuery = Loan::whereBetween('updated_at', [
                                        $from,
                                        $to,
                                    ])
                                    ->where('penalty', '>', 0);

                return [
                    'month' => $month,
                    'count_of_disbursed_loans' => $disbursedQuery->count(),
                    'count_of_fully_repaid_loans' => $fullyRepaidQuery->count(),
                    'volume_of_disbursed_loans' => $disbursedQuery->sum('amount'),
                    'repayment_and_fees' => $fullyRepaidQuery->sum('amount_payable') + $fullyRepaidQuery->sum('penalty'),
                    'unique_debtors' => $uniqueDebtors->count(),
                    'first_time_debtors' => $firstTimeDebtorsQuery->count(),
                    'penalty_fee_collected' => $penaltyQuery->sum('penalty') - $penaltyQuery->sum('penalty_remaining'),                                           
                ];
            });
        }
        
        $setting = Setting::find(Setting::MAIN_ID);

        // The emails to report to
        $reportEmails = $setting?->emails_to_report ?? config('quickfund.emails_to_report');

        // Send the hourly report to the emails
        Mail::to($reportEmails)
            ->send(new MonthlyReport($reportDate, $monthlyReport));
    }
}
