<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interswitch Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used as the responses for the 
    | Interswitch API.
    |
    */

    'success' => 'Request successful.',
    'no_offer' => 'You currently have no loan offer.',
    'declined_offer' => 'This offer has been declined.',
    'offer_expired' => 'This offer is expired.',
    'unknown_offer' => 'Offer not found.',
    'customer_not_found' => 'Customer not found in our records.',
    'uncollected_loan' => 'No record of a loan collected on this offer.',
    'outstanding_loans' => 'You have :count outstanding :pluralization of :amount.',
    'transaction_exists' => 'A :type transaction has already been done on this loan offer',
    'transaction_forbidden' => 'Cannot :type customer as loan status is ":loan_status". It must be ":expected_loan_status"',
    'loan_paid_in_full' => 'Loan has already been paid in full',
    'loan_unprocessable' => 'Loan payment processing cannot be completed as status is ":loan_status". It must be :expected_loan_statuses',
    'loan_unacceptable' => 'You cannot accept this offer as it is ":loan_status". It must be ":expected_loan_status"',
    'customer_ineligible' => 'You are ineligible to accept this offer.',
    'account_number_blocked' => 'You have to use the account number when you first applied.',
    
    
    'loan_repayment_message' => "Your payment of :amount was successful. You still owe :remaining_amount. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#",
    'full_loan_repayment_message' => "You have made your final repayment and your loan is now closed. To make a new request, dial *723*6# or visit https://www.quickteller.com/lender/quickfund",
    'loan_disbursement_failed_message' => "Dear Customer, we are unable to process your request. Please try again later, dial *723*6# or visit https://www.quickteller.com/lender/quickfund",


    'duplicate_loan_message' => 'Dear Customer, your previous request is currently being processed. Please try again later.',
    'insufficient_funds_collection_message' => 'Your account balance is not sufficient to repay your :current_amount_remaining micro loan. Please add funds today. Starting tomorrow late fee on your loan will be applied.',
    'disbursement_message' => 'Loan successfully provided. Amount: :amount, Service fee: :service_fee. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#. Please pay back no later than :due_date',
    'debt_warning_days_3_message' => 'Dear Customer, your loan balance is :loan_balance. Repayment is due on :due_date. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#. Kindly make payment before the due date.',
    'debt_warning_days_1_message' => 'Dear Customer, your loan balance is :loan_balance. Repayment is due TOMORROW. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*ussd_repayment_amount#. Kindly make payment before the due date.',
    'insufficient_funds_message' => 'Dear customer, you do not have sufficient funds in your account to service your loan repayment. Kindly make payment and try again.',
    'no_debts_at_hand_message' => "Dear Customer! You currently don't have any unpaid micro-loans. Visit :loan_request_url to check if you are eligible to get loan now.",
    'loan_partially_collected_message' => 'Thank you for paying back :recovered_amount of your micro-loan. You only have :remaining_amount remaining to pay back.',
    'loan_fully_collected_message' => 'Thank you for fully repaying :covered_amount of your micro-loan. You may be eligible for fresh loan! Visit :loan_request_url to check.',
    'late_fee_partially_collected_message' => 'Thank you for paying back :covered_late_fee late fee charges. You only have :remaining_late_fee of late fee and :debt_amount of micro-loan amount remaining to pay back.',
    'late_fee_fully_collected_message' => 'Thank you for paying back :covered_late_fee late fee charges. You only have :debt_amount remaining to pay back.',
    'loan_with_late_fee_partially_collected_message' => 'Thank you for paying back :covered_late_fee late fee charges and :recovered_amount of your micro-loan. You only have :amount_remaining remaining to pay back.',
    'loan_with_late_fee_fully_collected_message' => 'Thank you for fully repaying :covered_late_fee late fee charges and :recovered_amount of your micro-loan. You may be eligible for fresh loan! Visit url :loan_request_url to check.',
    'debt_due_today_message' => 'Dear Customer, your loan balance is :loan_balance. Repayment is due Today. please make payment via https://www.quickteller.com/lender/quickfund :virtual_account_details or via USSD *723*3389001*:ussd_repayment_amount# now to maintain a perfect record.',
    'debt_overdue_message' => 'Your micro loan balance of :amount_remaining is now overdue. :default_amount has been added to the remaining amount of the micro-loan. Please note that late fee charge on your loan balance will accrue daily until loan is fully repaid. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#.',
    'debt_overdue_x_days_message' => 'Your micro loan balance of :amount_remaining is overdue :overdue_days :pluralization. Please note that late fee charge on your loan balance will accrue daily until loan is fully repaid. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#.',
    'debt_overdue_first_week_message' => 'Your micro loan balance of :amount_remaining is already overdue :overdue_days days. Please note that late fee charge on your loan balance will accrue daily until loan is fully repaid. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#.',
    'debt_overdue_second_week_message' => 'Your micro loan balance of :amount_remaining is already overdue by :overdue_days days. Note that late fee charge on your loan balance will continue to accrue daily until your loan is fully repaid. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#.',
    'debt_overdue_third_week_message' => 'Your micro loan balance of :amount_remaining is still overdue by :overdue_days days. Please note that your credit history may be negatively affected. Kindly make your payment now. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#.',
    'debt_overdue_fourth_week_message' => 'Your loan balance - :amount_remaining is still overdue by :overdue_days days. Your default history will be logged with all credit bureaus. You may not be able to access loan anywhere. You can also make repayment via the link https://www.quickteller.com/lender/quickfund :virtual_account_details or pay via USSD *723*3389001*:ussd_repayment_amount#.',
    'has_no_debt_message' => 'Dear Customer, you do not have a micro loan. Kindly visit :loan_request_url to apply.',
    'has_debt_without_penalty_message' => 'Micro-Loan: :amount taken on :credit_date, Remaining amount: :remaining_amount, Due Date: :due_date',
    'has_debt_with_penalty_message' => 'Micro-Loan: :amount taken on :credit_date, Remaining amount: :remaining_amount, Penal fee: :penal_fee, Due Date: :due_date',
    'blacklist_scoring_message' => 'Dear Customer, Microlending service is not available to you. For details, please, visit url :assistance_url',
    'failed_disbursement_due_to_wrong_account_number_message' => 'Dear Customer, your account number is invalid. Please check and try again',
    'failed_disbursement_due_to_technical_issues' => 'Dear Customer, we are unable to process your request. Please try again later',
    'loan_already_credited' => 'Customer has already been credited for this loan.',
    'blacklisted' => 'Dear customer, you do not currently qualify for a loan, please try again later.',
    'loan_fully_repaid_after_7_days' => 'Dear Customer, you have qualified for a higher loan offer, apply today and receive an offer within 5 minutes. Click here to start your application https://www.quickteller.com/lender/quickfund. You deserve credit!',
    'loan_overdue_3_to_10_days' => 'Dear customer, your loan was due :due_days_difference ago, repay today to keep enjoying the service.:virtual_account_message',
    'loan_overdue_11_to_20_days' => 'Dear customer, your loan is :due_days_difference overdue, repay today to be able to enjoy higher offers so you can continue to enjoy the best offers from QuickFund.:virtual_account_message',
    'loan_overdue_21_to_60_days' => 'Dear customer, you are at risk of being reported and this may reduce your chance of getting loans from other loan companies, repay your loan today to keep enjoying the service.:virtual_account_message',
    'loan_overdue_more_than_60_days' => 'Dear customer, you are due to be reported to credit bureau in a few days time, this will affect your credit score and reduce your chances of getting a loan anywhere in the future.:virtual_account_message',
    'loan_fully_repaid_after_1_day' => "We appreciate you for trusting QuickFund to take care of your financial need! Don't miss out on the opportunity to get higher amount. #YouDeserveCredit",
    'loan_fully_repaid_after_5_days' => 'Dear customer, we have a higher loan offer available for you. Click http://cutt.ly/KNXqzzB to apply now. #YouDeserveCredit.',
    'loan_fully_repaid_after_10_days' => 'Dear customer, a new loan offer awaits you today! Click http://cutt.ly/KNXqzzB to apply for an instant loan now. You Deserve Credit!',

];
