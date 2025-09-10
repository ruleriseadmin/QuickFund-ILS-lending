<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use App\Mail\DailyReport;
use Tests\TestCase;

class SendDailyReportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Daily report send successfully
     */
    public function test_daily_report_was_sent_successfully()
    {
        Mail::fake();

        $this->artisan('loans:send-daily-report');

        Mail::assertQueued(DailyReport::class);
    }
}
