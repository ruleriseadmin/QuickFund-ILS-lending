<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example:
     *   ./vendor/bin/sail artisan mail:test you@example.com
     */
    protected $signature = 'mail:test {email : The email address to send the test message to}';

    /**
     * The console command description.
     */
    protected $description = 'Send a test email to verify mail configuration works.';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->info("📤 Sending test email to {$email}...");

        try {
            Mail::raw('This is a test email from your Laravel server.', function ($message) use ($email) {
                $message->to($email)
                    ->subject('✅ Test Email from Laravel');
            });

            $this->info('✅ Test email sent successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to send email: ' . $e->getMessage());
            return self::FAILURE;
        }

        return Command::SUCCESS;
    }
}
