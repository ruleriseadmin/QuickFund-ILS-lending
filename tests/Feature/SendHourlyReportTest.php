<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use App\Mail\HourlyReport;
use Tests\TestCase;

class SendHourlyReportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Hourly report send successfully
     */
    public function test_hourly_report_was_sent_successfully()
    {
        Mail::fake();

        $this->artisan('loans:send-hourly-report');

        Mail::assertQueued(HourlyReport::class);
    }
}
