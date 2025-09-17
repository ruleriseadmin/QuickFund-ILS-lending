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
        $batchSize = 200;
        Customer::chunk($batchSize, function ($customers) {
            ProcessCrcReport::dispatch($customers);
            return false; // stop after first chunk, testing
        });

        $this->info('Credit report jobs dispatched successfully.');
        return Command::SUCCESS;
    }
}
