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
use Illuminate\Support\Facades\Mail;

class CrcReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crc:report';

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
        $batchSize = 400;

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
                    'created_at' => now()->longRelativeToNowDiffForHumans(),
                    // 'created_at' => now()->toDateTimeString(),
                ];

                // Only add "name" if it exists
                // if (!empty($batch->name)) {
                //     $summary['name'] = $batch->name;
                // }
    
                Mail::to(config('services.crc.feedback_email', 'pugnac55@gmail.com'))
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
            // ->take(100) // 👈 testing with 100 customers only
            ->chunk($batchSize, function ($customers) use ($batch) {
                $batch->add([
                    new ProcessCrcReport($customers)
                ]);
            });

        $this->info('Credit report jobs dispatched successfully.');
        return Command::SUCCESS;
    }

}
