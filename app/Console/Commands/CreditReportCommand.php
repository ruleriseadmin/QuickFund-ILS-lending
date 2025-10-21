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
        // Step 1: Authenticate for First Central
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

        // Total customers with loans (used for reporting)
        $totalCustomers = Customer::whereHas('loans')->count();

        // Create CRC batch
        $crcBatch = Bus::batch([])
            ->then(function (Batch $batch) use ($totalCustomers) {
                $summary = [
                    'bureau' => 'CRC',
                    'batch_id' => $batch->id,
                    'total_jobs' => $batch->totalJobs,
                    'pending_jobs' => $batch->pendingJobs,
                    'processed_jobs' => $batch->processedJobs(),
                    'failed_jobs' => $batch->failedJobs,
                    'total_customers' => $totalCustomers,
                    'created_at' => now()->toDateTimeString(),
                ];

                Mail::to(config('services.credit_report.feedback_email', 'pugnac55@gmail.com'))
                    ->send(new CreditReportCompleted($summary));
            })
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error('CRC credit report batch failed', [
                    'batch_id' => $batch->id,
                    'name' => $batch->name,
                    'total_jobs' => $batch->totalJobs,
                    'failed_jobs' => $batch->failedJobs,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            })
            ->dispatch();

        // Create First Central batch only if token is present
        $firstCentralBatch = null;
        if ($token) {
            $firstCentralBatch = Bus::batch([])
                ->then(function (Batch $batch) use ($totalCustomers) {
                    $summary = [
                        'bureau' => 'First Central',
                        'batch_id' => $batch->id,
                        'total_jobs' => $batch->totalJobs,
                        'pending_jobs' => $batch->pendingJobs,
                        'processed_jobs' => $batch->processedJobs(),
                        'failed_jobs' => $batch->failedJobs,
                        'total_customers' => $totalCustomers,
                        'created_at' => now()->toDateTimeString(),
                    ];

                    Mail::to(config('services.credit_report.feedback_email', 'pugnac55@gmail.com'))
                        ->send(new CreditReportCompleted($summary));
                })
                ->catch(function (Batch $batch, Throwable $e) {
                    Log::error('First Central credit report batch failed', [
                        'batch_id' => $batch->id,
                        'name' => $batch->name,
                        'total_jobs' => $batch->totalJobs,
                        'failed_jobs' => $batch->failedJobs,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                })
                ->dispatch();
        }

        // Chunk and add jobs to the appropriate batches
        Customer::whereHas('loans')
            ->chunk($batchSize, function ($customers) use ($crcBatch, $firstCentralBatch, $token) {
                foreach ($customers as $customer) {
                    // Add CRC job to CRC batch
                    $crcBatch->add([new ProcessCrcReport($customer)]);

                    // Add First Central job to its batch if available
                    if ($token && $firstCentralBatch) {
                        $firstCentralBatch->add([new ProcessFirstCentralReport($customer, $token)]);
                    }
                }
            });

        $this->info('Credit report jobs dispatched successfully for CRC' . ($token ? ' and First Central' : '') . '.');
        return Command::SUCCESS;
    }
}