<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use App\Mail\MonthlyReport;
use Tests\TestCase;

class SendMonthlyReportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Monthly report send successfully
     */
    public function test_monthly_report_was_sent_successfully()
    {
        Mail::fake();

        $this->artisan('loans:send-monthly-report');

        Mail::assertQueued(MonthlyReport::class);
    }
}
