<?php

namespace App\Traits\Extensions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\{Carbon, Arr};
use App\Models\{Blacklist, Offer, LoanOffer, Setting, Whitelist};
use App\Exceptions\Interswitch\{BlacklistedException, CustomerIneligibleException, NoOfferException};
use App\Services\Phone\Nigeria as NigerianPhone;
use App\Services\CreditBureau\{
    Crc as CrcService,
    FirstCentral as FirstCentralService
};
use App\Services\Interswitch as InterswitchService;

trait CustomerManager
{
    /**
     * Get the offers the customer qualifies for
     */
    public function getOffers($amount = null, $channelCode = null)
    {

        /**
         * Get the application settings
         */
        $setting = Setting::find(Setting::MAIN_ID);

        // If loans should be given
        $shouldGiveLoans = $setting?->should_give_loans ?? config('quickfund.should_give_loans');

        // Check if the application should give loans
        if (!$shouldGiveLoans) {
            throw new NoOfferException;
        }

        // Check if the customer is blacklisted
        if ($this->isBlacklistedManually()) {
            throw new BlacklistedException;
        }

        /**
         * This check is used when credit score check is disabled but the customer does not have BVN
         * and is not whitelisted
         */
        if (
            (!$this->isWhitelistedManually() && !isset($this->bvn)) ||
            (!$this->isWhitelistedByCode() && !isset($this->bvn))
        ) {
            throw new NoOfferException;
        }

        // Get the customer's credit score
        $scoreDetails = app()->make(InterswitchService::class)->creditScore($this->phone_number);

        // Resolve the first time offer that is available to the customer based on their credit score
        $firstTimeAmount = $this->firstTimeOfferAmount($scoreDetails, $setting);

        /**
         * We use the logic based on the application to resolve the offers of a user. We put whatever logic we want
         * and we return the offers in the "$offers" variable as a collection
         */
        $offers = $this->processOffers($firstTimeAmount, $setting, $channelCode);

        // Check if the offers is empty
        if ($offers->isEmpty()) {
            throw new NoOfferException;
        }

        // Initialize the loan offers
        $loanOffers = collect([]);

        // Loop through each qualified offer
        $offers->each(function ($offer) use (&$loanOffers, $channelCode) {
            Arr::set($offer, 'offer_id', $offer->id);
            Arr::set($offer, 'channel_code', $channelCode);
            Arr::forget($offer, 'id');

            $loanOffers->push($offer);
        });

        // Create the loan offers and update the credit score of the customer
        $offers = DB::transaction(function () use ($loanOffers, $scoreDetails) {
            $this->creditScore()->updateOrCreate([

            ], [
                'score' => $scoreDetails['creditScores'][0]['score'],
                'date' => Carbon::parse($scoreDetails['creditScores'][0]['dateCreated'])->format('Y-m-d')
            ]);

            return $this->loanOffers()->createMany($loanOffers->toArray());
        });

        return $offers;
    }

    /**
     * Check to know if a customer is blacklisted manually
     */
    public function isBlacklistedManually()
    {
        return Blacklist::where('phone_number', app()->make(NigerianPhone::class)->convert($this->phone_number))
            ->where('type', Blacklist::MANUALLY)
            ->exists();
    }

    /**
     * Check to know if a customer is blacklisted by the code
     */
    public function isBlacklistedByCode()
    {
        return Blacklist::where('phone_number', app()->make(NigerianPhone::class)->convert($this->phone_number))
            ->where('type', Blacklist::BY_CODE)
            ->exists();
    }

    /**
     * Check to know if a customer is whitelisted manually
     */
    public function isWhitelistedManually()
    {
        return Whitelist::where('phone_number', app()->make(NigerianPhone::class)->convert($this->phone_number))
            ->where('type', Whitelist::MANUALLY)
            ->exists();
    }

    /**
     * Check to know if a customer is whitelisted by the code
     */
    public function isWhitelistedByCode()
    {
        return Whitelist::where('phone_number', app()->make(NigerianPhone::class)->convert($this->phone_number))
            ->where('type', Whitelist::BY_CODE)
            ->exists();
    }

    /**
     * Perform the credit bureau checks
     */
    public function performCreditBureauChecks()
    {
        $setting = Setting::find(Setting::MAIN_ID);

        info($setting);
        info($this);

        /**
         * Check if the customer fails any credit bureau checks
         */
        if (
            !app()->make(CrcService::class)->passesCheck($this, $setting) ||
            !app()->make(FirstCentralService::class)->passesCheck($this, $setting)
        ) {
            throw new CustomerIneligibleException;
        }
    }

    /**
     * Resolve the first time amount for the customer based on the credit score response from Quickfund
     */
    private function firstTimeOfferAmount($scoreDetails, $setting)
    {
        // The credit score of the customer
        $creditScore = (int) ($scoreDetails['creditScores'][0]['score']);

        // Get the first time amount from the credit score
        return $this->firstTimeAmountFromBucketOffers($creditScore, $setting);
    }

    /**
     * Get the first time offers from the bucket offer
     */
    private function firstTimeAmountFromBucketOffers($creditScore, $setting)
    {
        $bucketOffers = $setting?->bucket_offers;

        // Check if the bucket offers exists
        if (isset($bucketOffers)) {
            return $this->resolveFirstTimeAmountFromBucketOffers($creditScore, $bucketOffers);
        }

        return $this->firstTimeAmountFromApplication($setting);
    }

    /**
     * Get the first time offers from the application
     */
    private function firstTimeAmountFromApplication($setting)
    {
        return $setting?->maximum_amount_for_first_timers ?? config('quickfund.maximum_amount_for_first_timers');
    }

    /**
     * Resolve first time offer amount from bucket offers
     */
    private function resolveFirstTimeAmountFromBucketOffers($creditScore, $bucketOffers)
    {
        /**
         * Bucket offers exist, we give the user the offers based on the configuration of the application
         */
        if ($creditScore >= 0 && $creditScore <= 9) {
            // First time offers for credit scores 0 - 9
            $firstTimeAmount = $bucketOffers['bucket_0_to_9'];
        } elseif ($creditScore >= 10 && $creditScore <= 19) {
            // First time offers for credit scores 10 - 19
            $firstTimeAmount = $bucketOffers['bucket_10_to_19'];
        } elseif ($creditScore >= 20 && $creditScore <= 29) {
            // First time offers for credit scores 20 - 29
            $firstTimeAmount = $bucketOffers['bucket_20_to_29'];
        } elseif ($creditScore >= 30 && $creditScore <= 39) {
            // First time offers for credit scores 30 - 39
            $firstTimeAmount = $bucketOffers['bucket_30_to_39'];
        } elseif ($creditScore >= 40 && $creditScore <= 49) {
            // First time offers for credit scores 40 - 49
            $firstTimeAmount = $bucketOffers['bucket_40_to_49'];
        } elseif ($creditScore >= 50 && $creditScore <= 59) {
            // First time offers for credit scores 50 - 59
            $firstTimeAmount = $bucketOffers['bucket_50_to_59'];
        } elseif ($creditScore >= 60 && $creditScore <= 69) {
            // First time offers for credit scores 60 - 69
            $firstTimeAmount = $bucketOffers['bucket_60_to_69'];
        } elseif ($creditScore >= 70 && $creditScore <= 79) {
            // First time offers for credit scores 70 - 79
            $firstTimeAmount = $bucketOffers['bucket_70_to_79'];
        } elseif ($creditScore >= 80 && $creditScore <= 89) {
            // First time offers for credit scores 80 - 89
            $firstTimeAmount = $bucketOffers['bucket_80_to_89'];
        } elseif ($creditScore >= 90) {
            // First time offers for credit scores 90 - 100
            $firstTimeAmount = $bucketOffers['bucket_90_to_100'];
        } else {
            // For some crazy reason, we get a weird credit score
            throw new NoOfferException;
        }

        return $firstTimeAmount;
    }

    private function processOffers($firstTimeAmount, $setting, $channelCode)
    {
        // Calculate the permissible loan amount that the application can still give
        $permissibleLoanAmount = $this->permissibleAmountBorrowableByApplication($setting);

        if ($this->isFirstTimer()) {
            // The offers for first time users
            return $this->offersForAmount($permissibleLoanAmount, $firstTimeAmount, $channelCode);
        } else {
            // The offers for non first time users
            return $this->nonFirstTimerOffers($permissibleLoanAmount, $firstTimeAmount, $setting, $channelCode);
        }
    }

    /**
     * Check to know if a customer is a first timer
     */
    private function isFirstTimer()
    {
        /**
         * We check if a user does not have any collected loan
         */
        return $this->loanOffers()
            ->whereIn('status', [
                LoanOffer::OPEN,
                LoanOffer::CLOSED,
                LoanOffer::OVERDUE
            ])
            ->doesntExist();
    }

    /**
     * The amount that is borrowable based on the total amount allowed by the application
     */
    private function permissibleAmountBorrowableByApplication($setting)
    {
        $totalAmountCreditedPerDay = $setting?->total_amount_credited_per_day ?? config('quickfund.total_amount_credited_per_day');

        return $totalAmountCreditedPerDay - $this->totalLoanAmountBorrowedToday();
    }

    /**
     * Total loans amount borrowed by customers
     */
    private function totalLoanAmountBorrowedToday()
    {
        return LoanOffer::whereIn('status', [
            LoanOffer::OPEN,
            LoanOffer::CLOSED
        ])
            ->whereBetween('updated_at', [
                today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString(),
                today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
            ])
            ->sum('amount');
    }

    /**
     * The offers for a particular amount
     */
    private function offersForAmount($permissibleLoanAmount, $amount, $channelCode)
    {
        // The maximum amount that can be collected from a particular channel
        $channelMaximumAmount = 2000000;

        /**
         * We restrict the maximum loan amount for customers coming from a certain channel.
         */
        if (
            $amount > $channelMaximumAmount && in_array(strtoupper($channelCode), [
                'UBAMOB'
            ])
        ) {
            $amount = $channelMaximumAmount;
        }

        return Offer::where('amount', '<=', $permissibleLoanAmount)
            ->where('amount', '<=', $amount)
            ->get();
    }

    /**
     * The non first time offers a customer
     */
    private function nonFirstTimerOffers($permissibleLoanAmount, $firstTimeAmount, $setting, $channelCode)
    {
        /**
         * We get the most recent fully paid loan to by the customer to know their last loan analysis
         */
        $recentLoan = $this->loanOffers()
            ->with(['loan'])
            ->where('status', LoanOffer::CLOSED)
            ->latest()
            ->first();

        // We check if the customer should be blacklisted
        if ($this->shouldBlacklistCustomer($recentLoan, $setting)) {
            /**
             * If the customer is not blacklisted we blacklist the customer
             */
            if (
                Blacklist::where('phone_number', app()->make(NigerianPhone::class)->convert($this->phone_number))
                    ->where('type', Blacklist::BY_CODE)
                    ->doesntExist()
            ) {
                // Add the customer to the blacklist
                $blacklist = Blacklist::updateOrCreate([
                    'phone_number' => app()->make(NigerianPhone::class)->convert($this->phone_number),
                ], [
                    'type' => Blacklist::BY_CODE,
                    'completed' => false
                ]);

                throw new BlacklistedException;
            } else {
                /**
                 * The customer has already been blacklisted so we check if they have exceeded the total number
                 * of days that they should be blacklisted for
                 */
                $daysToBlacklistCustomer = $setting?->days_to_blacklist_customer ?? config('quickfund.days_to_blacklist_customer');

                // Get the blacklist model of the customer
                $blacklist = Blacklist::where('phone_number', app()->make(NigerianPhone::class)->convert($this->phone_number))
                    ->where('type', Blacklist::BY_CODE)
                    ->first();

                // We check if the user has completed the blacklist period
                if ($blacklist->completed) {
                    // Blacklist period done successfully. The user starts from the first time offer available
                    return $this->offersForAmount($permissibleLoanAmount, $firstTimeAmount, $channelCode);
                }

                // Check if the user should still remain in the blacklist
                if ($blacklist->updated_at->addDays($daysToBlacklistCustomer) > now()) {
                    /**
                     * User should still remain blacklisted
                     */
                    throw new BlacklistedException;
                }

                /**
                 * Blacklist duration has been exceeded and the user is given a pass 
                 */
                $blacklist->update([
                    'completed' => true
                ]);

                // User starts from the first time offers available
                return $this->offersForAmount($permissibleLoanAmount, $firstTimeAmount, $channelCode);
            }
        }

        // We check if the user if should be demoted
        if ($this->shouldDemoteCustomer($recentLoan, $setting)) {
            return $this->offersForAmount($permissibleLoanAmount, $firstTimeAmount, $channelCode);
        }

        /**
         * The recent loan collected by the user is greater than or equal to the first time offer the user
         * qualifies for. So we check if the user should repeat the cycle or they should be promoted to the next
         * cycle
         */
        // Get the offer tied to the recent loan amount collected by the user
        $recentLoanOffer = Offer::firstWhere('amount', $recentLoan->amount);

        /**
         * If the offer does not exist or has been deleted, there is no way to get the number of cycles that the
         * user should repeat before they can be promoted, therefore we use the fact that if they should repeat
         * the cycle based on if they defaulted for some number of days or if they paid on time, we either
         * increase to the next available offer amount or decrease to the next one
         */
        if (!isset($recentLoanOffer)) {
            /**
             * We check if the user should repeat the cycle. If the user should repeat the cycle, we take them back
             * to the loan amount just below the previously collected loan but if they paid on time and should be
             * promoted, we take the user to the next available loan amount
             */
            if ($this->shouldRepeatCycle($recentLoan, null, $setting)) {
                // Return offers with the next amount just lower than the previously collected loan
                return $this->offersForAmount($permissibleLoanAmount, max(Offer::where('amount', '<', $recentLoan->amount)
                    ->max('amount'), $recentLoan->amount, $firstTimeAmount), $channelCode);
            } else {
                // Return offers with the next amount just higher than the previously collected loan
                return $this->offersForAmount($permissibleLoanAmount, max(Offer::where('amount', '>', $recentLoan->amount)
                    ->min('amount'), $recentLoan->amount, $firstTimeAmount), $channelCode);
            }
        }

        // We check if the user should repeat the cycle
        if ($this->shouldRepeatCycle($recentLoan, $recentLoanOffer, $setting)) {
            /**
             * We check if the first time amount is more than the recent loan amount collected by the user, meaning
             * that we can just return the first time offers
             */
            if ($firstTimeAmount > $recentLoan->amount) {
                return $this->offersForAmount($permissibleLoanAmount, $firstTimeAmount, $channelCode);
            }

            return $this->offersForAmount($permissibleLoanAmount, $recentLoan->amount, $channelCode);
        }

        // User is qualified for promotion
        return $this->offersForAmount($permissibleLoanAmount, max(Offer::where('amount', '>', $recentLoan->amount)
            ->min('amount'), $recentLoan->amount, $firstTimeAmount), $channelCode);
    }

    /**
     * Check if the user should be blacklisted
     */
    private function shouldBlacklistCustomer($recentLoan, $setting)
    {
        // The maximum days for demotion
        $maximumDaysForDemotion = $setting?->maximum_days_for_demotion ?? config('quickfund.maximum_days_for_demotion');

        // We check if the customer met the blacklisting criteria
        if ($recentLoan->updated_at->startOfDay() > $recentLoan->loan->due_date->addDays($maximumDaysForDemotion)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the customer should be demoted
     */
    private function shouldDemoteCustomer($recentLoan, $setting)
    {
        // The minimum days for demotion
        $minimumDaysForDemotion = $setting?->minimum_days_for_demotion ?? config('quickfund.minimum_days_for_demotion');

        // The maximum days for demotion
        $maximumDaysForDemotion = $setting?->maximum_days_for_demotion ?? config('quickfund.maximum_days_for_demotion');

        // We check if the customer met the blacklisting criteria
        if (
            $recentLoan->updated_at->startOfDay() > $recentLoan->loan->due_date->addDays($minimumDaysForDemotion) &&
            $recentLoan->updated_at->startOfDay() <= $recentLoan->loan->due_date->addDays($maximumDaysForDemotion)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user should repeat the cycle
     */
    private function shouldRepeatCycle($recentLoan, $recentLoanOffer, $setting)
    {
        // The minimum days for demotion
        $minimumDaysForDemotion = $setting?->minimum_days_for_demotion ?? config('quickfund.minimum_days_for_demotion');

        // We check if the customer met the repeating criteria
        if (
            $recentLoan->updated_at->startOfDay() > $recentLoan->loan->due_date->addDays(2) &&
            $recentLoan->updated_at->startOfDay() <= $recentLoan->loan->due_date->addDays($minimumDaysForDemotion)
        ) {
            return true;
        }

        // We check if the most recent loan collected by the customer was not a quick loan
        if ($recentLoan->updated_at->startOfDay() > $recentLoan->created_at->addDays(7)) {
            /**
             * User did not collect a quick loan so we check the cycles. We check to see if the loan offer exists
             */
            if (isset($recentLoanOffer)) {
                // Initialize the successful cycles done by the user
                $successfulCycles = 0;

                /**
                 * We get the number of times the user collected the loan recent loan amount
                 */
                $recentLoanAmountLoans = $this->loanOffers()
                    ->with(['loan'])
                    ->where('status', LoanOffer::CLOSED)
                    ->where('amount', $recentLoan->amount)
                    ->latest()
                    ->get();

                foreach ($recentLoanAmountLoans as $loan) {
                    /**
                     * We check if the user was successful in the loan payment by checking if they can be promoted
                     * based on the collected loan
                     */
                    if ($this->shouldPromoteCustomer($loan, $setting)) {
                        // Increase the successful cycles count done by the user
                        $successfulCycles++;
                    }

                    // We check if the user should not repeat the cycle based on the successful cycles
                    if ($recentLoanOffer->cycles <= $successfulCycles) {
                        return false;
                    }
                }

                // Should repeat cycle
                return true;
            }

            return false;
        }

        /**
         * We get the loans collected by the user in the past 14 days
         */
        // We get the loans a user has taken within the past 14 days and also paid within those 14 days
        $loansInThePast14Days = $this->loanOffers()
            ->with(['loan'])
            ->where('status', LoanOffer::CLOSED)
            ->whereBetween('created_at', [
                today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->subDays(14)->toDateTimeString(),
                today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
            ])
            ->whereBetween('updated_at', [
                today()->startOfDay()->timezone(config('quickfund.date_query_timezone'))->subDays(14)->toDateTimeString(),
                today()->endOfDay()->timezone(config('quickfund.date_query_timezone'))->toDateTimeString()
            ])
            ->latest()
            ->get();

        // The customer can only collected a maximum of 3 loans in a 14 day period
        if ($loansInThePast14Days->count() <= 3) {
            return true;
        }

        return false;
    }

    /**
     * Check if the customer should be promoted
     */
    private function shouldPromoteCustomer($recentLoan, $setting)
    {
        // We check if the customer met the promotion criteria which is not more than 2 days after due date
        if ($recentLoan->updated_at->startOfDay() > $recentLoan->loan->due_date->addDays(2)) {
            return false;
        }

        return true;
    }

}