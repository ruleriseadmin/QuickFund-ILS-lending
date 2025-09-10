<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\{Str, Carbon};
use App\Http\Requests\SmsRequest;
use App\Jobs\Interswitch\SendSms;
use App\Exceptions\CustomException;
use App\Models\Customer;
use App\Services\Calculation\Money as MoneyCalculator;

class SmsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(SmsRequest $request)
    {
        $data = $request->validated();

        // Get the customers
        $customers = Customer::with([
                                'latestLoanOffer',
                                'latestLoanOffer.loan',
                                'virtualAccount'
                            ])
                            ->has('latestLoanOffer')
                            ->find($data['customer_ids']);

        // Check if there are no customers
        if ($customers->isEmpty()) {
            throw new CustomException('None of the customers have collected a loan.', 404);
        }

        // The currency representation
        $currencyRepresentation = config('quickfund.currency_representation');

        // Send the SMS to each customer
        $customers->each(function($customer) use ($data, $currencyRepresentation) {
            $higherDenominationLoanBalance = app()->make(MoneyCalculator::class, [
                'value' => $customer->latestLoanOffer->loan->amount_remaining + $customer->latestLoanOffer->loan->penalty_remaining
            ])->toHigherDenomination()->getValue();

            $replaceables = [
                ':VIRTUAL_ACCOUNT' => isset($customer->virtualAccount) ? "{$customer->virtualAccount->bank_name}, Acc No: {$customer->virtualAccount->account_number}" : '',
                ':AMOUNT_DUE' => $currencyRepresentation.number_format($higherDenominationLoanBalance, 2),
                ':DUE_DATE' => Carbon::parse($customer->latestLoanOffer->loan->due_date)->format('jS F, Y'),
                ':PAYMENT_LINK' => config('quickfund.loan_request_url'),
                ':PAYMENT_USSD' => '*723*3389001*'.ceil($higherDenominationLoanBalance).'#'
            ];
            
            $message = Str::of($data['message'])->replace(array_keys($replaceables), array_values($replaceables));

            // Send the messages
            SendSms::dispatch(
                $message,
                $customer->phone_number,
                $customer->latestLoanOffer->id,
                true
            );
        });

        return $this->sendSuccess('Messages are being sent.');
    }
}
