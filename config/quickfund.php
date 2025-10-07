<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Username
    |--------------------------------------------------------------------------
    |
    | The username used by the API application
    |
    */

    'username' => env('API_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | Application Password
    |--------------------------------------------------------------------------
    |
    | The password used by the API application
    |
    */

    'password' => env('API_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Terms And Conditions
    |--------------------------------------------------------------------------
    |
    | The URL of the terms and conditions
    |
    */

    'terms_and_conditions' => 'http://www.quickfundmfb.com/terms-and-conditions',

    /*
    |--------------------------------------------------------------------------
    | Per Page Result Set
    |--------------------------------------------------------------------------
    |
    | The default number of items return in pagination results
    |
    */

    'per_page' => 20,

    /*
    |--------------------------------------------------------------------------
    | Minimum Loan Amount
    |--------------------------------------------------------------------------
    |
    | The minimum loan amount that can be borrowed (Value is in Kobo)
    |
    */

    'minimum_loan_amount' => 500000,

    /*
    |--------------------------------------------------------------------------
    | Maximum Loan Amount
    |--------------------------------------------------------------------------
    |
    | The maximum loan amount that can be borrowed (Value is in Kobo)
    |
    */

    'maximum_loan_amount' => 20000000,

    /*
    |--------------------------------------------------------------------------
    | Loan Tenures
    |--------------------------------------------------------------------------
    |
    | The loan tenures that are allowed
    |
    */

    'loan_tenures' => [14, 30],

    /*
    |--------------------------------------------------------------------------
    | Percentage Increase For Loyal Customers
    |--------------------------------------------------------------------------
    |
    | The percentage amount increase a customer can be offered for being a
    | loyal customer
    |
    */

    'percentage_increase_for_loyal_customers' => 50,

    /*
    |--------------------------------------------------------------------------
    | Loan Interest Percentage
    |--------------------------------------------------------------------------
    |
    | The percentage interest applied on collected loan
    |
    */

    'loan_interest' => 31,

    /*
    |--------------------------------------------------------------------------
    | Default Interest Percentage
    |--------------------------------------------------------------------------
    |
    | The percentage interest applied on collected loan when a customer
    | defaults
    |
    */

    'default_interest' => 10,

    /*
    |--------------------------------------------------------------------------
    | Days After Default Date To Add Late Payment Fees
    |--------------------------------------------------------------------------
    |
    | The number of days to add late payment fees
    |
    */

    'days_to_attach_late_payment_fees' => 7,

    /*
    |--------------------------------------------------------------------------
    | Use Credit Score Check
    |--------------------------------------------------------------------------
    |
    | Boolean to use credit score check when giving customers offers
    |
    */

    'use_credit_score_check' => true,

    /*
    |--------------------------------------------------------------------------
    | Use CRC Check
    |--------------------------------------------------------------------------
    |
    | Boolean to use CRC check when giving customers offers
    |
    */

    'use_crc_check' => true,

    /*
    |--------------------------------------------------------------------------
    | Use First Central Check
    |--------------------------------------------------------------------------
    |
    | Boolean to use First Central check when giving customers offers
    |
    */

    'use_first_central_check' => true,

    /*
    |--------------------------------------------------------------------------
    | Minimum Credit Score
    |--------------------------------------------------------------------------
    |
    | Minimum credit score to give loans to customers
    |
    */

    'minimum_credit_score' => 50,

    /*
    |--------------------------------------------------------------------------
    | Minimum Approved Credit Score
    |--------------------------------------------------------------------------
    |
    | The minimum approved credit score
    |
    */

    'minimum_approved_credit_score' => 300,

    /*
    |--------------------------------------------------------------------------
    | Maximum Approved Credit Score
    |--------------------------------------------------------------------------
    |
    | The maximum approved credit score
    |
    */

    'maximum_approved_credit_score' => 850,

    /*
    |--------------------------------------------------------------------------
    | Days For CRC Check
    |--------------------------------------------------------------------------
    |
    | Minimum number of days to make fresh CRC check
    |
    */

    'days_to_make_crc_check' => 90,

    /*
    |--------------------------------------------------------------------------
    | Days For First Central Check
    |--------------------------------------------------------------------------
    |
    | Minimum number of days to make fresh First Central check
    |
    */

    'days_to_make_first_central_check' => 90,

    /*
    |--------------------------------------------------------------------------
    | Use CRC Credit Score Check
    |--------------------------------------------------------------------------
    |
    | Boolean to use CRC credit score check
    |
    */

    'use_crc_credit_score_check' => true,

    /*
    |--------------------------------------------------------------------------
    | Use First Central Credit Score Check
    |--------------------------------------------------------------------------
    |
    | Boolean to use First Central credit score check
    |
    */

    'use_first_central_credit_score_check' => true,

    /*
    |--------------------------------------------------------------------------
    | Maximum Outstanding Loans To Qualify For Offers
    |--------------------------------------------------------------------------
    |
    | The maximum outstanding loans to qualify for offers
    |
    */

    'maximum_outstanding_loans_to_qualify' => 0,

    /*
    |--------------------------------------------------------------------------
    | Naira Currency Representation
    |--------------------------------------------------------------------------
    |
    | The representation of Naira in SMS
    |
    */

    'currency_representation' => 'NGN',

    /*
    |--------------------------------------------------------------------------
    | Loan Request URL
    |--------------------------------------------------------------------------
    |
    | The URL to make a loan request
    |
    */

    'loan_request_url' => 'https://www.quickteller.com/lender/quickfund',

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone used by the application
    |
    */

    'timezone' => 'Africa/Lagos',

    /*
    |--------------------------------------------------------------------------
    | Date Query Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone to use for date queries
    |
    */

    'date_query_timezone' => 'Atlantic/Cape_Verde',

    /*
    |--------------------------------------------------------------------------
    | Total Amount Credited Per Day
    |--------------------------------------------------------------------------
    |
    | The total amount to be given to customers per day (Value is in Kobo)
    |
    */

    'total_amount_credited_per_day' => 50000000,

    /*
    |--------------------------------------------------------------------------
    | Maximum Amount For First Timers
    |--------------------------------------------------------------------------
    |
    | The maximum amount that can be available for first timers
    |
    */

    'maximum_amount_for_first_timers' => 50000,

    /*
    |--------------------------------------------------------------------------
    | Should Give Loans
    |--------------------------------------------------------------------------
    |
    | Check to know if loans should be given
    |
    */

    'should_give_loans' => true,

    /*
    |--------------------------------------------------------------------------
    | Report Emails
    |--------------------------------------------------------------------------
    |
    | The emails to send reports to
    |
    */

    'emails_to_report' => [
        // 'oluyemi.a@quickfundmfb.com',
        // 'adebayooluyemi4@gmail.com',
        'isikakudaniel@yahoo.com',
        'isikakudaniel@gmail.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Number Of Days To Stop Penalty From Accruing
    |--------------------------------------------------------------------------
    |
    | The number of days to stop penalty from accruing
    |
    */

    'days_to_stop_penalty_from_accruing' => 60,

    /*
    |--------------------------------------------------------------------------
    | Minimum Days For Demotion
    |--------------------------------------------------------------------------
    |
    | The minimum number of days a loan is "OVERDUE" before demotion upon next
    | loan application
    |
    */

    'minimum_days_for_demotion' => 30,

    /*
    |--------------------------------------------------------------------------
    | Maximum Days For Demotion
    |--------------------------------------------------------------------------
    |
    | The maximum number of days a loan is "OVERDUE" before demotion upon next
    | loan application
    |
    */

    'maximum_days_for_demotion' => 90,

    /*
    |--------------------------------------------------------------------------
    | Days To Blacklist User
    |--------------------------------------------------------------------------
    |
    | The number of days a user will be blacklisted on default on previously
    | collected loans
    |
    */

    'days_to_blacklist_customer' => 90,

    /*
    |--------------------------------------------------------------------------
    | Available Role Permissions
    |--------------------------------------------------------------------------
    |
    | The permissions that can be assigned by the application
    |
    */

    'available_permissions' => [
        'sms', // For SMS related privileges
        'offers', // For offer related privileges
        'fees', // For fee related privileges
        'loans', // For loan related privileges
        'loan-search', // For loan search privileges
        'loan-credit', // For loan credit privileges
        'loan-debit', // For loan debit privileges
        'loan-status', // For loan status privileges
        'loan-transactions', // For loan transactions privileges
        'loan-refund', // For loan refund privileges
        'loan-sms-choice', // For loan sms choice privileges
        'loan-metrics', // For loan metrics privileges
        'loan-payment-processing', // For loan payment processing privileges
        'transactions', // For transactions privileges
        'transaction-query', // For transaction query privileges
        'transaction-search', // For transaction search privileges
        'customers', // For customers privileges
        'customer-bvn-search', // For customer BVN search privileges
        'customer-name-search', // For customer name search privileges
        'customer-credit-score', // For customer credit score privileges
        'customer-loans', // For customer loans privileges
        'customer-virtual-accounts', // For customer virtual accounts privileges
        'customer-credit-bureau-data', // For customer credit bureau data privileges
        'whitelists', // For whitelists privileges
        'blacklists', // For blacklists privileges
        'crcs', // For CRC records privileges
        'crc-histories', // For CRC histories records privileges
        'crc-reports', // For CRC reports privileges
        'first-centrals', // For First Central records privileges
        'first-central-histories', // For First Central histories records privileges
        'first-central-reports', // For First Central reports privileges
        'activity-logs', // For activity logs privileges
        'credit-scores', // For credit scores privileges
        'collection-cases', // For collection cases privileges
        'super-collector' // For super collector privileges
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Role Permissions
    |--------------------------------------------------------------------------
    |
    | The default permissions that are assigned by the application
    |
    */

    'default_permissions' => [
        'change-password', // For changing passwords
    ],

];
