<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // The timezone to use for scheduling.
        $timezone = config('quickfund.timezone');

        // Send the hourly reports
        $schedule->command('loans:send-hourly-report')->timezone($timezone)->hourly()->runInBackground();

        // Send the daily reports
        $schedule->command('loans:send-daily-report')->timezone($timezone)->dailyAt('00:30')->runInBackground();

        // Send the monthly reports
        $schedule->command('loans:send-monthly-report')->timezone($timezone)->monthlyOn(1, '00:45')->runInBackground();

        // Command to add default payment to overdue loans.
        $schedule->command('loans:overdue')->timezone($timezone)->hourly()->withoutOverlapping();

        // Requery loans that are still accepted due to some kind of disbursement error
        $schedule->command('loans:requery-accepted')->timezone($timezone)->everyTenMinutes()->withoutOverlapping();

        // Close the paid loans
        $schedule->command('loans:close-paid')->timezone($timezone)->everyFifteenMinutes()->withoutOverlapping();

        // Reassign collection cases
        $schedule->command('collection-cases:reassign')->timezone($timezone)->withoutOverlapping()->runInBackground();

        // Retry all failed jobs.
        $schedule->command('queue:retry all')->timezone($timezone)->everyTenMinutes()->withoutOverlapping()->runInBackground();

        // Credit the customers that for some reason, the credit customer job fails
        $schedule->command('loans:credit-customers')->timezone($timezone)->everyTenMinutes()->withoutOverlapping();

        // Command to debit due loans
        $schedule->command('loans:debit-due')->timezone($timezone)->everyTwoHours()->withoutOverlapping()->runInBackground();

        // Command to debit overdue loans.
        $schedule->command('loans:debit-overdue')->timezone($timezone)->everyFourHours()->withoutOverlapping()->runInBackground();

        // Send due today reminder
        $schedule->command('loans:due-today-reminder')->timezone($timezone)->dailyAt('15:00')->withoutOverlapping()->runInBackground();

        // Send one day reminder of "OPEN" loans.
        $schedule->command('loans:one-day-reminder')->timezone($timezone)->dailyAt('18:00')->withoutOverlapping()->runInBackground();

        // Send three days reminder of "OPEN" loans.
        $schedule->command('loans:three-days-reminder')->timezone($timezone)->dailyAt('18:00')->withoutOverlapping()->runInBackground();

        // Send loan overdue message for loans overdue for one week
        $schedule->command('loans:overdue-one-week')->timezone($timezone)->dailyAt('12:00')->withoutOverlapping()->runInBackground();

        // Send loan overdue message for loans overdue for two weeks
        $schedule->command('loans:overdue-two-weeks')->timezone($timezone)->dailyAt('12:00')->withoutOverlapping()->runInBackground();

        // Send loan overdue message for loans overdue for three weeks
        $schedule->command('loans:overdue-three-weeks')->timezone($timezone)->dailyAt('12:00')->withoutOverlapping()->runInBackground();

        // Send loan overdue message for loans overdue for four weeks
        $schedule->command('loans:overdue-four-weeks')->timezone($timezone)->dailyAt('12:00')->withoutOverlapping()->runInBackground();

        // Send message on loans overdue three to ten days
        $schedule->command('messages:loan-overdue-three-to-ten-days')->timezone($timezone)->dailyAt('10:30')->withoutOverlapping()->runInBackground();

        // Send message on loans overdue eleven to twenty days
        $schedule->command('messages:loan-overdue-eleven-to-twenty-days')->timezone($timezone)->dailyAt('11:00')->withoutOverlapping()->runInBackground();

        // Send message on loans overdue twenty one to sixty days
        $schedule->command('messages:loan-overdue-twenty-one-to-sixty-days')->timezone($timezone)->dailyAt('11:30')->withoutOverlapping()->runInBackground();

        // Send message on loans overdue more than sixty days
        $schedule->command('messages:loan-overdue-more-than-sixty-days')->timezone($timezone)->dailyAt('12:30')->withoutOverlapping()->runInBackground();

        // Send message one day after loan full repayment
        // $schedule->command('messages:one-day-after-loan-closed')->timezone($timezone)->dailyAt('10:20')->withoutOverlapping()->runInBackground();

        // Send message five days after loan full repayment
        // $schedule->command('messages:five-days-after-loan-closed')->timezone($timezone)->dailyAt('10:50')->withoutOverlapping()->runInBackground();

        // Send message seven days after loan full repayment
        // $schedule->command('messages:seven-days-after-loan-closed')->timezone($timezone)->dailyAt('10:00')->withoutOverlapping()->runInBackground();

        // Send message ten days after loan full repayment
        // $schedule->command('messages:ten-days-after-loan-closed')->timezone($timezone)->dailyAt('11:20')->withoutOverlapping()->runInBackground();

        // Clean the records of uncollected loan offers
        $schedule->command('loans:clean')->timezone($timezone)->hourly()->withoutOverlapping()->runInBackground();

        // Send weekly credit report every Friday
        $schedule->command('credit:report')->timezone($timezone)->weeklyOn(5, '00:00')->withoutOverlapping()->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
