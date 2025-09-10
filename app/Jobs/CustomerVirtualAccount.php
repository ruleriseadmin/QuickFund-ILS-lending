<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Interswitch as InterswitchService;

class CustomerVirtualAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The customer
     */
    public $customer;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customer)
    {
        $this->customer = $customer;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /**
         * If the customer does not have a virtual account, we create the virtual account if all the necessary
         * checks are put in place
         */
        if ($this->customer->virtualAccount()->doesntExist() &&
            isset($this->customer->first_name) &&
            isset($this->customer->last_name)) {
            // Make the request to create the virtual account of the customer
            $virtualAccount = $this->customer->virtualAccount()->firstOr(function() {
                $virtualAccountDetails = app()->make(InterswitchService::class)->virtualAccount($this->customer, true);

                return $this->customer->virtualAccount()
                                    ->updateOrCreate([

                                    ], [
                                        'payable_code' => $virtualAccountDetails['payableCode'],
                                        'account_name' => $virtualAccountDetails['accountName'],
                                        'account_number' => $virtualAccountDetails['accountNumber'],
                                        'bank_name' => $virtualAccountDetails['bankName'],
                                        'bank_code' => $virtualAccountDetails['bankCode']
                                    ]);
            });
        }
    }
}
