<?php

namespace App\Jobs\Interswitch;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Interswitch as InterswitchService;

class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The message to be sent
     */
    public $message;

    /**
     * The customer identification
     */
    public $customerId;

    /**
     * The loan identification
     */
    public $loanId;

    /**
     * If the request should return an interswitch response or in app response
     */
    public $inApp;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($message, $customerId, $loanId, $inApp = false)
    {
        $this->message = $message;
        $this->customerId = $customerId;
        $this->loanId = $loanId;
        $this->inApp = $inApp;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $sendSms = app()->make(InterswitchService::class)->sendSms(
            $this->message,
            $this->customerId,
            $this->loanId,
            $this->inApp,
            true
        );

        /**
         * We check if a response code was returned. If it was not returned, we fail the job so it can be
         * retried again
         */
        if (!isset($sendSms['responseCode'])) {
            return $this->fail('Response code not returned.');
        }
    }
}
