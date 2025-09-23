<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\Customer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessFirstCentralReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    protected $customers;

    /**
     * Create a new job instance.
     *
     * @param \Illuminate\Support\Collection $customers
     */
    public function __construct($customers)
    {
        $this->customers = $customers;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Step 1: Authenticate
            $loginResponse = Http::post(config('services.first_central.reporting_base_url') . '/login', [
                'username' => config('services.first_central.reporting_username'),
                'password' => config('services.first_central.reporting_password'),
            ]);

            if (!$loginResponse->ok()) {
                Log::error('FirstCentral login failed', [
                    'response' => $loginResponse->body()
                ]);
                return;
            }

            // Extract DataTicket (auth token)
            $token = $loginResponse->json('0.DataTicket');
            if (!$token) {
                Log::error('FirstCentral token missing', [
                    'response' => $loginResponse->json(),
                ]);
                return;
            }

            // Step 2: Map customers into FirstCentral format
            $payload = $this->customers->flatMap(function (Customer $customer) {
                return $customer->loans()
                    ->whereNull('first_central_reported_at')
                    ->get()
                    ->map(function ($loan) use ($customer) {
                        return [
                            "loan_id" => $loan->id,
                            "CUSTOMERID" => $customer->formatted_customer_id,
                            "BRANCHCODE" => $customer->bank_code ?? "",
                            "SURNAME" => $customer->last_name ?? "",
                            "FIRSTNAME" => $customer->first_name ?? "",
                            "MIDDLENAME" => null,
                            "DATEOFBIRTH" => optional($customer->date_of_birth)->format('Ymd'),
                            "NATIONALIDENTITYNUMBER" => "",
                            "DRIVERSLICENSENUMBER" => "",
                            "BVNNUMBER" => $customer->bvn ?? "",
                            "PASSPORTNUMBER" => "",
                            "PENCOMIDNUMBER" => "",
                            "OTHERID" => "",
                            "GENDER" => $customer->gender ?? "",
                            "NATIONALITY" => $customer->country_code ?? "Nigeria",
                            "MARITALSTATUS" => "",
                            "MOBILENUMBER" => $customer->phone_number ?? "",
                            "PRIMARYADDRESSLINE1" => $customer->address ?? "",
                            "PRIMARYADDRESSLINE2" => "",
                            "PRIMARYADDRESSCITY" => $customer->city ?? "",
                            "PRIMARYADDRESSSTATE" => $customer->state ?? "",
                            "PRIMARYADDRESSCOUNTRY" => $customer->country_code ?? "Nigeria",
                            "PRIMARYADDRESSPOSTCODE" => "",
                            "EMPLOYMENTSTATUS" => "",
                            "OCCUPATION" => "",
                            "BUSINESSCATEGORY" => "",
                            "BUSINESSSECTOR" => "",
                            "BORROWERTYPE" => "Individual",
                            "TAXID" => "",
                            "PICTUREFILEPATH" => "",
                            "EMAILADDRESS" => $customer->email ?? "",
                            "EMPLOYERNAME" => "",
                            "EMPLOYERADDRESSLINE1" => "",
                            "EMPLOYERADDRESSLINE2" => "",
                            "EMPLOYERCITY" => "",
                            "EMPLOYERSTATE" => "",
                            "EMPLOYERCOUNTRY" => "",
                            "TITLE" => "",
                            "PLACEOFBIRTH" => "",
                            "WORKTELEPHONE" => "",
                            "HOMETELEPHONE" => "",
                            "SECONDARYADDRESSLINE1" => "",
                            "SECONDARYADDRESSLINE2" => "",
                            "SECONDARYADDRESSCITYLGA" => "",
                            "SECONDARYADDRESSSTATE" => "",
                            "SECONDARYADDRESSCOUNTRY" => "",
                            "SECONDARYADDRESSPOSTCODE" => "",
                            "SPOUSESURNAME" => "",
                            "SPOUSEFIRSTNAME" => "",
                            "SPOUSEMIDDLENAME" => "",
                            "ACCOUNTNUMBER" => $loan->destination_account_number ?? $customer->account_number ?? "",
                            "ACCOUNTSTATUS" => in_array($loan->loanOffer?->status, [\App\Models\LoanOffer::OPEN, \App\Models\LoanOffer::OVERDUE]) ? "1" : "0",
                            "ACCOUNTSTATUSDATE" => optional($loan->due_date)->format('Ymd'),
                            "LOANEFFECTIVEDATE" => optional($loan->created_at)->format('Ymd'),
                            "DEFEREDPAYMENTDATE" => "",
                            "CREDITLIMIT" => "0",
                            "AVAILEDLIMIT" => "0",
                            "OUTSTANDINGBALANCE" => $loan->amount_remaining ?? 0,
                            "CURRENTBALANCEDEBITIND" => "",
                            "INSTALMENTAMOUNT" => "0",
                            "CURRENCY" => "NGN",
                            "DAYSINARREARS" => $loan->days_in_arrears ?? 0,
                            "OVERDUEAMOUNT" => $loan->defaults ?? 0,
                            "FACILITYTYPE" => "Personal Loan",
                            "FACILITYTENOR" => $loan->loanOffer?->tenure ?? 0,
                            "FACILITYOWNERSHIPTYPE" => "",
                            "REPAYMENTFREQUENCY" => "Monthly",
                            "LASTPAYMENTDATE" => optional($loan->next_due_date)->format('Ymd'),
                            "LASTPAYMENTAMOUNT" => "0",
                            "MATURITYDATE" => optional($loan->due_date)->format('Ymd'),
                            "INCOME" => "",
                            "INCOMEFREQUENCY" => "",
                            "OWNERTENANT" => "",
                            "NUMBEROFPARTICIPANTSINJOINTLOAN" => "",
                            "DEPENDANTS" => "",
                            "LOANCLASSIFICATION" => ($loan->defaults > 0 ? "Lost" : "Performing"),
                            "LEGALCHALLENGESTATUS" => "NO",
                            "LITIGATIONDATE" => "",
                            "CONSENTSTATUS" => "YES",
                            "LOANSECURITYSTATUS" => "NO",
                            "COLLATERALTYPE" => "",
                            "COLLATERALDETAILS" => "",
                            "PREVIOUSACCOUNTNUMBER" => "",
                            "PREVIOUSNAME" => "",
                            "PREVIOUSCUSTOMERID" => "",
                            "PREVIOUSBRANCHCODE" => "",
                            "CUSTOMERSACCOUNTNUMBER" => "",
                            "GUARANTEESTATUSOFLOAN" => "",
                            "TYPEOFGUARANTEE" => "",
                            "NAMEOFCORPORATEGUARANTOR" => "",
                            "BIZIDNUMBEROFCORPORATEGUARANTOR" => "",
                            "INDIVIDUALGUARANTORSURNAME" => "",
                            "INDIVIDUALGUARANTORFIRSTNAME" => "",
                            "INDIVIDUALGUARANTORMIDDLENAME" => "",
                            "GUARANTORDATEOFBIRTHINCORPORATION" => "",
                            "GUARANTORGENDER" => "",
                            "GUARANTORNATIONALIDNUMBER" => "",
                            "GUARANTORINTLPASSPORTNUMBER" => "",
                            "GUARANTORDRIVERSLICENCENUMBER" => "",
                            "GUARANTORBVN" => "",
                            "GUARANTOROTHERID" => "",
                            "GUARANTORPRIMARYADDRESSLINE1" => "",
                            "GUARANTORPRIMARYADDRESSLINE2" => "",
                            "GUARANTORPRIMARYADDRESSCITYLGA" => "",
                            "GUARANTORPRIMARYADDRESSSTATE" => "",
                            "GUARANTORPRIMARYADDRESSCOUNTRY" => "",
                            "GUARANTORPRIMARYPHONENUMBER" => "",
                            "GUARANTOREMAIL" => "",
                        ];
                    });
            })->values()->toArray();

            if (empty($payload)) {
                Log::info('No new loans to report to FirstCentral.');
                return;
            }

            // Step 3: Submit JSON data in chunks
            collect($payload)
                ->chunk(20)
                ->each(function ($chunk, $index) use ($token) {

                    // Extract IDs for updating later
                    $loanIds = collect($chunk)->pluck('loan_id');

                    // Prepare payload without loan_id
                    $cleanPayload = collect($chunk)->map(function ($item) {
                        return collect($item)->except(['loan_id'])->toArray();
                    })->toArray();


                    // Submit
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                    ])->post(config('services.first_central.reporting_base_url') . '/uploadjson', $cleanPayload);

                    if ($response->failed()) {
                        Log::error("FirstCentral upload failed for chunk {$index}", [
                            'chunk_size' => $chunk->count(),
                            'response' => $response->body(),
                        ]);
                    } else {
                        // ✅ Update successfully reported loans
                        Loan::whereIn('id', $loanIds)->update([
                            'first_central_reported_at' => now(),
                        ]);

                        Log::info("FirstCentral upload success for chunk {$index}", [
                            'count' => $chunk->count(),
                            'response' => $response->json(),
                        ]);
                    }


                });

        } catch (\Throwable $e) {
            Log::error('Process FirstCentralReport job failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
