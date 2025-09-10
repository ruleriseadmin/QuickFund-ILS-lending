<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use App\Models\{Role, User};
use Tests\TestCase;

class AccountResolutionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_initiating_account_resolution()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('account-resolution', [
            'account_number' => null
        ]));

        $response->assertInvalid(['account_number']);
        $response->assertUnprocessable();
    }

    /**
     * Account resolution successful
     */
    public function test_account_resolution_was_successful()
    {
        $accountNumber = '1100000000';
        $bankCode = '001';
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.name_enquiry_base_url')."inquiry/bank-code/{$bankCode}/account/{$accountNumber}" => Http::response([
                'responseCode' => '00',
                'responseMessage' => 'Successful',
                'bvn' => '22222222222',
                'accountNumber' => $accountNumber,
                'bankCode' => $bankCode,
                'firstName' => $this->faker->firstName(),
                'lastName' => $this->faker->lastName(),
                'dob' => '01/01/1990',
                'phone' => $this->faker->e164PhoneNumber(),
                'residentialAddress' => $this->faker->address()
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('account-resolution', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode
        ]));

        $response->assertOk();
    }
}
