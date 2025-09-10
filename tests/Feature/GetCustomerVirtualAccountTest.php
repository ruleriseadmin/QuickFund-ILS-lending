<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use App\Models\{Customer, User, Role};
use Tests\TestCase;

class GetCustomerVirtualAccountTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Customer not found
     */
    public function test_customer_is_not_found()
    {
        $customer = Customer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('customers.virtual-accounts', [
            'customer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Virtual account successfully fetched or created
     */
    public function test_customer_virtual_account_was_successfully_fetched()
    {
        $accountName = $this->faker->name();
        $accountNumber = '1100000000';
        $bankName = 'Wema Bank';
        $bankCode = 'WEMA';
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/customer-virtual-account' => Http::response([
                'payableCode' => 'VIRTUAL_ACCOUNTMX'.rand(1000000, 999999999999),
                'accountName' => $accountName,
                'accountNumber' => $accountNumber,
                'bankName' => $bankName,
                'bankCode' => $bankCode
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);
        $customer = Customer::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('customers.virtual-accounts', [
            'customer' => $customer
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('virtual_accounts', [
            'customer_id' => $customer->id,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'bank_name' => $bankName,
            'bank_code' => $bankCode
        ]);
    }
}
