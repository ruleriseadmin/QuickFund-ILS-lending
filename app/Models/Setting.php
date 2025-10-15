<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Casts\BucketOffer;

class Setting extends Model
{
    use HasFactory;

    /**
     * The ID of the setting
     */
    public const MAIN_ID = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'minimum_loan_amount',
        'maximum_loan_amount',
        'loan_tenures',
        'percentage_increase_for_loyal_customers',
        'loan_interest',
        'default_interest',
        'days_to_attach_late_payment_fees',
        'use_credit_score_check',
        'use_crc_check',
        'use_first_central_check',
        'minimum_credit_score',
        'days_to_make_crc_check',
        'days_to_make_first_central_check',
        'total_amount_credited_per_day',
        'maximum_amount_for_first_timers',
        'should_give_loans',
        'emails_to_report',
        'use_crc_credit_score_check',
        'use_first_central_credit_score_check',
        'minimum_credit_bureau_credit_score',
        'maximum_outstanding_loans_to_qualify',
        'bucket_offers',
        'days_to_stop_penalty_from_accruing',
        'minimum_days_for_demotion',
        'maximum_days_for_demotion',
        'days_to_blacklist_customer'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        'minimum_loan_amount' => 'integer',
        'maximum_loan_amount' => 'integer',
        'loan_tenures' => 'array',
        'percentage_increase_for_loyal_customers' => 'float',
        'loan_interest' => 'float',
        'default_interest' => 'float',
        'days_to_attach_late_payment_fees' => 'integer',
        'use_credit_score_check' => 'boolean',
        'use_crc_check' => 'boolean',
        'use_first_central_check' => 'boolean',
        'minimum_credit_score' => 'float',
        'days_to_make_crc_check' => 'integer',
        'days_to_make_first_central_check' => 'integer',
        'total_amount_credited_per_day' => 'integer',
        'maximum_amount_for_first_timers' => 'integer',
        'should_give_loans' => 'boolean',
        'emails_to_report' => 'array',
        'use_crc_credit_score_check' => 'boolean',
        'use_first_central_credit_score_check' => 'boolean',
        'minimum_credit_bureau_credit_score' => 'integer',
        'maximum_outstanding_loans_to_qualify' => 'integer',
        'bucket_offers' => BucketOffer::class,
        'days_to_stop_penalty_from_accruing' => 'integer',
        'minimum_days_for_demotion' => 'integer',
        'maximum_days_for_demotion' => 'integer',
        'days_to_blacklist_customer' => 'integer'
    ];
    }
}
