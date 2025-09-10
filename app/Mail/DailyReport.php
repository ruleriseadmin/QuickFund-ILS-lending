<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The report date
     */
    public $reportDate;

    /**
     * The daily report
     */
    public $dailyReport;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($reportDate, $dailyReport)
    {
        $this->reportDate = $reportDate;
        $this->dailyReport = $dailyReport;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.daily-report');
    }
}
