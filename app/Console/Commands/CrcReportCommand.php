<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Jobs\ProcessCrcReport;
use Illuminate\Console\Command;

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

        // dd([
        //     "all" => Customer::count(),
        //     "with_loan" => Customer::whereHas('loans')->count(),
        // ]);


        Customer::whereHas('loans') // 👈 only customers with loans
            ->chunk($batchSize, function ($customers) {
                ProcessCrcReport::dispatch($customers);
                // return false; // stop after first chunk (for testing)
            });

        $this->info('Credit report jobs dispatched successfully.');
        return Command::SUCCESS;
    }
}
