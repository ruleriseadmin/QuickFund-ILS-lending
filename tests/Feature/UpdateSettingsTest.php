<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{Setting, User, Role};
use Tests\TestCase;

class UpdateSettingsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_settings()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('settings.store'), [
            'loan_tenures' => null
        ]);

        $response->assertInvalid(['loan_tenures']);
        $response->assertUnprocessable();
    }

    /**
     * Settings updated successfully
     */
    public function test_settings_was_successfully_updated()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('settings.store'), [
            'minimum_loan_amount' => config('quickfund.minimum_loan_amount'),
            'maximum_loan_amount' => config('quickfund.maximum_loan_amount'),
            'loan_tenures' => config('quickfund.loan_tenures'),
            'percentage_increase_for_loyal_customers' => config('quickfund.percentage_increase_for_loyal_customers'),
            'loan_interest' => config('quickfund.loan_interest'),
            'default_interest' => config('quickfund.default_interest'),
            'days_to_attach_late_payment_fees' => config('quickfund.days_to_attach_late_payment_fees'),
            'use_credit_score_check' => config('quickfund.use_credit_score_check'),
            'use_crc_check' => config('quickfund.use_crc_check'),
            'use_first_central_check' => config('quickfund.use_first_central_check'),
            'minimum_credit_score' => config('quickfund.minimum_credit_score'),
            'days_to_make_crc_check' => config('quickfund.days_to_make_crc_check'),
            'days_to_make_first_central_check' => config('quickfund.days_to_make_first_central_check'),
            'total_amount_credited_per_day' => config('quickfund.total_amount_credited_per_day'),
            'maximum_amount_for_first_timers' => config('quickfund.maximum_amount_for_first_timers'),
            'should_give_loans' => config('quickfund.should_give_loans'),
            'emails_to_report' => config('quickfund.emails_to_report'),
            'use_crc_credit_score_check' => config('quickfund.use_crc_credit_score_check'),
            'use_first_central_credit_score_check' => config('quickfund.use_first_central_credit_score_check'),
            'minimum_credit_bureau_credit_score' => config('quickfund.minimum_approved_credit_score'),
            'maximum_outstanding_loans_to_qualify' => config('quickfund.maximum_outstanding_loans_to_qualify'),
            'bucket_0_to_9' => 100000,
            'bucket_10_to_19' => 100000,
            'bucket_20_to_29' => 100000,
            'bucket_30_to_39' => 100000,
            'bucket_40_to_49' => 100000,
            'bucket_50_to_59' => 100000,
            'bucket_60_to_69' => 100000,
            'bucket_70_to_79' => 100000,
            'bucket_80_to_89' => 100000,
            'bucket_90_to_100' => 100000,
            'days_to_stop_penalty_from_accruing' => config('quickfund.days_to_stop_penalty_from_accruing'),
            'minimum_days_for_demotion' => config('quickfund.minimum_days_for_demotion'),
            'maximum_days_for_demotion' => config('quickfund.maximum_days_for_demotion'),
            'days_to_blacklist_customer' => config('quickfund.days_to_blacklist_customer')
        ]);

        $response->assertValid();
        $this->assertDatabaseHas('settings', [
            'id' => Setting::MAIN_ID
        ]);
        $response->assertOk();
    }

}
