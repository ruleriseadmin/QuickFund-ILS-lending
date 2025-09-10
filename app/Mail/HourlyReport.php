<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HourlyReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The report date
     */
    public $reportDate;

    /**
     * The hourly report
     */
    public $hourlyReport;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($reportDate, $hourlyReport)
    {
        $this->reportDate = $reportDate;
        $this->hourlyReport = $hourlyReport;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.hourly-report');
    }
}
