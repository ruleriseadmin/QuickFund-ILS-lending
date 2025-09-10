<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Exceptions\Interswitch\{
    CustomException as InterswitchCustomException,
    NoOfferException,
    OutstandingLoanException,
    ValidationException as InterswitchValidationException
};
use App\Models\{LoanOffer, Customer, Loan, Offer, User};
use Tests\TestCase;

class InterswitchGetOffersTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_getting_offers()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchValidationException::class);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('offers.index'));

        $response->assertUnprocessable();
    }

    /**
     * Interswitch authentication failed
     */
    public function test_interswitch_authentication_failed()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchCustomException::class);
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'description' => 'Invalid credentials'
            ], 401, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('offers.index', [
            'customerId' => $this->faker->phoneNumber()
        ]));
        $response->assertSeeText('Interswitch authentication failed');
    }

    /**
     * Failed to find customer details from Interswitch
     */
    // public function test_failed_to_find_customer_details()
    // {
    //     $this->withoutExceptionHandling();
    //     $this->expectException(InterswitchCustomException::class);
    //     Http::fake([
    //         config('services.interswitch.oauth_token_url') => Http::response([
    //             'access_token' => 'access token'
    //         ], 200, [
    //             'Content-Type' => 'application/json'
    //         ]),

    //         config('services.interswitch.customer_info_url').'*' => Http::response([
    //             'responseCode' => '104',
    //             'responseMessage' => 'Customer does not exist in our records'
    //         ], 400, [
    //             'Content-Type' => 'application/json'
    //         ]),
    //     ]);

    //     $this->actingAs(User::factory()->interswitch()->create());
    //     $response = $this->getJson(route('offers.index', [
    //         'customerId' => $this->faker->phoneNumber()
    //     ]));

    //     $response->assertSeeText('Fetching customer info failed');
    // }

    /**
     * Customer has outstanding loans
     */
    public function test_customer_has_outstanding_loans()
    {
        $this->withoutExceptionHandling();
        $this->expectException(OutstandingLoanException::class);
        $customer = Customer::factory()
            ->hasLoanOffers([
                'status' => LoanOffer::OPEN
            ])
            ->create();
        $customer->load(['loanOffers']);
        $loanOffer = $customer->loanOffers->first();
        $loan = Loan::factory()
            ->for($loanOffer)
            ->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.customer_info_url') . '*' => Http::response([
                'firstName' => 'N/A',
                'lastName' => 'N/A',
                'email' => 'test@example.com',
                'msisdn' => $customer->phone_number,
                'hashedMsisdn' => Str::random(20),
                'encryptedPan' => 'N/A',
                'dateOfBirth' => 'N/A',
                'bvn' => '22222222222',
                'address' => 'N/A',
                'addressCity' => 'N/A',
                'addressState' => 'N/A',
                'accountNumber' => 'N/A',
                'bankCode' => 'N/A',
                'gender' => 'N/A',
                'countryCode' => 'N/A',
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('offers.index', [
            'customerId' => $customer->phone_number
        ]));
    }

    /**
     * Failed to fetch customer credit score from Interswitch
     */
    public function test_failed_to_get_customer_credit_score()
    {
        $this->withoutExceptionHandling();
        $this->expectException(InterswitchCustomException::class);
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.customer_info_url') . '*' => Http::response([
                'firstName' => 'N/A',
                'lastName' => 'N/A',
                'email' => 'test@example.com',
                'msisdn' => $this->faker->phoneNumber(),
                'hashedMsisdn' => Str::random(20),
                'encryptedPan' => 'N/A',
                'dateOfBirth' => 'N/A',
                'bvn' => '22222222222',
                'address' => 'N/A',
                'addressCity' => 'N/A',
                'addressState' => 'N/A',
                'accountNumber' => 'N/A',
                'bankCode' => 'N/A',
                'gender' => 'N/A',
                'countryCode' => 'N/A',
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.customer_credit_score_url') . '*' => Http::response([
                'responseCode' => '104',
                'responseMessage' => 'Customer does not exist in our records'
            ], 400, [
                'Content-Type' => 'application/json'
            ])
        ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('offers.index', [
            'customerId' => $this->faker->phoneNumber()
        ]));

        $response->assertSeeText('Credit score request failed');
    }

    /**
     * No offer for customer
     */
    public function test_customer_has_no_offers()
    {
        $this->withoutExceptionHandling();
        $this->expectException(NoOfferException::class);
        $customer = Customer::factory()
            ->hasLoanOffers()
            ->create();
        $customer->load(['loanOffers']);
        $loanOffer = $customer->loanOffers->first();
        $loan = Loan::factory()
            ->for($loanOffer)
            ->create();
        Offer::query()->delete();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.customer_info_url') . '*' => Http::response([
                'firstName' => 'N/A',
                'lastName' => 'N/A',
                'email' => 'test@example.com',
                'msisdn' => $customer->phone_number,
                'hashedMsisdn' => Str::random(20),
                'encryptedPan' => 'N/A',
                'dateOfBirth' => 'N/A',
                'bvn' => '22222222222',
                'address' => 'N/A',
                'addressCity' => 'N/A',
                'addressState' => 'N/A',
                'accountNumber' => 'N/A',
                'bankCode' => 'N/A',
                'gender' => 'N/A',
                'countryCode' => 'N/A',
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.customer_credit_score_url') . '*' => Http::response([
                'responseCode' => '00',
                'creditScores' => [
                    [
                        'score' => '100',
                        'dateCreated' => '2021-01-01'
                    ]
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('offers.index', [
            'customerId' => $customer->phone_number
        ]));
    }

    /**
     * Offers successfully fetched
     */
    public function test_offers_is_successfully_fetched()
    {
        $phoneNumber = $this->faker->phoneNumber();
        $offers = Offer::factory()->create([
            'amount' => config('quickfund.maximum_amount_for_first_timers')
        ]);
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.customer_info_url') . '*' => Http::response([
                'firstName' => 'N/A',
                'lastName' => 'N/A',
                'email' => 'test@example.com',
                'msisdn' => $phoneNumber,
                'hashedMsisdn' => Str::random(20),
                'encryptedPan' => 'N/A',
                'dateOfBirth' => 'N/A',
                'bvn' => '22222222222',
                'address' => 'N/A',
                'addressCity' => 'N/A',
                'addressState' => 'N/A',
                'accountNumber' => 'N/A',
                'bankCode' => 'N/A',
                'gender' => 'N/A',
                'countryCode' => 'N/A',
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.customer_credit_score_url') . '*' => Http::response([
                'responseCode' => '00',
                'creditScores' => [
                    [
                        'score' => '100',
                        'dateCreated' => '2021-01-01'
                    ]
                ]
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);

        $this->actingAs(User::factory()->interswitch()->create());
        $response = $this->getJson(route('offers.index', [
            'customerId' => $phoneNumber
        ]));

        $response->assertSeeText(__('interswitch.success'));
        Http::assertSentInOrder([
            fn(Request $request) => $request->url() === config('services.interswitch.oauth_token_url'),
            fn(Request $request) => str_starts_with($request->url(), config('services.interswitch.customer_info_url')),
            fn(Request $request) => str_starts_with($request->url(), config('services.interswitch.customer_credit_score_url')),
        ]);
        $this->assertDatabaseHas('customers', [
            'phone_number' => $phoneNumber
        ]);
        $this->assertDatabaseCount('credit_scores', 1);
    }

}
