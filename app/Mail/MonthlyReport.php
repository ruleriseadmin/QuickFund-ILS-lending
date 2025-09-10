<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MonthlyReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The report date
     */
    public $reportDate;

    /**
     * The daily report
     */
    public $monthlyReport;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($reportDate, $monthlyReport)
    {
        $this->reportDate = $reportDate;
        $this->monthlyReport = $monthlyReport;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.monthly-report');
    }
}
