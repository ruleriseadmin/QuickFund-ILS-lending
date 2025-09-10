<?php

namespace App\Exceptions\Interswitch;

use Exception;
use Illuminate\Support\Str;
use App\Traits\Response\Interswitch;
use App\Services\Calculation\Money as MoneyCalculator;

class OutstandingLoanException extends Exception
{
    use Interswitch;

    /**
     * The outstanding loans
     */
    private $outstandingLoans;

    /**
     * Create an instance
     */
    public function __construct($outstandingLoans)
    {
        // The total higher denomination amount
        $totalAmountHigherDenomination = app()->make(MoneyCalculator::class, [
            'value' => $outstandingLoans->sum('loan.amount_remaining') + $outstandingLoans->sum('loan.penalty_remaining')
        ])->toHigherDenomination()->getValue();

        parent::__construct(__('interswitch.outstanding_loans', [
            'count' => $outstandingLoans->count(),
            'pluralization' => Str::plural('loan', $outstandingLoans),
            'amount' => config('quickfund.currency_representation').number_format($totalAmountHigherDenomination, 2)
        ]));
    }

    /**
     * Report the exception.
     *
     * @return bool|null
     */
    public function report()
    {
        //
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render($request)
    {
        return $this->sendInterswitchOutstandingLoanErrorMessage($this->getMessage());
    }
}
