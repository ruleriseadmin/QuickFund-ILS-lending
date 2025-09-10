<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Bus, Http};
use App\Models\{Customer, LoanOffer, User, Crc, FirstCentral, Loan, Setting};
use App\Exceptions\Interswitch\{
    AccountNumberBlockedException,
    CustomerIneligibleException,
    CustomException as InterswitchCustomException,
    ValidationException as InterswitchValidationException,
    InternalCustomerNotFoundException,
    OfferExpiredException,
    OutstandingLoanException,
    UnknownOfferException
};
use App\Jobs\CustomerVirtualAccount;
use App\Jobs\Interswitch\CreditCustomer;
use Tests\TestCase;

class InterswitchAcceptOfferTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_accepting_offer()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchValidationException::class);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('offers.accept'));

        $response->assertUnprocessable();
    }

    /**
     * Customer not found
     */
    public function test_customer_is_not_found()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InternalCustomerNotFoundException::class);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('offers.accept'), [
            'customerId' => $this->faker->phoneNumber(),
            'offerId' => 'non-existent-id',
            'destinationAccountNumber' => '0123456789',
            'destinationBankCode' => '001',
            'token' => 'token',
            'loanReferenceId' => 'loan_reference'
        ]);
    }

    /**
     * Loan offer not found
     */
    public function test_loan_offer_is_not_found()
    {
        $this->withoutExceptionHandling();
        $this->expectException(UnknownOfferException::class);
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('offers.accept'), [
            'customerId' => $customer->phone_number,
            'offerId' => 'non-existent-id',
            'destinationAccountNumber' => '0123456789',
            'destinationBankCode' => '001',
            'token' => 'token',
            'loanReferenceId' => 'loan_reference'
        ]);
    }

    /**
     * Loan offer has expired
     */
    public function test_loan_offer_has_expired()
    {
        $this->withoutExceptionHandling();
        $this->expectException(OfferExpiredException::class);
        $customer = Customer::factory()
            ->create();
        $loanOffer = LoanOffer::factory()
            ->for($customer)
            ->create([
                'expiry_date' => now()->subDays(3)
            ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('offers.accept'), [
            'customerId' => $customer->phone_number,
            'offerId' => $loanOffer->id,
            'destinationAccountNumber' => '0123456789',
            'destinationBankCode' => '001',
            'token' => 'token',
            'loanReferenceId' => 'loan_reference'
        ]);
    }

    /**
     * Unable to accept loan offer if there are outstanding loans
     */
    public function test_loan_offer_cannot_be_accepted_when_customer_has_outstanding_loans()
    {
        $this->withoutExceptionHandling();
        $this->expectException(OutstandingLoanException::class);
        $customer = Customer::factory()
            ->create();
        $loanOffer = LoanOffer::factory()
            ->for($customer)
            ->create([
                'status' => LoanOffer::OPEN
            ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('offers.accept'), [
            'customerId' => $customer->phone_number,
            'offerId' => $loanOffer->id,
            'destinationAccountNumber' => '0123456789',
            'destinationBankCode' => '001',
            'token' => 'token',
            'loanReferenceId' => 'loan_reference'
        ]);
    }

    /**
     * Unable to accept loan offer when status is not "NONE"
     */
    public function test_loan_offer_cannot_be_accepted_when_status_is_not_none()
    {
        $customer = Customer::factory()
            ->create();
        $loanOffer = LoanOffer::factory()
            ->for($customer)
            ->create([
                'status' => LoanOffer::CLOSED
            ]);
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchCustomException::class);
        $this->expectExceptionMessage(__('interswitch.loan_unacceptable', [
            'loan_status' => $loanOffer->status,
            'expected_loan_status' => LoanOffer::NONE
        ]));

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('offers.accept'), [
            'customerId' => $customer->phone_number,
            'offerId' => $loanOffer->id,
            'destinationAccountNumber' => '0123456789',
            'destinationBankCode' => '001',
            'token' => 'token',
            'loanReferenceId' => 'loan_reference'
        ]);
    }

    /**
     * Account number is blocked from collecting a loan
     */
    // public function test_account_number_is_blocked_from_collecting_a_loan()
    // {
    //     $accountNumber = '1100000000';
    //     $bankCode = '001';
    //     $accountNumber = '0140940818';
    //     $bankCode = '058';
    //     $destinationAccountNumber = '1111000000';
    //     $destinationBankCode = '001';
    //     $this->withoutExceptionHandling();
    //     $this->expectException(AccountNumberBlockedException::class);
    //     $customer = Customer::factory()
    //                         ->create();
    //     $loanOffer = LoanOffer::factory()
    //                         ->for($customer)
    //                         ->create([
    //                             'status' => loanOffer::CLOSED
    //                         ]);
    //     $loan = Loan::factory()
    //                 ->for($loanOffer)
    //                 ->create([
    //                     'destination_account_number' => $accountNumber,
    //                     'destination_bank_code' => $bankCode
    //                 ]);
    //     $loanOffer2 = LoanOffer::factory()
    //                         ->for($customer)
    //                         ->create();
    //     Bus::fake();
    //     Http::fake([
    // config('services.interswitch.oauth_token_url') => Http::response([
    //     'access_token' => 'access token'
    // ], 200, [
    //     'Content-Type' => 'application/json'
    // ]),
    // config('services.interswitch.name_enquiry_base_url')."inquiry/bank-code/{$bankCode}/account/{$accountNumber}" => Http::response([
    //     'responseCode' => '00',
    //     'responseMessage' => 'Successful',
    //     'bvn' => '22222222222',
    //     'accountNumber' => $accountNumber,
    //     'bankCode' => $bankCode,
    //     'firstName' => $this->faker->firstName(),
    //     'lastName' => $this->faker->lastName(),
    //     'dob' => '01/01/1990',
    //     'phone' => $this->faker->e164PhoneNumber(),
    //     'residentialAddress' => $this->faker->address()
    // ], 200, [
    //     'Content-Type' => 'application/json'
    // ]),
    //         config('services.crc.url') => Http::response([
    //             'ConsumerHitResponse' => [
    //                 'BODY' => [
    //                     'SummaryOfPerformance' => null,
    //                     'ReportDetailBVN' => null,
    //                     'ContactHistory' => null,
    //                     'AddressHistory' => null,
    //                     'ClassificationInsType' => null,
    //                     'ClassificationProdType' => null,
    //                     'CREDIT_SCORE_DETAILS' => null,
    //                     'CREDIT_NANO_SUMMARY' => [
    //                         'SUMMARY' => [
    //                             'HAS_CREDITFACILITIES' => 'YES',
    //                             'LAST_REPORTED_DATE' => '30-APR-2022',
    //                             'NO_OF_DELINQCREDITFACILITIES' => '0'
    //                         ]
    //                     ],
    //                     'MFCREDIT_NANO_SUMMARY' => [
    //                         'SUMMARY' => [
    //                             'HAS_CREDITFACILITIES' => 'YES',
    //                             'NO_OF_DELINQCREDITFACILITIES' => '0',
    //                         ]
    //                     ],
    //                     'MGCREDIT_NANO_SUMMARY' => [
    //                         'SUMMARY' => [
    //                             'HAS_CREDITFACILITIES' => 'NO',
    //                             'NO_OF_DELINQCREDITFACILITIES' => '0'
    //                         ]
    //                     ],
    //                     'NANO_CONSUMER_PROFILE' => 'Consumer profile details'
    //                 ],
    //                 'HEADER' => 'Header content'
    //             ]
    //         ], 200, [
    //             'Content-Type' => 'application/json'
    //         ]),

    //         config('services.first_central.base_url').'Login' => Http::response([
    //             [
    //                 'DataTicket' => 'Data ticket content'
    //             ]
    //         ], 200, [
    //             'Content-Type' => 'application/json'
    //         ]),

    //         config('services.first_central.base_url').'ConnectConsumerMatch' => Http::response([
    //             [
    //                 'MatchedConsumer' => [
    //                     [
    //                         'ConsumerID' => '178520820',
    //                         'EnquiryID' => '60260335',
    //                         'MatchingEngineID' => '76566'
    //                     ]
    //                 ]
    //             ]
    //         ], 200, [
    //             'Content-Type' => 'application/json'
    //         ]),

    //         config('services.first_central.base_url').'XScoreConsumerPrimeReport' => Http::response([
    //             [
    //                 'SubjectList' => 'subject list content'
    //             ],
    //             [
    //                 'PersonalDetailsSummary' => 'Personal details summary content'
    //             ],
    //             [
    //                 'Scoring' => [
    //                     [
    //                         'TotalConsumerScore' => config('quickfund.minimum_approved_credit_score')
    //                     ]
    //                 ]
    //             ],
    //             [
    //                 'CreditSummary' => [
    //                     [
    //                         'NumberofAccountsInBadStanding' => '0'
    //                     ]
    //                 ]
    //             ],
    //             [
    //                 'PerformanceClassification' => 'Performance classification details'
    //             ],
    //             [
    //                 'EnquiryDetails' => 'Enquiry details'
    //             ]
    //         ], 200, [
    //             'Content-Type' => 'application/json'
    //         ]),
    //     ]);

    //     $this->actingAs(User::factory()->interswitch()->create());
    //     $response = $this->postJson(route('offers.accept'), [
    //         'customerId' => $customer->phone_number,
    //         'offerId' => $loanOffer2->id,
    //         'destinationAccountNumber' => $destinationAccountNumber,
    //         'destinationBankCode' => $destinationBankCode,
    //         'token' => 'token',
    //         'loanReferenceId' => 'loan_reference'
    //     ]);
    // }

    /**
     * Customer is ineligible to accept the offer
     */
    public function test_customer_is_ineligible_for_accepting_offer()
    {
        $accountNumber = '1100000000';
        $bankCode = '001';
        $this->withoutExceptionHandling();
        $this->expectException(CustomerIneligibleException::class);
        $customer = Customer::factory()
            ->create();
        $loanOffer = LoanOffer::factory()
            ->for($customer)
            ->create();
        Bus::fake();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            // config('services.interswitch.name_enquiry_base_url')."inquiry/bank-code/{$bankCode}/account/{$accountNumber}" => Http::response([
            //     'responseCode' => '00',
            //     'responseMessage' => 'Successful',
            //     'bvn' => '22222222222',
            //     'accountNumber' => $accountNumber,
            //     'bankCode' => $bankCode,
            //     'firstName' => $this->faker->firstName(),
            //     'lastName' => $this->faker->lastName(),
            //     'dob' => '01/01/1990',
            //     'phone' => $this->faker->e164PhoneNumber(),
            //     'residentialAddress' => $this->faker->address()
            // ], 200, [
            //     'Content-Type' => 'application/json'
            // ]),

            config('services.crc.url') => Http::response([
                'ConsumerHitResponse' => [
                    'BODY' => [
                        'SummaryOfPerformance' => null,
                        'ReportDetailBVN' => null,
                        'ContactHistory' => null,
                        'AddressHistory' => null,
                        'ClassificationInsType' => null,
                        'ClassificationProdType' => null,
                        'CREDIT_SCORE_DETAILS' => null,
                        'CREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'YES',
                                'LAST_REPORTED_DATE' => '30-APR-2022',
                                'NO_OF_DELINQCREDITFACILITIES' => config('quickfund.maximum_outstanding_loans_to_qualify') + 1
                            ]
                        ],
                        'MFCREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'YES',
                                'NO_OF_DELINQCREDITFACILITIES' => config('quickfund.maximum_outstanding_loans_to_qualify') + 1,
                            ]
                        ],
                        'MGCREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'NO',
                                'NO_OF_DELINQCREDITFACILITIES' => config('quickfund.maximum_outstanding_loans_to_qualify') + 1
                            ]
                        ],
                        'NANO_CONSUMER_PROFILE' => 'Consumer profile details'
                    ],
                    'HEADER' => 'Header content'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.first_central.base_url') . 'Login' => Http::response([
                [
                    'DataTicket' => 'Data ticket content'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.first_central.base_url') . 'ConnectConsumerMatch' => Http::response([
                [
                    'MatchedConsumer' => [
                        [
                            'ConsumerID' => '178520820',
                            'EnquiryID' => '60260335',
                            'MatchingEngineID' => '76566'
                        ]
                    ]
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.first_central.base_url') . 'XScoreConsumerPrimeReport' => Http::response([
                [
                    'SubjectList' => 'subject list content'
                ],
                [
                    'PersonalDetailsSummary' => 'Personal details summary content'
                ],
                [
                    'Scoring' => [
                        [
                            'TotalConsumerScore' => $this->faker->numberBetween(config('quickfund.minimum_approved_credit_score'))
                        ]
                    ]
                ],
                [
                    'CreditSummary' => [
                        [
                            'NumberofAccountsInBadStanding' => config('quickfund.maximum_outstanding_loans_to_qualify') + 1
                        ]
                    ]
                ],
                [
                    'PerformanceClassification' => 'Performance classification details'
                ],
                [
                    'EnquiryDetails' => 'Enquiry details'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('offers.accept'), [
            'customerId' => $customer->phone_number,
            'offerId' => $loanOffer->id,
            'destinationAccountNumber' => $accountNumber,
            'destinationBankCode' => $bankCode,
            'token' => 'token',
            'loanReferenceId' => 'loan_reference'
        ]);
    }

    /**
     * Offer accepted successfully
     */
    public function test_loan_offer_accepted_successfully()
    {
        $accountNumber = '1100000000';
        $bankCode = '001';
        $bvn = '22222222222';
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $address = $this->faker->address();
        $this->withoutExceptionHandling();
        $customer = Customer::factory()
            ->create();
        $loanOffer = LoanOffer::factory()
            ->for($customer)
            ->create();
        Bus::fake();
        Setting::factory()->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            // config('services.interswitch.name_enquiry_base_url')."inquiry/bank-code/{$bankCode}/account/{$accountNumber}" => Http::response([
            //     'responseCode' => '00',
            //     'responseMessage' => 'Successful',
            //     'bvn' => $bvn,
            //     'accountNumber' => $accountNumber,
            //     'bankCode' => $bankCode,
            //     'firstName' => $firstName,
            //     'lastName' => $lastName,
            //     'dob' => '01/01/1990',
            //     'phone' => $this->faker->e164PhoneNumber(),
            //     'residentialAddress' => $address
            // ], 200, [
            //     'Content-Type' => 'application/json'
            // ]),

            config('services.crc.url') => Http::response([
                'ConsumerHitResponse' => [
                    'BODY' => [
                        'SummaryOfPerformance' => null,
                        'ReportDetailBVN' => null,
                        'ContactHistory' => null,
                        'AddressHistory' => null,
                        'ClassificationInsType' => null,
                        'ClassificationProdType' => null,
                        'CREDIT_SCORE_DETAILS' => null,
                        'CREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'YES',
                                'LAST_REPORTED_DATE' => '30-APR-2022',
                                'NO_OF_DELINQCREDITFACILITIES' => '0'
                            ]
                        ],
                        'MFCREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'YES',
                                'NO_OF_DELINQCREDITFACILITIES' => '0',
                            ]
                        ],
                        'MGCREDIT_NANO_SUMMARY' => [
                            'SUMMARY' => [
                                'HAS_CREDITFACILITIES' => 'NO',
                                'NO_OF_DELINQCREDITFACILITIES' => '0'
                            ]
                        ],
                        'NANO_CONSUMER_PROFILE' => 'Consumer profile details'
                    ],
                    'HEADER' => 'Header content'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.first_central.base_url') . 'Login' => Http::response([
                [
                    'DataTicket' => 'Data ticket content'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.first_central.base_url') . 'ConnectConsumerMatch' => Http::response([
                [
                    'MatchedConsumer' => [
                        [
                            'ConsumerID' => '178520820',
                            'EnquiryID' => '60260335',
                            'MatchingEngineID' => '76566'
                        ]
                    ]
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.first_central.base_url') . 'XScoreConsumerPrimeReport' => Http::response([
                [
                    'SubjectList' => 'subject list content'
                ],
                [
                    'PersonalDetailsSummary' => 'Personal details summary content'
                ],
                [
                    'Scoring' => [
                        [
                            'TotalConsumerScore' => config('quickfund.minimum_approved_credit_score')
                        ]
                    ]
                ],
                [
                    'CreditSummary' => [
                        [
                            'NumberofAccountsInBadStanding' => '0'
                        ]
                    ]
                ],
                [
                    'PerformanceClassification' => 'Performance classification details'
                ],
                [
                    'EnquiryDetails' => 'Enquiry details'
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->postJson(route('offers.accept'), [
            'customerId' => $customer->phone_number,
            'offerId' => $loanOffer->id,
            'destinationAccountNumber' => $accountNumber,
            'destinationBankCode' => $bankCode,
            'token' => 'token',
            'loanReferenceId' => 'loan_reference'
        ]);
        $loanOffer->refresh();
        $customer->refresh();

        $response->assertOk();
        $this->assertDatabaseHas('loans', [
            'loan_offer_id' => $loanOffer->id
        ]);
        // $this->assertSame($firstName, $customer->first_name);
        // $this->assertSame($lastName, $customer->last_name);
        // $this->assertSame($bvn, $customer->bvn);
        // $this->assertSame($address, $customer->address);
        $this->assertSame(LoanOffer::ACCEPTED, $loanOffer->status);
        Bus::assertDispatched(CustomerVirtualAccount::class);
        Bus::assertDispatched(CreditCustomer::class);
        // $this->assertDatabaseHas('crcs', [
        //     'customer_id' => $customer->id
        // ]);
        // $this->assertDatabaseHas('first_centrals', [
        //     'customer_id' => $customer->id
        // ]);
        // $this->assertDatabaseCount('crc_histories', 1);
        // $this->assertDatabaseCount('first_central_histories', 1);
        // $this->assertNotNull($customer->crc_check_last_requested_at);
        // $this->assertNotNull($customer->first_central_check_last_requested_at);
        // $response->assertSee([
        //     'loanId' => $loanOffer->id,
        //     'approvedAmount' => $loanOffer->amount
        // ]);
    }

}
