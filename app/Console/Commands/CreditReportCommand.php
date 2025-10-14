<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\Customer;
use Illuminate\Bus\Batch;
use App\Jobs\ProcessCrcReport;
use Illuminate\Console\Command;
use App\Mail\CreditReportCompleted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Jobs\ProcessFirstCentralReport;

class CreditReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credit:report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run weekly crc credit reports for customers';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $batchSize = 100;
        // Step 1: Authenticate
        $loginResponse = Http::post(config('services.first_central.reporting_base_url') . '/login', [
            'username' => config('services.first_central.reporting_username'),
            'password' => config('services.first_central.reporting_password'),
        ]);

        if (!$loginResponse->ok()) {
            Log::error('FirstCentral login failed', [
                'response' => $loginResponse->body()
            ]);
        }

        $token = $loginResponse->json('0.DataTicket');
        if (!$token) {
            Log::error('FirstCentral token missing', [
                'response' => $loginResponse->json(),
            ]);
        }

        $totalCustomers = Customer::whereHas('loans')->count();

        $batch = Bus::batch([])
            ->then(function (Batch $batch) use ($totalCustomers) {
                // Collect details for the email
                $summary = [
                    'batch_id' => $batch->id,
                    'total_jobs' => $batch->totalJobs,
                    'pending_jobs' => $batch->pendingJobs,
                    'processed_jobs' => $batch->processedJobs(),
                    'failed_jobs' => $batch->failedJobs,
                    'total_customers' => $totalCustomers,
                    // 'created_at' => now()->longRelativeToNowDiffForHumans(),
                    'created_at' => now()->toDateTimeString(),
                ];

                Mail::to(config('services.credit_report.feedback_email', 'pugnac55@gmail.com'))
                    ->send(new CreditReportCompleted($summary));
            })
            ->catch(function (Batch $batch, Throwable $e) {
                // 🚨 Log failure
                Log::error('Credit report batch failed', [
                    'batch_id' => $batch->id,
                    'name' => $batch->name,
                    'total_jobs' => $batch->totalJobs,
                    'failed_jobs' => $batch->failedJobs,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);


            })
            ->dispatch();

        Customer::whereHas('loans')
            ->chunk($batchSize, function ($customers) use ($batch, $token) {
                foreach ($customers as $customer) {
                    // $jobs = [new ProcessCrcReport($customer)];
                    $jobs = [];

                    // ✅ Only add FirstCentral job if token is present
                    if ($token) {
                        $jobs[] = new ProcessFirstCentralReport($customer, $token);
                    }

                    $batch->add($jobs);
                }
            });


        $this->info('Credit report jobs dispatched successfully.');
        return Command::SUCCESS;
    }

}
