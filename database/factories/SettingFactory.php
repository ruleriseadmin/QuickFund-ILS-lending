<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            "minimum_loan_amount" => "100000",
            "maximum_loan_amount" => "5000000000000",
            "loan_tenures" => "[14]",
            "percentage_increase_for_loyal_customers" => "50.4",
            "loan_interest" => "30.5",
            "default_interest" => "1",
            "days_to_attach_late_payment_fees" => "0",
            "use_credit_score_check" => "0",
            "use_crc_check" => "0",
            "use_first_central_check" => "0",
            "minimum_credit_score" => "50",
            "days_to_make_crc_check" => "90",
            "days_to_make_first_central_check" => "90",
            "total_amount_credited_per_day" => "9000000000000",
            "maximum_amount_for_first_timers" => "500000",
            "use_crc_credit_score_check" => "0",
            "use_first_central_credit_score_check" => "0",
            "minimum_credit_bureau_credit_score" => "500",
            "maximum_outstanding_loans_to_qualify" => "0",
            "should_give_loans" => "1",
            "emails_to_report" => "[\"lucas.d@quickfundmfb.com\", \"bolafunmi@gmail.com\", \"oluyemi.a@quickfundmfb.com\", \"adeola.o@quickfundmfb.com\", \"olashile.s@quickfundmfb.com\", \"tobi.f@quickfundmfb.com\", \"andrew@quickfundmfb.com\"]",
            "bucket_offers" => "{\"bucket_0_to_9\": 0, \"bucket_10_to_19\": 0, \"bucket_20_to_29\": 100000, \"bucket_30_to_39\": 100000, \"bucket_40_to_49\": 500000, \"bucket_50_to_59\": 500000, \"bucket_60_to_69\": 750000, \"bucket_70_to_79\": 800000, \"bucket_80_to_89\": 1000000, \"bucket_90_to_100\": 1500000}",
            "days_to_stop_penalty_from_accruing" => "60",
            "minimum_days_for_demotion" => null,
            "maximum_days_for_demotion" => null,
            "days_to_blacklist_customer" => null,
        ];
    }
}
