<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\Customer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\CreditBureau\FirstCentral;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessFirstCentralReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    protected Customer $customer;
    protected $token;

    /**
     * Create a new job instance.
     *
     * @param Customer $customer
     * @param string $token
     */
    public function __construct(Customer $customer, string $token)
    {
        $this->customer = $customer;
        $this->token = $token;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Step 2: Report loans for this customer
            $firstCentral = new FirstCentral();
            $result = $firstCentral->reportCustomerLoans($this->customer, $this->token);

            Log::info("Processed FirstCentral report for customer {$this->customer->id}", $result);

        } catch (\Throwable $e) {
            Log::error('Process FirstCentralReport job failed', [
                'customer_id' => $this->customer->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }


}
